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
| `FeedbackSubmitted` | Un LB **envía una corrección** de un capítulo | **`Credits`**, `Notification`, `Community` | `correctionId`, `workId`, **`chapterId`**, `authorId`, `readerId`, `betaReaderAccessId`, `questionnaireVersion`, `submittedAt` |
| `FeedbackRatedPositively` | El autor lo valora como útil | **`Credits`**, `Notification`, `Community` | `feedbackId`, `reviewerId`, `authorId` |
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
| `CreditsAdded` | Se abonan créditos | `Notification` | `userId`, `amount`, `reason`, `balance`, `pricingRuleVersion?` |
| `CreditsSpent` | Se confirma una retención | `Notification` | `userId`, `amount`, `reason`, `balance`, `pricingRuleVersion` |
| `CreditsReserved` | Se retiene el coste de un feedback | `Notification` | `reservationId`, `userId`, `workId`, `amount`, `availableBalance` |
| `CreditReservationRejected` | No hay saldo disponible | **`Reading`**, `Notification` | `userId`, `workId`, `betaReaderAccessId`, `required`, `available` |
| `CreditReservationReleased` | Se libera una retención | `Notification` | `reservationId`, `userId`, `amount`, `reason` |
| `CreditBalanceChanged` | Cambia el saldo o el retenido | **`Reading`**, read models, `Notification` | `userId`, `balance`, `held`, `available` |
| `InsufficientCredits` | Llega un hecho sin retención que lo respalde | `Feedback`, `Notification` | `userId`, `required`, `available`, `sourceEventId` |

`CreditReservationRejected` es el único evento del catálogo del que **otro contexto depende
para deshacer algo**: `Reading` revoca con él un acceso ya concedido. Perderlo deja el
sistema en un estado incorrecto, así que su publicación exige **Outbox Pattern**.

`Credits` publica hechos de su propio modelo. Los consumidores **nunca** dependen de sus
entidades ni de sus repositorios.

## `Community`

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `PostPublished` | Se publica en el muro | `Notification` | `postId`, `authorId`, `type`, `format`, `audience` |
| `PostCommented` | Se comenta una publicación | `Notification` | `postId`, `commentId`, `postAuthorId`, `commentAuthorId` |
| `PostReposted` | Se repostea una publicación | `Notification` | `postId`, `originalAuthorId`, `repostedBy` |
| `UserMentioned` | Se menciona a alguien en un comentario | `Notification` | `mentionedUserId`, `byUserId`, `postId`, `commentId` |
| `AuthorSubscribed` | Un usuario sigue a un autor | `Notification` | `subscriberId`, `authorId` |
| `UserBlocked` | Un usuario bloquea a otro | `Reading`, `Feedback`, `Credits`, `Notification` | `blockerId`, `blockedId` |
| `UserUnblocked` | Se deshace el bloqueo | Los mismos | `blockerId`, `blockedId` |
| `OnboardingAuthorSuggestionsShown` | *(opcional, analítica)* Se muestran sugerencias | — | `userId`, `suggestedAuthorIds` |
| `DirectMessageSent` | Se envía un mensaje | `Notification` | `conversationId`, `senderId`, `recipientId` |
| `ChapterCommented` | Se comenta un capítulo | `Notification` | `chapterId`, `workId`, `commentId`, `authorId`, `commentAuthorId` |
| `ChapterLiked` | Se da «me gusta» a un capítulo | `Notification` | `chapterId`, `workId`, `authorId`, `byUserId` |

`DirectMessageSent` **no transporta el contenido del mensaje**: la notificación avisa y
enlaza, no reproduce.

`PostPublished` y `UserMentioned` llevan la audiencia o dependen de ella: `Notification`
comprueba que el destinatario puede ver el contenido **antes** de avisarle. Un aviso sobre
algo que no se puede abrir es, además de inútil, una filtración.

## `Notification`

No publica eventos de integración. Es un contexto terminal.

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
| Publicación atómica con el cambio en base de datos | Solo con Outbox Pattern. **Obligatorio** para los eventos que afectan a créditos |
