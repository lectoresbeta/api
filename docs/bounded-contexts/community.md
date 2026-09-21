# Bounded context: `Community`

> Estado: `DRAFT` — Prefijo: `COM`

## Responsabilidad

La vida social de la plataforma: el muro principal, las publicaciones y su interacción, el
seguimiento de autores, los mensajes directos y los rankings.

## Qué posee

- Muro principal y publicaciones.
- Comentarios, reacciones y apoyos a publicaciones.
- Filtrado y búsqueda de publicaciones.
- Suscripciones a autores.
- Mensajes directos y conversaciones.
- Rankings de escritores, obras y lectores.

## Qué NO posee

| No es suyo | Es de |
|---|---|
| Las obras que se promocionan en el muro | `Work` |
| El feedback sobre las obras | `Feedback` |
| El ajuste que habilita los mensajes directos | `User` (`Community` lo consume) |
| Los accesos de lector beta que resultan de una publicación | `Reading` |

## Conceptos

| Concepto | Responsabilidad |
|---|---|
| `Post` | Publicaciones del muro y su tipo |
| `Interaction` | Comentarios, reacciones y apoyos |
| `Subscription` | Seguimiento de autores |
| `Messaging` | Mensajes directos y conversaciones |
| `Ranking` | Read models de escritores, obras y lectores |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `Post` | `PostId` | Tiene autor y tipo. Contiene sus comentarios, reacciones y apoyos. |
| `Conversation` | `ConversationId` | Entre dos usuarios. Requiere que el destinatario acepte mensajes directos. |
| `AuthorSubscription` | `AuthorSubscriptionId` | Una por par (suscriptor, autor). Un usuario no se suscribe a sí mismo. |

### Enums

| Nombre | Valores |
|---|---|
| `PostType` | `GENERAL`, `LOOKING_FOR_BETA_READERS`, `LOOKING_FOR_WRITING_BUDDY`, `OFFERING_AS_BETA_READER` |
| `RankingType` | `WRITERS`, `WORKS`, `READERS` |
| `RankingPeriod` | Por definir (`CM-3`) |

## Rankings

Son **read models**, no agregados. Se alimentan de eventos de otros contextos:

| Ranking | Se alimenta de | Filtros |
|---|---|---|
| Escritores | `WorkRated`, `FeedbackSubmitted` | Temática, periodo |
| Obras | `WorkRated` | Temática, periodo |
| Lectores | `FeedbackSubmitted`, `FeedbackRatedPositively` | Periodo (pendiente de confirmar) |

El documento de partida deja abierta la pregunta de si el ranking de lectores se filtra por
periodo.

## Eventos publicados

| Evento | Cuándo | Consumidores |
|---|---|---|
| `PostPublished` | Se publica en el muro | `Notification` (suscriptores del autor) |
| `AuthorSubscribed` | Un usuario sigue a un autor | `Notification` |
| `DirectMessageSent` | Se envía un mensaje directo | `Notification` |

## Eventos consumidos

| Evento | Origen | Efecto |
|---|---|---|
| `WorkPublished` | `Work` | Avisa a los suscriptores; alimenta el catálogo del muro |
| `WorkRated` | `Feedback` | Actualiza rankings de obras y escritores |
| `FeedbackSubmitted`, `FeedbackRatedPositively` | `Feedback` | Actualiza el ranking de lectores |
| `UserProfileUpdated`, `UserDeleted` | `User` | Mantiene los read models |

## Reglas de negocio

- `RN-1` Un mensaje directo solo se entrega si el destinatario los tiene habilitados.
- `RN-2` Un usuario no se suscribe a sí mismo.
- `RN-3` Una reacción por usuario y publicación; cambiarla sustituye la anterior.
- `RN-4` El apoyo (`Like`) y la reacción con emoji son mecanismos distintos y coexisten.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| CM-1 | ¿Qué diferencia exactamente "reaccionar con emoji" de dar un "like"? El documento los lista por separado | Modelo de interacción |
| CM-2 | ¿Qué emojis están disponibles? ¿Catálogo cerrado? | Modelo de `Reaction` |
| CM-3 | ¿Qué periodos admiten los rankings: semana, mes, año, histórico? | Read models e índices |
| CM-4 | ¿Cómo se puntúa exactamente cada ranking? El documento dice "mejor valorados" sin definir la fórmula | **Bloqueante** para implementar rankings |
| CM-5 | ¿El ranking de lectores se filtra por periodo? | El documento lo deja abierto |
| CM-6 | ¿Los rankings se calculan en tiempo real o por proceso programado? (`D-5`) | Arquitectura del read model |
| CM-7 | ¿Los mensajes directos necesitan tiempo real (WebSocket)? | Podría justificar separar `Messaging` (`BC-1`) |
| CM-8 | ¿Se puede bloquear a un usuario concreto? | Modelo de mensajería |
| CM-9 | ¿Una publicación de tipo `LOOKING_FOR_BETA_READERS` enlaza con la obra y permite solicitar acceso desde ahí? | Integración con `Reading` |
