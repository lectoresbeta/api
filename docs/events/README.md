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
| `UserRegistered` | Se crea una cuenta | `Credits` (crea cuenta con saldo 0), `Notification` | `userId`, `authProvider`, `status`, `invitedBy?` |
| `AccountActivated` | El usuario activa su cuenta desde el correo | **`Credits`** (+20), `Notification`, `Feedback` | `userId`, `activatedAt` |
| `ActivationEmailRequested` | Se pide reenviar el correo de activación | `Notification` | `userId` |
| `LiteraryPreferencesUpdated` | El usuario fija o cambia sus géneros | `Community` | `userId`, `genres` |
| `OnboardingCompleted` | Termina el onboarding | `Notification`, read models | `userId`, `completedAt` |
| `UserProfileUpdated` | Cambian datos públicos | `Community` | `userId`, campos modificados |
| `UserDeleted` | Se elimina la cuenta | Todos | `userId`, `deletedAt` |
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
| `QuestionnaireUpdated` | Cambia el cuestionario | `Credits` | `workId`, `questionCount` |

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
| `FeedbackSubmitted` | Se envía un comentario | **`Credits`**, `Notification`, `Community` | `feedbackId`, `workId`, `chapterId?`, `authorId`, `reviewerId`, `betaReaderAccessId`, `textTier`, `questionCount`, `origin` |
| `FeedbackRatedPositively` | El autor lo valora como útil | **`Credits`**, `Notification`, `Community` | `feedbackId`, `reviewerId`, `authorId` |
| `FeedbackReplied` | El autor contesta | `Notification` | `feedbackId`, `reviewerId` |
| `FeedbackHidden` | El autor lo oculta | `Community`, `Credits`* | `feedbackId`, `workId` |
| `WorkRated` | Un LB valora la obra | `Community` | `workId`, `authorId`, `readerId`, `rating` |

\* Solo si se decide revertir créditos al ocultar (`C-9`).

`FeedbackSubmitted` es el evento más importante del sistema: provoca a la vez el abono al
comentarista y el cargo al autor. **Nunca transporta el texto del comentario.**

## `Credits`

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `CreditsAdded` | Se abonan créditos | `Notification` | `userId`, `amount`, `reason`, `balance` |
| `CreditsSpent` | Se confirma una retención | `Notification` | `userId`, `amount`, `reason`, `balance` |
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
| `PostPublished` | Se publica en el muro | `Notification` | `postId`, `authorId`, `type` |
| `AuthorSubscribed` | Un usuario sigue a un autor | `Notification` | `subscriberId`, `authorId` |
| `OnboardingAuthorSuggestionsShown` | *(opcional, analítica)* Se muestran sugerencias | — | `userId`, `suggestedAuthorIds` |
| `DirectMessageSent` | Se envía un mensaje | `Notification` | `conversationId`, `senderId`, `recipientId` |

`DirectMessageSent` **no transporta el contenido del mensaje**: la notificación avisa y
enlaza, no reproduce.

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
