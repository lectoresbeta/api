# Modelo de dominio (visión global)

> Estado: `DRAFT`. Modelo conceptual de alto nivel. **No es el modelo de datos.**
> El detalle de cada agregado vive en la ficha de su bounded context.

Este documento existe para ver las relaciones entre conceptos de un vistazo. La
implementación **no debe deducirse de aquí**: una relación dibujada entre dos contextos no
autoriza una clave foránea ni una consulta directa. Ver
[`../architecture/04-cross-context-communication.md`](../architecture/04-cross-context-communication.md).

---

## Mapa conceptual

```text
┌─ User ───────────────┐      ┌─ Work ───────────────────────┐
│  User                │      │  Work                        │
│  Credentials         │      │   └─ Chapter                 │
│  AuthorPage          │      │   └─ Questionnaire           │
│  AccountSettings     │      │        └─ QuestionnaireQuestion
│  PlatformInvitation  │      │   └─ AuthorshipRecord        │
└──────────────────────┘      │   └─ PublicLink              │
          │                   └──────────────────────────────┘
          │                                │
          │                                │ obra
          ▼                                ▼
┌─ Reading ────────────────────────────────────────────────┐
│  BetaReaderAccess   (usuario ── obra)                    │
│  AccessRequest      (solicitud del lector)               │
│  AccessInvitation   (invitación del autor)               │
│  BetaReaderGroup                                         │
│  WritingBuddyLink                                        │
└──────────────────────────────────────────────────────────┘
                       │ habilita
                       ▼
┌─ Feedback ───────────────────────────────────────────────┐
│  Feedback           (sobre un Chapter o un Work)         │
│   └─ QuestionnaireAnswer                                 │
│   └─ FeedbackReply                                       │
│   └─ FeedbackRating   (valoración del autor)             │
│  WorkRating                                              │
└──────────────────────────────────────────────────────────┘
                       │ hechos de negocio (eventos)
                       ▼
┌─ Credits ────────────────────────────────────────────────┐
│  CreditAccount  ── CreditBalance                         │
│  CreditTransaction  (inmutable)                          │
│  CreditRule                                              │
│  ProcessedEvent     (deduplicación)                      │
└──────────────────────────────────────────────────────────┘

┌─ Community ──────────────────┐   ┌─ Notification ──────────┐
│  Post ── PostComment         │   │  Notification           │
│       ── Reaction / Like     │   │  NotificationPreference  │
│  AuthorSubscription          │   │  DeliveryChannel        │
│  DirectMessage / Conversation│   └─────────────────────────┘
│  Ranking (read model)        │
└──────────────────────────────┘
```

---

## Agregados y su contexto propietario

| Agregado | Contexto | Identidad | Notas |
|---|---|---|---|
| `User` | `User` | `UserId` | Raíz de la cuenta. Referenciada por todos los demás contextos **solo por su identificador**. |
| `AuthorPage` | `User` | `UserId` | Perfil público y su personalización visual. |
| `Work` | `Work` | `WorkId` | Contiene sus `Chapter` y su `Questionnaire`. Frontera de consistencia del contenido. |
| `AuthorshipRecord` | `Work` | `AuthorshipRecordId` | Inmutable. Histórico por obra. |
| `PublicLink` | `Work` | `PublicLinkId` | Token de acceso sin sesión. |
| `BetaReaderAccess` | `Reading` | `BetaReaderAccessId` | Autoriza lectura y feedback de una obra. |
| `AccessRequest` | `Reading` | `AccessRequestId` | Ciclo de vida propio: `PENDING → ACCEPTED/REJECTED`. |
| `BetaReaderGroup` | `Reading` | `BetaReaderGroupId` | Agrupación gestionada por el autor. |
| `WritingBuddyLink` | `Reading` | `WritingBuddyLinkId` | Vínculo recíproco entre dos usuarios. |
| `Feedback` | `Feedback` | `FeedbackId` | Contiene respuestas al cuestionario, respuestas del autor y su valoración. |
| `WorkRating` | `Feedback` | `WorkRatingId` | Una por lector beta y obra. |
| `CreditAccount` | `Credits` | `UserId` | Saldo + historial. Frontera de consistencia de la economía. |
| `Post` | `Community` | `PostId` | Contiene comentarios y reacciones. |
| `Conversation` | `Community` | `ConversationId` | Mensajes directos entre dos usuarios. |
| `AuthorSubscription` | `Community` | `AuthorSubscriptionId` | Seguimiento de un autor. |
| `Notification` | `Notification` | `NotificationId` | Un aviso entregable por uno o varios canales. |

---

## Reglas estructurales

1. **Ningún agregado referencia un agregado de otro contexto por objeto.** Solo por
   identificador (`UserId`, `WorkId`), y ese identificador se trata como un valor opaco.
2. **Una obra pertenece a un único autor.** No hay coautoría en el alcance actual.
3. **Un fragmento pertenece a una única obra** y no existe fuera de ella.
4. **El feedback siempre se ancla a una obra y opcionalmente a un fragmento.** El alcance
   exacto (¿se comenta el fragmento, la obra, o ambos?) es una pregunta abierta: `D-1`.
5. **El acceso de lector beta se concede por obra**, no por autor ni por fragmento.
6. **`Credits` no conoce obras ni comentarios.** Solo conoce hechos de negocio que llegan
   como eventos de integración, y los traduce a movimientos según sus propias reglas.
7. **Los rankings son read models.** No son agregados: se derivan de eventos de otros
   contextos y se recalculan o se proyectan.

---

## Conceptos que cruzan contextos

Estos conceptos aparecen en varios contextos con **representaciones distintas y
deliberadamente independientes**:

| Concepto | En `User` | En `Work` | En `Reading` | En `Credits` | En `Community` |
|---|---|---|---|---|---|
| Usuario | Agregado `User` completo | `authorId: UserId` | `readerId`, `authorId` | `CreditAccount` por `UserId` | `authorId` del post |
| Obra | — | Agregado `Work` completo | `workId: WorkId` | No la conoce | `workId` referenciado en un post |
| Extensión del texto | — | `wordCount` y `TextTier` calculados | — | `TextTier` recibido en el evento | — |

Que el mismo nombre aparezca en dos columnas **no significa que se comparta la clase**.
Es exactamente lo que `AGENTS.md` prohíbe.

---

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| D-1 | ¿El feedback se deja sobre el fragmento, sobre la obra, o ambos? ¿Hay comentarios anclados a una posición del texto? | Define la raíz del agregado `Feedback` y el cálculo de créditos |
| D-2 | ¿Puede un lector beta dejar más de un comentario sobre la misma obra? ¿Cobra créditos por cada uno? | Riesgo de abuso de la economía de créditos |
| D-3 | ¿El cuestionario pertenece a la obra o a cada fragmento? | Afecta al coste por pregunta adicional |
| D-4 | ¿Se versiona el contenido de la obra al editarla, o se sobrescribe? | Afecta al registro de autoría y a la validez del feedback antiguo |
| D-5 | ¿Los rankings se calculan en tiempo real o por proceso programado? | Define si son read model proyectado o consulta agregada |
