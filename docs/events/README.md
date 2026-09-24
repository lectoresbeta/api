# Catálogo de eventos de integración

Los eventos son el **contrato público asíncrono** entre bounded contexts. Un evento del
catálogo es tan contrato como un endpoint de la API: cambiarlo rompe a sus consumidores.

Reglas en [`../architecture/04-cross-context-communication.md`](../architecture/04-cross-context-communication.md).

> Estado: `DRAFT`. Los payloads son propuestas hasta que la funcionalidad que los produce
> alcance `APPROVED`.

---

## Principios

1. Un evento describe un **hecho ya ocurrido**, en pasado.
2. Describe lo que pasó, **nunca lo que otro contexto debe hacer**.
3. Contiene solo datos estables que el consumidor necesita.
4. Nunca contiene agregados, entidades de Doctrine ni contenido de obras, feedback o
   mensajes directos.
5. El consumidor decide qué significa el hecho en su propio modelo.

## Envoltorio común

```json
{
  "eventId": "0192f8a1-4c3b-7e2a-9f10-6b5d2c8e4a71",
  "eventType": "FeedbackSubmitted",
  "eventVersion": 1,
  "occurredAt": "2026-09-21T10:15:30Z",
  "payload": { }
}
```

`eventId` es la clave de deduplicación. Los consumidores idempotentes lo registran antes de
aplicar el efecto.

---

## `User`

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `UserRegistered` | Se crea una cuenta | `Credits` (crea cuenta con saldo 0), `Notification` | `userId`, `username`, `authProvider`, `status`, `invitedBy?` |
| `AccountActivated` | El usuario activa su cuenta desde el correo | **`Credits`** (+10), `Notification`, `Feedback` | `userId`, `activatedAt` |
| `ActivationEmailRequested` | Se pide reenviar el correo de activación | `Notification` | `userId` |
| `LiteraryPreferencesUpdated` | El usuario fija o cambia sus géneros | `Community` | `userId`, `genres` |
| `OnboardingCompleted` | Termina el onboarding | `Notification`, read models | `userId`, `completedAt` |
| `UserProfileUpdated` | Cambian datos públicos | `Community` | `userId`, `name`, `description`, `updatedAt`. **Los valores nuevos, no un diff**: quien lo consume quiere con qué quedarse |
| `UsernameChanged` | El usuario cambia su nombre de usuario, **o recupera uno suyo** | `Community` (read models con el `@`) | `userId`, `previousUsername`, `newUsername`, `aliasExpiresAt`, `changedAt`. El viejo para encontrar qué actualizar, el nuevo para escribirlo, y la fecha para saber hasta cuándo un enlace antiguo sigue llevando a alguna parte |
| `EmailChangeRequested` | Se pide cambiar el correo | `Notification` | `userId`, `requestId`, `expiresAt` |
| `EmailChanged` | Se confirma el cambio de correo | `Notification`, read models | `userId`, `changedAt` |
| `PasswordChanged` | Se cambia la contraseña | `Notification` | `userId`, `changedAt` |
| `PrivacySettingsChanged` | Cambian los ajustes de privacidad | `Community`, read models de visibilidad | `userId`, `profileVisibility`, `commentPermission`, `messagePermission`, `changedAt`. **Nada del perfil** |
| `NotificationPreferencesChanged` | Cambian las preferencias de aviso | `Notification` | `userId`, preferencias modificadas |
| `UserDeleted` | Se elimina la cuenta | Todos | `userId`, `deletedAt`. En `User` convierte su nombre de usuario en alias bloqueado 30 días |
| `InvitedUserParticipated` | Un invitado deja su primer comentario | `Credits` | `inviterId`, `invitedUserId` |

`AccountActivated` es el hecho que abona los créditos de bienvenida, no `UserRegistered`.
`Feedback` también lo consume, para saber qué autores pueden recibir comentarios
([`decision:0003`](../decisions/0003-write-operations-require-activated-account.md)).

> `InvitedUserParticipated` exige correlacionar una invitación de `User` con un hecho de
> `Feedback`. Quién lo publica está sin decidir (`U-4`).
>
> Ningún evento de `User` transporta la contraseña, su hash, el token de activación ni la
> fecha de nacimiento.

## 
`Work`

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `WorkCreated` | Se crea la obra | `Reading`, `Notification` | `workId`, `authorId`, `title`, `accessMode`, `status`, `createdAt` |
| `WorkPublished` | La obra pasa a `PUBLISHED` | `Reading`, `Community`, `Notification` | `workId`, `authorId`, `title`, `wordCount`, `chapterCount`, `publishedAt` |
| `WorkOpenedForCorrection` | La obra pasa a `IN_CORRECTION` | `Reading`, **`Credits`** | `workId`, `authorId`, `openedAt` |
| `WorkClosedForCorrection` | La obra vuelve a `PUBLISHED` | `Reading`, **`Credits`** | `workId`, `authorId`, `closedAt` |
| `ChapterContentUpdated` | Cambia el texto de un capítulo | `Feedback`, **`Credits`** | `chapterId`, `workId`, `authorId`, `position`, `wordCount`, `updatedAt` |
| `WorkAccessModeChanged` | Cambia la modalidad | `Reading` | `workId`, `authorId`, `accessMode`, `changedAt` |
| `WorkDeleted` | Se elimina | `Reading`, `Feedback`, `Community` | `workId`, `authorId` |
| `QuestionnaireUpdated` | Cambia el cuestionario: nueva versión | **`Credits`** | `workId`, `version`, `questionCount`, `requiredWords`, `requiredWordsForEveryChapter`, `updatedAt` |

**Ningún evento de `Work` transporta el contenido de la obra**, y `QuestionnaireUpdated`
tampoco transporta el enunciado de las preguntas: es contenido del autor, y `Credits` no lo
necesita para calcular nada.

`QuestionnaireUpdated` lleva **dos** totales de palabras exigidas porque el precio de un
capítulo cuenta solo las preguntas de alcance `EVERY_CHAPTER`, mientras que el último
capítulo las responde todas ([`FEAT-WRK-014`](../features/work/FEAT-WRK-014-configure-questionnaire.md),
`W-17`). Con un solo total, el autor pagaría en cada capítulo por preguntas que solo se
contestan en uno.

`textTier` ya no aparece en ningún payload: lo eliminó
[`decision:0006`](../decisions/0006-credit-system.md).

## `Reading`

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `AccessRequested` | Un usuario solicita ser LB | `Notification` | `accessRequestId`, `workId`, `authorId`, `readerId` |
| `AccessRequestRejected` | El autor rechaza | `Notification` | `accessRequestId`, `readerId` |
| `BetaReaderAccessGranted` | Se concede acceso | `Notification` | `accessId`, `workId`, `authorId`, `readerId`, `grantedVia`, `grantedAt` |
| `BetaReaderAccessRevoked` | Se retira el acceso | `Notification` | `accessId`, `workId`, `readerId`, `revokedAt` |
| `BetaReaderInvited` | El autor invita | `Notification` | `invitationId`, `workId`, `authorId`, `readerId` |
| `BetaReaderInvitationDeclined` | El invitado rechaza | `Notification` | `invitationId`, `workId`, `authorId`, `readerId` |
| `WritingBuddyProposed` | Se propone el vínculo | `Notification` | `proposalId`, `proposerId`, `targetUserId` |
| `WritingBuddyLinked` | Se acepta | `Notification`, `Community` | `linkId`, `userIds` |

`BetaReaderAccessGranted` llevaba `textTier` y `questionCount` «porque `Credits` los necesita
para calcular la retención». Ya no: `textTier` no existe y
[`decision:0006`](../decisions/0006-credit-system.md) eliminó las retenciones, así que
**`Credits` no consume este evento en absoluto**. Conceder un acceso no mueve ni compromete
créditos.

Ni `AccessRequested` ni `BetaReaderInvited` llevan **el mensaje** que escribe quien los
origina. Es texto de usuario, puede ser largo y no hace falta para avisar: el aviso dice que
hay algo que resolver y quien resuelve lo abre. Es la misma razón por la que
`QuestionnaireUpdated` no transporta los enunciados del cuestionario.

Cancelar una solicitud no publica nada —nadie estaba esperando esa pregunta— y rechazar una
invitación sí: el autor ofreció su obra a una persona concreta y espera respuesta
([`FEAT-RDG-005`](../features/reading/FEAT-RDG-005-resolve-invitation.md) `RN-6`).

`grantedVia` dice por qué camino se llegó —`PUBLIC_JOIN`, `REQUEST_APPROVED`,
`AUTHOR_INVITATION`—, que es lo que permite responder meses después por qué esta persona
tiene acceso.

## `Feedback`

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `CorrectionStarted` | Un LB pulsa «Empezar corrección» | **`Credits`** (anota el precio), `Notification` | `chapterId`, `workId`, `authorId`, `readerId`, `startedAt` |
| `FeedbackSubmitted` | Un LB **envía una corrección** de un capítulo | **`Credits`**, `Notification`, `Community` | `correctionId`, `workId`, **`chapterId`**, `authorId`, `readerId`, `questionnaireVersion`, `submittedAt` |
| `CorrectionDraftDiscarded` | El lector descarta su borrador | **`Credits`** (descarta la anotación), **`Reading`** (revoca el acceso) | `chapterId`, `workId`, `readerId`, `discardedAt` |
| `CorrectionTipped` | El autor propina una corrección | **`Credits`**, `Community` | `correctionId`, `authorId`, `readerId`, `amount` |
| `PublicCorrectionSubmitted` | Corrección por enlace público | `Notification`. **`Credits` NO lo consume** | `correctionId`, `workId`, `chapterId`, `authorId`, `authorLabel?` |
| `FeedbackRatedPositively` | El autor lo valora como útil | `Notification`, `Community`. **`Credits` ya no lo consume**: la bonificación automática se sustituyó por la propina | `correctionId`, `readerId`, `authorId` |
| `FeedbackReplied` | El autor contesta | `Notification` | `feedbackId`, `reviewerId` |
| `FeedbackHidden` | El autor lo oculta | `Community`, `Credits`* | `feedbackId`, `workId` |
| `WorkRated` | Un LB valora la obra | `Community` | `workId`, `authorId`, `readerId`, `rating` |

\* Solo si se decide revertir créditos al ocultar (`C-9`).

`FeedbackSubmitted` es el evento más importante del sistema: provoca a la vez el abono al
lector y la confirmación del cargo al autor. **Nunca transporta el texto de las respuestas.**

Ese texto es material privado entre lector y autor, y una cola con reintentos y colas de
fallos no es sitio para él. El evento dice **qué ha pasado**; quien necesite el contenido lo
pide a `Feedback` con autorización.

**Se publica al enviar, nunca al guardar un borrador** ([`FEAT-FBK-011`](../features/feedback/FEAT-FBK-011-save-correction-draft.md)).
Un borrador no es un hecho de negocio.

Tampoco lo es **comentar un capítulo**: eso es `ChapterCommented`, vive en `Community` y no
mueve créditos. Confundir ambos haría que cada comentario suelto cobrase al autor.

`chapterId` **es obligatorio**: la corrección es por capítulo (`R-2`), y es la longitud de
ese capítulo la que pesa en el precio junto con el cuestionario. Un evento sin `chapterId`
dejaría a `Credits` sin poder calcular nada.

`CorrectionDraftDiscarded` lleva además `workId` porque sus dos consumidores sueltan cosas de
distinto tamaño: `Credits` una cotización, que es de un capítulo, y `Reading` un acceso, que
es de la obra entera.

## `Credits`

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `CreditsAdded` | Se abonan créditos | `Notification` | `userId`, `amount`, `reason`, `balance`, `addedAt` |
| `CreditsSpent` | Se carga una corrección recibida | `Notification` | `userId`, `amount`, `reason`, `balance`, `spentAt` |
| `ChapterCorrectabilityChanged` | Un capítulo pasa a ser corregible o deja de serlo | **`Feedback`**, **`Work`** | `chapterId`, `workId`, `correctable`, `affordableCorrections`, `changedAt`. **Sin importes** |
| `ChapterPriceChanged` | Cambia lo que vale corregir un capítulo | **`Work`** (insignia del catálogo), `Community` | `chapterId`, `workId`, `credits`, `changedAt`. **Lleva importe, y es el único** |
| `CreditBalanceChanged` | Cambia el saldo | Read models, `Notification` | `userId`, `balance`, `changedAt` |
| `CreditBalanceWentNegative` | El saldo **cruza** a negativo | `Notification`, `Feedback` | `userId`, `balance`, `crossedAt` |
| `CreditDebtCleared` | Vuelve a cero o más | `Feedback`, `Notification` | `userId`, `balance` |
| `OverdraftCorrectionGranted` | Se concede un descubierto | `Feedback`, `Notification` | `holdId`, `userId`, `chapterId`, `amount` |
| `CorrectionUnlocked` | El autor repone saldo | `Feedback`, `Notification` | `userId`, `correctionId` |

**Ningún evento de `Credits` bloquea a otro contexto.** Como no se retiene nada, `Feedback`
abre el panel de corrección contra su propia proyección de `ChapterCorrectabilityChanged`, sin
esperar respuesta. Que la proyección vaya ligeramente retrasada solo puede producir un
descubierto, que es un caso aceptado.

`ChapterCorrectabilityChanged` **no lleva ni un saldo ni un precio**, y lleva dos cosas:

- `correctable`, que `Feedback` usa para abrir el panel sin preguntar nada;
- `affordableCorrections`, cuántas correcciones de ese capítulo puede pagar su autor **con
  tope de diez**, que es el primer factor de la ordenación del catálogo
  ([`decision:0008`](../decisions/0008-catalogue-ordering.md)).

`ChapterPriceChanged` es la excepción y conviene decir por qué no contradice a la anterior.
Lo que [`decision:0002`](../decisions/0002-credits-as-isolated-bounded-context.md) prohíbe es
que otro contexto **calcule** efectos de crédito, no que `Credits` publique lo que ya ha
decidido; `RN-1` de [`FEAT-CRD-013`](../features/credits/FEAT-CRD-013-work-credit-badge.md)
lo pide con todas las letras. La cifra viaja **ya traducida**, es un precio y no un saldo, y
quien la recibe solo puede copiarla.

El segundo es la conclusión de una aritmética cuyos sumandos no salen de aquí. Sin él, el
catálogo no podía ordenar por capacidad de pago sin que alguien le contase el saldo del autor
y el precio del capítulo; con él, ordena sin saber lo que cuesta nada. El tope hace además que
por encima de diez todos los autores se parezcan.

**Dice que el autor puede pagarlo y que queda hueco**, no que la obra esté abierta a
corrección: ese dato es de `Work`, y cada consumidor lo combina con lo que ya sabe.

Se publica **solo cuando la respuesta cambia**: el precio de un capítulo se recalcula muchas
más veces de las que su corregibilidad se mueve.

`CreditBalanceWentNegative` es el **cruce**, no el estado. Se publica en el movimiento que
hunde la cuenta y no otra vez mientras siga hundida: quien recibiera uno por cada cargo no
podría distinguir el instante del estado, y es el instante el que merece un aviso y el que
bloquea la corrección recién llegada.

`CreditsAdded` y `CreditsSpent` narran **un movimiento y su motivo**, que es de donde se
escribe un aviso a la persona; `CreditBalanceChanged` dice **cuál es la cifra ahora**, que es
lo que necesita un read model y lo único que se puede aplicar fuera de orden sin hacer daño.

`Credits` **nunca oculta ni enseña el texto de una corrección**. En el descubierto publica el
hecho económico; qué se ve lo decide `Feedback`, que es quien posee la corrección.

---

## Evolución de los contratos

| Cambio | ¿Compatible? | Qué hacer |
|---|---|---|
| Añadir un campo opcional | Sí | Publicar con la misma versión |
| Eliminar o renombrar un campo | No | Nueva `eventVersion`, ambas conviven durante la migración |
| Cambiar el tipo de un campo | No | Nueva `eventVersion` |
| Cambiar el significado sin cambiar el nombre | No | El peor caso: no rompe nada visiblemente y corrompe datos. Nueva versión y nombre distinto |
| Añadir un evento | Sí | Documentarlo aquí antes de publicarlo |
| Eliminar un evento | No | Confirmar que no queda ningún consumidor |

## Garantías

| Garantía | ¿Se da? |
|---|---|
| Entrega al menos una vez | Sí |
| Entrega exactamente una vez | **No.** Los handlers deben ser idempotentes |
| Orden de llegada | **No.** El diseño no puede depender del orden |
| Publicación atómica con el cambio en base de datos | Solo con Outbox Pattern. **Obligatorio** para los eventos que afectan a créditos |## `Moderation`

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `ClaimSubmitted` | Se presenta una reclamación | **`Notification`** (avisa a los moderadores) | `claimId`, `type`, `targetType`, `targetId`, `reporterId`, `reason`. **Sin el texto del reclamante** |
| `ClaimUpheld` | El moderador la estima | **`Credits`**, **`Work`**, **`User`**, `Notification` | `claimId`, `type`, `targetType`, `targetId`, `subjectId` |
| `ClaimRejected` | La desestima | `Notification` | `claimId`, `reporterId` |
| `ClaimMessageSent` | El moderador o una parte escribe | `Notification` | `claimId`, `thread`, `authorType`. **Sin el cuerpo del mensaje** |
| `ContentReviewPassed` | El revisor automático aprueba | **`Work`** | `workId`, `chapterId?`, `reviewerVersion` |
| `ContentReviewFlagged` | El revisor lo marca | **`Work`**, `Notification` | `workId`, `chapterId?`, `reason`, `reviewerVersion` |
| `SanctionImposed` | Se sanciona a un usuario | **`User`**, `Notification` | `sanctionId`, `userId`, `type`, `scope`, `expiresAt?` |
| `SanctionLifted` | Caduca o se levanta | `User`, `Notification` | `sanctionId`, `userId` |
| `CreditAdjustmentOrdered` | Ajuste manual desde el backoffice | **`Credits`**, `Notification` | `userId`, `amount`, `reason`, `orderedBy` |

**`ClaimUpheld` es el evento que más contextos moviliza**, y justo por eso no lleva
instrucciones: dice **qué se ha estimado y sobre qué**, nunca «devuelve 6 créditos» ni
«bloquea la obra». `Credits` revierte el movimiento, `Work` bloquea la obra y `User` aplica la
sanción, cada uno según su modelo.

Tampoco viajan **el texto del reclamante, la motivación del moderador ni el cuerpo de los
mensajes**. Son material que acusa a alguien y que va al expediente, no a una cola con
reintentos: `Notification` avisa de que hay algo que leer, no lo reproduce.

`CreditAdjustmentOrdered` es la única vía por la que entra crédito al sistema sin ser
transferencia ni grifo ordinario, así que **`Credits` lo contabiliza aparte** o la invariante
contable empezará a fallar sin explicación.

---


