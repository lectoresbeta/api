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

## Qué se consume de verdad

Un ✅ junto a un consumidor quiere decir que ese consumo **existe como código**. Sin él, la
fila es el contrato previsto y nadie lo escucha todavía.

La distinción importa al leer esta tabla: casi todos los hechos listan `Notification` como
consumidor, pero solo seis se convierten hoy en un aviso
([`FEAT-NOT-001`](../features/notification/FEAT-NOT-001-in-app-notifications.md)). Los de
`Credits` no, y no por olvido: son de grano fino —uno por movimiento— y merecen antes una
decisión sobre agrupación (`N-2`).

---

## `User`

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `UserRegistered` | Se crea una cuenta | `Credits` ✅ (crea cuenta con saldo 0), `Notification` ✅ | `userId`, `username`, `authProvider`, `status`, `invitedBy?` |
| `AccountActivated` | El usuario activa su cuenta desde el correo | **`Credits`** (+10), `Notification`, `Feedback` | `userId`, `activatedAt` |
| `ActivationEmailRequested` | Se pide reenviar el correo de activación | `Notification` ✅ | `userId`, `requestedAt`. **Ni el correo ni el token**: el token es una credencial viva y la dirección se obtiene por el contrato al enviar |
| `PasswordResetRequested` | Se pide el enlace de «he olvidado mi contraseña» (`FEAT-USR-007`) | `Notification` ✅ | `userId`, `requestedAt`. **Ni el correo ni el token**: el token es una credencial viva y se pide por contrato al enviar |
| `LiteraryPreferencesUpdated` | El usuario fija sus géneros en el onboarding **o los cambia después** (`FEAT-USR-009`) | `Community` | `userId`, `genres`. **La selección entera, no lo que cambió**: aplicar diferencias daría un conjunto equivocado el primer día que se pierda un mensaje |
| `OnboardingCompleted` | Termina el onboarding | `Notification`, read models | `userId`, `completedAt` |
| `UserProfileUpdated` | Cambian datos públicos, **la foto incluida** | `Community` | `userId`, `name`, `description`, `avatarUrl`, `updatedAt`. **Los valores nuevos, no un diff**: quien lo consume quiere con qué quedarse. `avatarUrl` es la recortada, **nunca la original**, y viaja como dirección y no como clave: un consumidor no tiene por qué aprender cómo se construye una URL de este sistema |
| `UsernameChanged` | El usuario cambia su nombre de usuario, **o recupera uno suyo** | `Community` (read models con el `@`) | `userId`, `previousUsername`, `newUsername`, `aliasExpiresAt`, `changedAt`. El viejo para encontrar qué actualizar, el nuevo para escribirlo, y la fecha para saber hasta cuándo un enlace antiguo sigue llevando a alguna parte |
| `EmailChangeRequested` | Se pide cambiar el correo | `Notification` ✅ | `userId`, `requestId`, `requestedAt`. **Ninguna de las dos direcciones viaja**: se piden por contrato al enviar |
| `EmailChanged` | Se confirma el cambio de correo | `Notification`, read models | `userId`, `changedAt`. Sin la dirección: una copia guardada por otro contexto es una copia que envejece |
| `PasswordChanged` | Se cambia la contraseña, **desde dentro o restableciéndola** | `Notification` ✅ | `userId`, `viaReset`, `changedAt`. **Nunca la contraseña ni su hash**. `viaReset` no decide si se avisa, decide qué dice el aviso |
| `PrivacySettingsChanged` | Cambian los ajustes de privacidad | `Community`, read models de visibilidad | `userId`, `profileVisibility`, `commentPermission`, `messagePermission`, `changedAt`. **Nada del perfil** |
| `NotificationPreferencesChanged` | Cambian las preferencias de aviso | **Nadie, hoy**: `Notification` las **pregunta al entregar** y no las proyecta, que es lo que hace que un cambio surta efecto en el acto. Se publica como traza de qué cambió y cuándo | `userId`, `changedTopics`, `allMuted?`, `changedAt`. **Nunca la configuración entera**: los ajustes de una persona no viajan por una cola para decir que cambiaron |
| `ReactivationOfferChoiceChanged` | Alguien decide si acepta el gancho de reactivación (`FEAT-CRD-019` `RN-2d`, `RN-8`) | **`Credits`** ✅ (deja de seleccionarlo) | `userId`, `accepted`, `changedAt`. La respuesta **efectiva**, con el interruptor general ya aplicado |
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
| `WorkPublished` | La obra pasa a `PUBLISHED`, **y solo la primera vez** (`FEAT-WRK-016`): pedir el estado que ya se tiene no anuncia nada | `Reading`, `Community`, `Notification` ✅ (avisa a los seguidores) | `workId`, `authorId`, `title`, `wordCount`, `chapterCount`, `genres`, `publishedAt` |
| `WorkOpenedForCorrection` | La obra pasa a `IN_CORRECTION` | `Reading`, **`Credits`** | `workId`, `authorId`, `openedAt` |
| `WorkClosedForCorrection` | La obra vuelve a `PUBLISHED` | `Reading`, **`Credits`** | `workId`, `authorId`, `closedAt` |
| `ChapterContentUpdated` | Cambia el texto de un capítulo | `Feedback`, **`Credits`** ✅ | `chapterId`, `workId`, `authorId`, `position`, `wordCount`, `version`, `updatedAt`. `version` la añade `FEAT-WRK-005` |
| `WorkAccessModeChanged` | Cambia la modalidad | `Reading` | `workId`, `authorId`, `accessMode`, `changedAt` |
| `WorkUpdated` | Cambian el título o la sinopsis (`FEAT-WRK-005`) | `Community`, `Reading` | `workId`, `authorId`, `updatedAt`. **Sin el texto** |
| `ChaptersReordered` | Cambia el orden (`FEAT-WRK-003`) | **`Credits`** ✅ | `workId`, `authorId`, `order`. Importa porque el último capítulo puede ser otro, y con él las preguntas que se cobran |
| `ChapterRemoved` | Se elimina un capítulo, de los que nadie corrigió | **`Credits`** ✅, `Feedback` | `chapterId`, `workId`, `authorId`, `order` (el que queda) |
| `ChapterVisibilityChanged` | El autor oculta o muestra un capítulo (`FEAT-WRK-008`) | **`Feedback`** ✅ (avisa a quien estaba corrigiendo), `Credits` |  `chapterId`, `workId`, `authorId`, `visibility`, `changedAt` |
| `WorkArchived` | El autor retira la obra (`FEAT-WRK-006`) | **`Reading`** ✅ (revoca los accesos), **`Feedback`** ✅ (avisa a quien estaba corrigiendo), `Credits`, `Community`, `Notification` | `workId`, `authorId`, `archivedAt` |
| `WorkRestored` | La recupera | Los mismos | `workId`, `authorId`, `restoredAt` |
| `WorkDeleted` | Se borra de verdad | `Reading`, `Feedback`, `Community` | `workId`, `authorId`. **Borrado definitivo**, no el botón de «Eliminar»: lo publica `FEAT-USR-013` |
| `WorkBlockedByModeration` | Se bloquea la obra o uno de sus capítulos tras una reclamación estimada (`FEAT-MOD-003`) | `Notification` ✅, **`Feedback`** ✅ (avisa a quien estaba corrigiendo), `Community`, **`Credits`** | `workId`, `authorId`, `title`, `scope`, `chapterId?`, `reason`, `blockedAt` |
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
| `AccessRequested` | Un usuario solicita ser LB | `Notification` ✅ | `accessRequestId`, `workId`, `authorId`, `readerId` |
| `AccessRequestRejected` | El autor rechaza | `Notification` ✅ | `accessRequestId`, `workId`, `readerId`. **Sin el autor**: quien rechaza no tiene por qué dar la cara |
| `BetaReaderAccessGranted` | Se concede acceso | `Notification` ✅ | `accessId`, `workId`, `authorId`, `readerId`, `grantedVia`, `grantedAt` |
| `BetaReaderAccessRevoked` | Se retira el acceso: al **descartar** un borrador, al **bloquear** a alguien o porque **el autor lo revoca** (`FEAT-RDG-010`) | `Notification` ✅ | `accessId`, `workId`, `readerId`, `revokedAt` |
| `BetaReaderInvited` | El autor invita | `Notification` ✅ | `invitationId`, `workId`, `authorId`, `readerId` |
| `BetaReaderInvitationDeclined` | El invitado rechaza | `Notification` | `invitationId`, `workId`, `authorId`, `readerId` |
| `WritingBuddyProposed` | Se propone el vínculo | `Notification` | `proposalId`, `proposerId`, `targetUserId` |
| `WritingBuddyLinked` | Se acepta | `Notification`, `Community` | `linkId`, `userIds` |

Los tres caminos publican **el mismo hecho**, y a propósito: quien lo consume no tiene por qué
saber cuál fue. Lo que le importa es que esa persona ya no puede leer esa obra.

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
| `CorrectionStarted` | Un LB pulsa «Empezar corrección» | **`Credits`** (anota el precio), **`Work`** ✅ (marca esa versión del capítulo como leída, `FEAT-WRK-005`), `Notification` | `chapterId`, `workId`, `authorId`, `readerId`, `startedAt` |
| `CorrectionResumed` | Un LB vuelve a abrir el panel de una corrección **que ya tenía empezada** | **`Reading`** ✅ (concede el acceso si no lo tiene). **`Credits` NO lo consume**: no toma hueco ni fija precio, porque las dos cosas ocurrieron al empezar | `correctionId`, `chapterId`, `workId`, `authorId`, `readerId`, `resumedAt` |
| `FeedbackSubmitted` | Un LB **envía una corrección** de un capítulo | **`Credits`**, `Notification` ✅, `Community` | `correctionId`, `workId`, **`chapterId`**, `authorId`, `readerId`, `questionnaireVersion`, `submittedAt` |
| `CorrectionDraftDiscarded` | El lector descarta su borrador | **`Credits`** (descarta la anotación), **`Reading`** (revoca el acceso) | `chapterId`, `workId`, `readerId`, `discardedAt` |
| `PublicCorrectionSubmitted` | Corrección por enlace público | `Notification` ✅. **`Credits` NO lo consume** | `correctionId`, `workId`, `chapterId`, `authorId`, `authorLabel?`, `submittedAt`. **Sin `readerId`**: eso es lo que lo distingue de `FeedbackSubmitted` |
| `FeedbackRatedPositively` | El autor valora una corrección como útil, **la primera vez** (`FEAT-FBK-006`) | `Notification` ✅, `Community`. **`Credits` ya no lo consume**: la bonificación automática se sustituyó por la propina | `correctionId`, `chapterId`, `workId`, `readerId`, `ratedAt`. Cambiar la valoración después **no publica nada**: avisar a alguien de que su corrección ha dejado de ser útil es una crueldad sin función |
| `FeedbackReplied` | El autor contesta a una corrección, **la primera vez** (`FEAT-FBK-005`) | `Notification` ✅ | `correctionId`, `chapterId`, `workId`, `readerId`, `repliedAt`. **Sin el texto** |
| `CorrectionRead` | El autor abre una corrección recibida (`FEAT-FBK-004`) | `Notification` ✅, que retira el aviso pendiente | `correctionId`, `authorId`, `readAt`. **`authorId` se llamó `readerId` hasta `FEAT-NOT-006`** llevando dentro el identificador del autor; el consumidor acepta los dos nombres mientras pueda quedar algo del anterior en la cola |
| `CorrectionClosed` | Lo que alguien estaba corrigiendo deja de estar disponible: la obra se bloquea, su autor la retira o le oculta el capítulo | `Notification` ✅ | `correctionId`, `chapterId`, `workId`, `readerId`, `reason`, `closedAt`. **El borrador no se borra**: es texto suyo | `correctionId`, `readerId`, `readAt`. **No se le dice a quien corrigió**: sería una confirmación de lectura entre dos personas que no han elegido conversar |
| `FeedbackHidden` | El autor lo oculta | `Community`, `Credits`* | `feedbackId`, `workId` |
| `WorkRated` | Un LB valora la obra (`FEAT-FBK-002`) | `Community` | `workId`, `authorId`, `readerId`, `rating` (1–5, fijado por el modelo) |

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
| `CreditsAdded` | Se abonan créditos | `Notification`, **`Feedback`** ✅ (proyecta lo ganado por cada corrección, `FEAT-FBK-010`) | `userId`, `amount`, `reason`, `balance`, `correctionId?`, `addedAt` |
| `CreditsSpent` | Se carga una corrección recibida | `Notification`, **`Feedback`** ✅ (retira lo ganado cuando una reclamación estimada lo revierte) | `userId`, `amount`, `reason`, `balance`, `correctionId?`, `spentAt` |
| `ChapterCorrectabilityChanged` | Un capítulo pasa a ser corregible o deja de serlo | **`Feedback`**, **`Work`** | `chapterId`, `workId`, `correctable`, `affordableCorrections`, `changedAt`. **Sin importes** |
| `ChapterPriceChanged` | Cambia lo que vale corregir un capítulo | **`Work`** (insignia del catálogo), `Community` | `chapterId`, `workId`, `credits`, `changedAt`. **Lleva importe, y es el único** |
| `CreditBalanceChanged` | Cambia el saldo | `User` ✅ (copia el número para el menú lateral), `Notification` | `userId`, `balance`, `changedAt` |
| `CreditBalanceWentNegative` | El saldo **cruza** a negativo | `Notification` ✅, `Feedback` ✅ | `userId`, `balance`, `crossedAt`, `subjectId`. Lo último dice de qué era el movimiento: es lo que permite bloquear esa corrección y no las ya leídas |
| `CreditDebtCleared` | Vuelve a cero o más | `Feedback` ✅, `Notification` ✅ | `userId`, `balance`, `clearedAt`. Desbloquea **todas** las correcciones retenidas a la vez |
| `OverdraftCorrectionGranted` | Alguien **ha corregido** un capítulo que su autor no podía pagar (`FEAT-CRD-019`) | `Notification` ✅ | `authorId`, `readerId`, `correctionId`, `chapterId`, `workId`, `amount`, `creditsNeeded`, `grantedAt` |
| `CorrectionTipped` | El autor propina una corrección recibida | **`Feedback`** ✅ (la corrección muestra que fue propinada), `Community` ✅ (reputación del corrector), `Notification` | `correctionId`, `authorId`, `readerId`, `amount`, `tippedAt` |

`OverdraftCorrectionGranted` dice **que se ha usado un descubierto**, no que se haya
concedido la elegibilidad: conceder no mueve un crédito, y anunciarlo produciría un correo
por algo que puede no llegar a ocurrir nunca. `Feedback` **no lo consume**, aunque la ficha lo
propusiera: el bloqueo de esa corrección ya lo dispara `CreditBalanceWentNegative`, y dos
hechos que bloquean lo mismo son dos verdades sobre lo mismo. Por lo mismo no existe
`CorrectionUnlocked`: `CreditDebtCleared` ya desbloquea.

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

Los dos llevan `correctionId` cuando el movimiento cita una corrección. Es una **referencia**,
no un dato de nadie, y permite a `Feedback` decirle a quien corrigió cuánto ganó sin
preguntarle nada a `Credits`, que no responde preguntas de nadie. Es el mismo dato que
`CreditBalanceWentNegative` usa desde `FEAT-CRD-018`, leído de los metadatos del movimiento.

`CreditsAdded` y `CreditsSpent` narran **un movimiento y su motivo**, que es de donde se
escribe un aviso a la persona; `CreditBalanceChanged` dice **cuál es la cifra ahora**, que es
lo que necesita un read model y lo único que se puede aplicar fuera de orden sin hacer daño.

`Credits` **nunca oculta ni enseña el texto de una corrección**. En el descubierto publica el
hecho económico; qué se ve lo decide `Feedback`, que es quien posee la corrección.

---

## `Community`

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `AuthorSubscribed` | Alguien empieza a seguir a un autor | **`User`** (proyección de audiencias), **`Community`** (sugerencias), **`Notification`** ✅ (a quién avisar de una obra nueva) | `subscriberId`, `authorId`, `subscribedAt` |
| `AuthorUnsubscribed` | Alguien deja de seguir a un autor | **`User`**, **`Community`**, **`Notification`** ✅ | `subscriberId`, `authorId`, `unsubscribedAt` |
| `UserBlocked` | Alguien bloquea a alguien | **`User`** (deja de aceptar comentarios entre ambos), **`Reading`** (retira el acceso de lector beta), y `Feedback`, `Credits` y `Notification` cuando existan | `blockerId`, `blockedId`, `blockedAt` |
| `UserUnblocked` | Se levanta un bloqueo | **`User`**. `Reading` **no** lo consume: devolver un acceso revocado es una decisión del autor, no un efecto secundario | `blockerId`, `blockedId`, `unblockedAt` |
| `DirectMessageSent` | Alguien le escribe a alguien (`FEAT-COM-011`) | **`Notification`** ✅ (`DIRECT_MESSAGE_RECEIVED`) | `conversationId`, `senderId`, `recipientId`, `sentAt`. **Sin el cuerpo, ni una línea** |

Los dos llevan **los dos identificadores y nada más**. Ni nombres ni perfiles: quien los
consume tiene su propia copia de las personas, y copiar un nombre aquí solo añadiría un sitio
donde envejece.

`AuthorUnsubscribed` existe porque la otra mitad no basta, y es la que se olvida. Quien
proyecta el grafo lo necesita para no quedarse con una copia que envejece **hacia el lado
peligroso**: alguien contando como seguidor —y por tanto dentro de una audiencia
`FOLLOWERS`— después de haberse ido.

`UserBlocked` es el ejemplo más claro de por qué un hecho no lleva instrucciones: dice que dos
personas han dejado de hablarse, y **cada contexto decide qué significa eso en su modelo**.
`Community` no sabe qué es un acceso de lector beta ni un crédito, y no tiene por qué
aprenderlo para que bloquear funcione.

Al deshacer los seguimientos, el bloqueo publica además un `AuthorUnsubscribed` por cada uno.
Quien proyecta el grafo se entera de lo que le importa —esa relación ya no está— sin aprender
que detrás había un bloqueo.

`DirectMessageSent` **no lleva el mensaje**, y no es una precaución genérica. Quien lo
consume es el contexto que escribe correos: un hecho que trajera el cuerpo sacaría una
conversación privada de la plataforma por un canal que su remitente no eligió —una cola que
persiste, reintenta y aparca mensajes— y acabaría en un buzón de correo el día que alguien
clasificara ese aviso como de los que salen por ahí. Lo que viaja es quién escribió y a qué
conversación entrar, que es todo lo que hace falta para pintar el aviso y su enlace.

Que `User` consuma estos dos hechos es lo que hace verdad `FOLLOWERS` en
[`FEAT-USR-038`](../features/user/FEAT-USR-038-privacy-settings.md) sin que `User` llame a
`Community`: `CheckAuthorAudience` es un contrato publicado, y un contrato no llama al de
otro contexto mientras responde ([`decision:0014`](../decisions/0014-published-contracts-between-contexts.md),
regla 4). Nadie notifica el segundo: dejar de seguir es asunto de quien lo hace, y avisar al
autor convertiría una acción discreta en un desaire con acuse de recibo.

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
| `ClaimUpheld` | El moderador la estima | **`Credits`** ✅ (revierte lo cobrado), **`Work`** ✅ (bloquea lo reclamado), **`User`**, `Notification` | `claimId`, `type`, `targetType`, `targetId`, `subjectId` |
| `ClaimRejected` | La desestima | `Notification` | `claimId`, `reporterId` |
| `ModerationBlockLifted` | Un moderador levanta un bloqueo (`FEAT-MOD-003` `RN-7`) | **`Work`** ✅ (lo ejecuta, igual que el bloqueo) | `targetType`, `targetId`, `liftedAt`. **Sin la motivación**: va al registro de auditoría |
| `ClaimMessageSent` | El moderador o una parte escribe | `Notification` | `claimId`, `thread`, `authorType`. **Sin el cuerpo del mensaje** |
| `ContentReviewPassed` | El revisor automático aprueba | **Nadie, hoy**: el texto ya está donde tiene que estar. Se publica como hecho auditable | `chapterId`, `workId`, `reviewerVersion`, `reviewedAt` |
| `ContentReviewFlagged` | El revisor lo marca | **`Work`** ✅ (lo retira por el bloqueo de moderación), `Notification` | `chapterId`, `workId`, `authorId`, `reason`, `reviewerVersion`, `reviewedAt`. **Sin una palabra del texto** |
| `SanctionImposed` | Se sanciona a un usuario | **`User`**, `Notification` | `sanctionId`, `userId`, `type`, `scope`, `expiresAt?` |
| `SanctionLifted` | Caduca o se levanta | `User`, `Notification` | `sanctionId`, `userId` |
| `CreditAdjustmentOrdered` | Ajuste manual desde el backoffice | **`Credits`** ✅, `Notification` | `userId`, `amount`, `reason`, `orderedBy`, `claimId?`, `orderedAt`. **Lleva importe, y es la excepción que confirma la regla**: el importe viaja cuando es genuinamente parte del hecho de origen, y aquí decidirlo *era* el acto |

**`ClaimUpheld` es el evento que más contextos moviliza**, y justo por eso no lleva
instrucciones: dice **qué se ha estimado y sobre qué**, nunca «devuelve 6 créditos» ni
«bloquea la obra». `Credits` revierte el movimiento, `Work` bloquea la obra y `User` aplica la
sanción, cada uno según su modelo.

Tampoco viajan **el texto del reclamante, la motivación del moderador ni el cuerpo de los
mensajes**. Son material que acusa a alguien y que va al expediente, no a una cola con
reintentos: `Notification` avisa de que hay algo que leer, no lo reproduce.

`WorkBlockedByModeration` lo publica **`Work`**, no `Moderation`: el bloqueo es un cambio en
el ciclo de vida de la obra, y ese es su modelo. Lleva el **título** porque el correo que
avisa al autor tiene que decirle qué se ha bloqueado, y un identificador no se lo dice; lleva
el **tipo de la reclamación** como motivo, nunca la motivación del moderador. La dirección a
la que recurrir **no viaja**: es configuración, y en un evento quedaría congelada en cada
mensaje que estuviera esperando en la cola.

`CreditAdjustmentOrdered` es la única vía por la que entra crédito al sistema sin ser
transferencia ni grifo ordinario, así que **`Credits` lo contabiliza aparte** o la invariante
contable empezará a fallar sin explicación.

---


