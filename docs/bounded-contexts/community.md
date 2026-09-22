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
| `Post` | Publicaciones del muro, su intención y su formato |
| `Interaction` | Comentarios y respuestas, apoyos, reacciones, reposts, compartidos y menciones |
| `Subscription` | Seguimiento de autores, sus sugerencias y los listados de seguidos y seguidores |
| `Relationship` | Silenciados y bloqueados |
| `Recommendation` | Read models que alimentan la Home: obras recomendadas y autores sugeridos |
| `Messaging` | Mensajes directos y conversaciones |
| `Ranking` | Read models de escritores, obras y lectores |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `Post` | `PostId` | Tiene autor, tipo, formato y audiencia. Contiene sus comentarios, reacciones y apoyos. |
| `PostComment` | `PostCommentId` | De primer nivel o respuesta (`parentCommentId`). **Una respuesta nunca cuelga de otra respuesta**: se aplana al comentario raíz. |
| `Conversation` | `ConversationId` | Entre dos usuarios. Requiere que el destinatario acepte mensajes directos. |
| `AuthorSubscription` | `AuthorSubscriptionId` | Una por par (suscriptor, autor). Un usuario no se suscribe a sí mismo. |

### Enums

| Nombre | Valores |
|---|---|
| `PostType` | Intención: `GENERAL`, `LOOKING_FOR_BETA_READERS`, `LOOKING_FOR_WRITING_BUDDY`, `OFFERING_AS_BETA_READER` |
| `PostFormat` | Formato: `TEXT`, `IMAGE`, `VIDEO`, `LINK`, `WORK` |
| `PostAudience` | Audiencia: solo se conoce «cualquiera» (`C-1`) |
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
  **El diseño de la Home solo muestra el apoyo**, así que la coexistencia está en duda (`H-5`).
- `RN-5` `PostType`, `PostFormat` y `PostAudience` son dimensiones **independientes**: una
  publicación buscando lectores beta puede llevar cualquier formato y cualquier audiencia.
- `RN-7` El muro **filtra por audiencia en servidor**. No se sirve una publicación que el
  lector no debe ver confiando en que el cliente la oculte.
- `RN-8` Un repost **no amplía la audiencia** del original: quien no podía verlo sigue sin
  poder.
- `RN-9` **Mencionar a alguien no le da acceso a nada** y no se le avisa de contenido que no
  puede ver.
- `RN-11` **`Community` no revoca accesos de lector beta ni toca créditos** al bloquear:
  publica `UserBlocked` y cada contexto decide su reacción.
- `RN-10` Las menciones se guardan como `UserId`. Guardarlas como texto rompería al cambiar
  el nombre y, con el reciclado de nombres de usuario, podría señalar a otra persona.
- `RN-6` Este contexto **no consulta** las tablas de `Work`, `User` ni `Credits` para pintar
  la Home: mantiene proyecciones alimentadas por eventos de integración.

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
| CM-10 | ¿Qué compone el muro de quien no sigue a nadie? (`H-8`) | Define si es cronológico por seguidos o algorítmico |
| CM-11 | ¿Un repost se puede comentar de forma independiente del original? | Modelo de `Repost` (`C-3`) |
| CM-16 | ¿Qué opciones tiene el selector de audiencia? | **Bloqueante** para `FEAT-COM-029` |
| CM-17 | ¿Se admite vídeo en las publicaciones? | Transcodificación y coste de almacenamiento (`C-2`) |
| CM-18 | ¿Quién genera la previsualización de un enlace externo? | Si es el backend, hace peticiones a URLs que aporta el usuario |
| CM-19 | ¿Se confirma el anidamiento de comentarios a un solo nivel? | `I-2` |
| CM-20 | ¿Se puede mencionar escribiendo «@», o solo al responder? | `I-5`; si es lo primero, hace falta buscador de usuarios |
| CM-21 | ¿Se pueden editar o eliminar comentarios propios? | El menú «···» no tiene diseño (`I-3`) |
| CM-12 | ¿«Ocultar post» oculta ese post concreto o todos los de ese autor? | `FEAT-COM-022` |
| CM-13 | ¿Las publicaciones guardadas son privadas y dónde se consultan? | `FEAT-COM-021`, pantalla sin diseñar |
| CM-14 | ¿La lista de seguidores de un usuario es pública o solo la ve él? | `FEAT-COM-027` |
| CM-15 | ¿«Mis Amigos» es el nombre adecuado para una relación asimétrica? | Seguir no es ser amigo (`P-8`) |
