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
| `AccountActivated` | El usuario activa su cuenta desde el correo | **`Credits`** (+20), `Notification`, `Feedback` | `userId`, `activatedAt` |
| `ActivationEmailRequested` | Se pide reenviar el correo de activación | `Notification` | `userId` |
| `LiteraryPreferencesUpdated` | El usuario fija o cambia sus géneros | `Community` | `userId`, `genres` |
| `OnboardingCompleted` | Termina el onboarding | `Notification`, read models | `userId`, `completedAt` |
| `UserProfileUpdated` | Cambian datos públicos | `Community` | `userId`, campos modificados |
| `UsernameChanged` | El usuario cambia su nombre de usuario | `Community` (read models con el `@`) | `userId`, `previousUsername`, `newUsername`, `aliasExpiresAt` |
| `EmailChangeRequested` | Se pide cambiar el correo | `Notification` | `userId`, `requestId`, `expiresAt` |
| `EmailChanged` | Se confirma el cambio de correo | `Notification`, read models | `userId`, `changedAt` |
| `PasswordChanged` | Se cambia la contraseña | `Notification` | `userId`, `changedAt` |
| `PrivacySettingsChanged` | Cambian los ajustes de privacidad | `Community`, read models | `userId`, ajustes modificados |
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

## `Work`

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `WorkCreated` | Se crea la obra | `Reading`, `Notification` | `workId`, `authorId`, `title`, `wordCount`, `textTier`, `accessMode`, `visibility` |
| `WorkPublished` | La obra se hace visible | `Reading`, `Community`, `Notification` | `workId`, `authorId`, `title`, `genre` |
| `WorkContentUpdated` | Cambia el contenido | `Feedback`, `Credits` | `workId`, `wordCount`, `textTier` |
| `WorkAccessModeChanged` | Cambia la modalidad | `Reading` | `workId`, `accessMode` |
| `WorkDeleted` | Se elimina | `Reading`, `Feedback`, `Community` | `workId`, `authorId` |
| `QuestionnaireUpdated` | Cambia el cuestionario | **`Credits`** | `workId`, `version`, `questionCount`, atributos que influyen en el precio (`P-1`) |

**Ningún evento de `Work` transporta el contenido de la obra.**

## `Reading`

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `AccessRequested` | Un usuario solicita ser LB | `Notification` | `accessRequestId`, `workId`, `authorId`, `readerId` |
| `AccessRequestRejected` | El autor rechaza | `Notification` | `accessRequestId`, `readerId` |
| `BetaReaderAccessGranted` | Se concede acceso | `Feedback`, `Notification`, **`Credits`** | `accessId`, `workId`, `authorId`, `readerId`, `grantedVia`, `textTier`, `questionCount` |
| `BetaReaderAccessRevoked` | Se retira el acceso | `Feedback`, `Notification` | `accessId`, `workId`, `readerId` |
| `BetaReaderInvited` | El autor invita | `Notification` | `invitationId`, `workId`, `readerId` |
| `WritingBuddyProposed` | Se propone el vínculo | `Notification` | `proposalId`, `proposerId`, `targetUserId` |
| `WritingBuddyLinked` | Se acepta | `Notification`, `Community` | `linkId`, `userIds` |

`BetaReaderAccessGranted` lleva `textTier` y `questionCount` porque `Credits` los necesita
para calcular la retención sin consultar a `Work`
([`decision:0004`](../decisions/0004-credit-reservation-on-access-grant.md)).

## `Feedback`

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `CorrectionStarted` | Un LB pulsa «Empezar corrección» | **`Credits`** (anota el precio), `Notification` | `chapterId`, `workId`, `authorId`, `readerId` |
| `FeedbackSubmitted` | Un LB **envía una corrección** de un capítulo | **`Credits`**, `Notification`, `Community` | `correctionId`, `workId`, **`chapterId`**, `authorId`, `readerId`, `questionnaireVersion`, `submittedAt` |
| `CorrectionDraftDiscarded` | El lector descarta su borrador | **`Credits`** (descarta la anotación) | `chapterId`, `readerId` |
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

## `Credits`

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `CreditsAdded` | Se abonan créditos | `Notification` | `userId`, `amount`, `reason`, `balance` |
| `CreditsSpent` | Se carga una corrección recibida | `Notification` | `userId`, `amount`, `reason`, `balance` |
| `ChapterCorrectabilityChanged` | Un capítulo pasa a ser corregible o deja de serlo | **`Feedback`**, `Work` | `chapterId`, `correctable`. **Sin importes** |
| `CreditBalanceChanged` | Cambia el saldo | Read models, `Notification` | `userId`, `balance` |
| `CreditBalanceWentNegative` | El saldo cruza a negativo | `Notification` | `userId`, `balance` |
| `CreditDebtCleared` | Vuelve a cero o más | `Feedback`, `Notification` | `userId`, `balance` |
| `OverdraftCorrectionGranted` | Se concede un descubierto | `Feedback`, `Notification` | `holdId`, `userId`, `chapterId`, `amount` |
| `CorrectionUnlocked` | El autor repone saldo | `Feedback`, `Notification` | `userId`, `correctionId` |

**Ningún evento de `Credits` bloquea a otro contexto.** Como no se retiene nada, `Feedback`
abre el panel de corrección contra su propia proyección de `ChapterCorrectabilityChanged`, sin
esperar respuesta. Que la proyección vaya ligeramente retrasada solo puede producir un
descubierto, que es un caso aceptado.

`ChapterCorrectabilityChanged` lleva **un booleano, no un importe**: ningún contexto ajeno
tiene por qué conocer saldos.

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


