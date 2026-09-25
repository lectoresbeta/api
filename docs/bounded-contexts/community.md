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
| `Interaction` | Comentarios y respuestas, apoyos y reposts — **sobre una publicación y sobre un capítulo** (`FEAT-COM-036`), que son entidades distintas porque heredan audiencias distintas |
| `Mention` | A quién se nombra, en una publicación o en un comentario. Concepto propio desde que se puede mencionar en los dos sitios: dentro de uno, el otro dependería de él |
| `Subscription` | Seguimiento de autores, sus sugerencias y los listados de seguidos y seguidores |
| `Relationship` | Silenciados y bloqueados |
| `Recommendation` | Read models que alimentan la Home y el onboarding: obras recomendadas y autores sugeridos |
| `EventProcessing` | El registro de hechos ya aplicados. Hace falta porque las proyecciones **acumulan**, y RabbitMQ no garantiza entrega única |
| `Messaging` | Mensajes directos y conversaciones |
| `Ranking` | Read models de escritores, obras y lectores |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `Post` | `PostId` | Tiene autor, tipo, formato y audiencia. Contiene sus comentarios, reacciones y apoyos. |
| `PostComment` | `PostCommentId` | De primer nivel o respuesta (`parentCommentId`). **Una respuesta nunca cuelga de otra respuesta**: se aplana al comentario raíz. |
| `ChapterComment` | `ChapterCommentId` | Lo mismo bajo el texto de un capítulo. **No es un `PostComment`** (`R-9`): aquel hereda la audiencia de su publicación y este la regla de lectura de la obra, que vive en `Work`. |
| `ChapterEngagement` | `ChapterId` | Los contadores sociales de un capítulo. No es una copia del capítulo: es lo de `Community` sobre él. |
| `Conversation` | `ConversationId` | Entre dos usuarios, **una por par**: el par se guarda ordenado, así que (A,B) y (B,A) son la misma fila. Abrirla requiere que el destinatario acepte mensajes directos; continuarla, no. |
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
| `PostCommented` | Se comenta o se responde | `Notification`. Lleva **a quién avisar**, no el texto |
| `UserMentioned` | Se nombra a alguien | `Notification`. **No se publica** si el mencionado no puede ver dónde se le menciona |
| `PostReposted` | Se vuelve a sacar una publicación | `Notification` (el autor original) |
| `AuthorSubscribed` | Un usuario sigue a un autor | **`User`** (proyección de audiencias `FOLLOWERS`), `Notification` |
| `AuthorUnsubscribed` | Un usuario deja de seguir a un autor | **`User`**. Nadie lo notifica: dejar de seguir es asunto de quien lo hace |
| `UserBlocked` | Un usuario bloquea a otro | **`User`**, **`Reading`**, y `Feedback`, `Credits` y `Notification` cuando existan |
| `UserUnblocked` | Se levanta el bloqueo | **`User`**. `Reading` no lo consume: un acceso revocado no vuelve solo |
| `DirectMessageSent` | Se envía un mensaje directo | `Notification` |
| `ChapterCommented` | Se comenta bajo el texto de un capítulo | `Notification`. **`Credits` no**: comentar no mueve créditos |

## Contratos publicados

| Contrato | Responde | Quién pregunta |
|---|---|---|
| `SubscriptionCounts` | **Dos cifras**: a cuántos sigue esa persona y cuántos la siguen. Nunca las listas | `User` |

El primero de este contexto, y existe para los contadores de la cabecera del perfil
(`FEAT-USR-028`). Las listas tienen su propio endpoint y sus propias reglas de visibilidad
(`FEAT-COM-027`); entregarlas aquí haría de la privacidad de cada seguidor un problema de
quien pregunta.

**Las cifras no se filtran por privacidad**, y es deliberado: hacerlo las volvería distintas
para cada visitante, con lo que un contador dejaría de ser un dato de esa persona para ser uno
de quien mira. La consecuencia es que el contador puede ser mayor que las filas que devuelve
la lista.

## Contratos consumidos

| Contrato | De quién | Para qué |
|---|---|---|
| `VisibleProfiles`, `ProfileCards` | `User` | Pintar quién dice cada cosa, y filtrar por privacidad de perfil |
| `ReadableChapters` | `Work` | Saber si alguien puede comentar un capítulo (`FEAT-COM-036`) |
| `WorkCards` | `Work` | Pintar la obra que cita una publicación, **viva** (`FEAT-COM-028`). Lo que no es visible para cualquiera no vuelve, así que la tarjeta se cae sola y este contexto no tiene que saber por qué |
| `MessageAudience` | `User` | Saber si alguien admite que le abran una conversación |

## Eventos consumidos

| Evento | Origen | Efecto |
|---|---|---|
| `WorkPublished` | `Work` | Avisa a los suscriptores; alimenta el catálogo del muro |
| `WorkRated` | `Feedback` | Actualiza rankings de obras y escritores |
| `FeedbackSubmitted`, `FeedbackRatedPositively` | `Feedback` | Actualiza el ranking de lectores |
| `UserProfileUpdated`, `UserDeleted` | `User` | Mantiene los read models |

## Reglas de negocio

- `RN-1` El ajuste de privacidad del destinatario gobierna **abrir** una conversación, no
  continuarla (`FEAT-COM-011` `RN-3`, `RN-4`). Endurecerlo no cierra los hilos abiertos: si lo
  hiciera, «prefiero que no me escriba cualquiera» significaría «desaparezco de conversaciones
  que estaba teniendo». Lo responde `User` por contrato (`MessageAudience`), con un booleano:
  este contexto no ve nunca el valor del ajuste.
- `RN-1b` Un **bloqueo** sí corta siempre, en los dos sentidos y esté la conversación abierta o
  no. Es la diferencia entre una preferencia y una regla de acceso contra una persona concreta.
- `RN-1c` El hecho que se publica al enviar **no lleva el cuerpo del mensaje**
  (`FEAT-COM-011` `RN-9`). Quien lo consume es el contexto que escribe correos.
- `RN-2` Un usuario no se suscribe a sí mismo.
- `RN-2b` Seguir y dejar de seguir son **idempotentes**, y seguir **no concede acceso a nada**
  (`FEAT-COM-010`). Lo que cambia son los avisos y qué audiencias incluyen a esa persona; qué
  audiencia eligió su titular no lo decide quien le sigue.
- `RN-2c` Las listas de seguidos y seguidores se ven **tanto como el perfil que las tiene**, y
  cada fila **tanto como la persona que la ocupa** (`FEAT-COM-027`, `CM-14` resuelta). Quién
  es visible lo decide `User` por contrato: este contexto no aprende qué hace visible a
  alguien.
- `RN-3` Una reacción por usuario y publicación; cambiarla sustituye la anterior.
- `RN-4` El apoyo (`Like`) y la reacción con emoji son mecanismos distintos y coexisten.
  **El diseño de la Home solo muestra el apoyo**, así que la coexistencia está en duda (`H-5`).
- `RN-5` `PostType`, `PostFormat` y `PostAudience` son dimensiones **independientes**: una
  publicación buscando lectores beta puede llevar cualquier formato y cualquier audiencia.
- `RN-5b` **`LOOKING_FOR_BETA_READERS` exige una obra** (`FEAT-COM-003`), propia y publicada.
  Es la única intención que impone algo, y no contradice a `RN-5`: lo que exige es el adjunto,
  no el formato ni la audiencia. Sin obra nadie puede atender la petición; con la de otro se
  estaría decidiendo por él a quién enseña su texto; con un borrador se manda a quien responda
  a una puerta cerrada.
- `RN-7` El muro **filtra por audiencia en servidor**. No se sirve una publicación que el
  lector no debe ver confiando en que el cliente la oculte.
- `RN-8` Un repost **no amplía la audiencia** del original: quien no podía verlo sigue sin
  poder.
- `RN-9` **Mencionar a alguien no le da acceso a nada** y no se le avisa de contenido que no
  puede ver.
- `RN-11` **`Community` no revoca accesos de lector beta ni toca créditos** al bloquear:
  publica `UserBlocked` y cada contexto decide su reacción. Lo único que hace por su cuenta es
  deshacer los dos seguimientos, que son suyos (`FEAT-COM-034` `RN-3`).
- `RN-10` Las menciones se guardan como `UserId`. Guardarlas como texto rompería al cambiar
  el nombre y, con el reciclado de nombres de usuario, podría señalar a otra persona.
- `RN-6` Este contexto **no consulta** las tablas de `Work`, `User` ni `Credits` para pintar
  la Home: mantiene proyecciones alimentadas por eventos de integración.
- `RN-12` **Comentar un capítulo no es corregirlo** (`FEAT-COM-036`). Un comentario es una
  reacción libre bajo el texto, caben las que sean y no mueve un crédito; la corrección es el
  cuestionario del autor, hay una por lector y capítulo, y sí los mueve (`FEAT-FBK-003`).
  Conviven en la misma pantalla y pertenecen a contextos distintos.
- `RN-13` **No se comenta ni se apoya lo que no se puede leer.** Quién puede leer un capítulo
  lo responde `Work` por contrato (`ReadableChapters`): la regla de lectura es la más
  peligrosa del backend y no se reconstruye aquí.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| CM-1 | ¿Qué diferencia exactamente "reaccionar con emoji" de dar un "like"? El documento los lista por separado | Modelo de interacción |
| CM-2 | ¿Qué emojis están disponibles? ¿Catálogo cerrado? | Modelo de `Reaction` |
| CM-3 | ¿Qué periodos admiten los rankings: semana, mes, año, histórico? | Read models e índices |
| CM-4 | ¿Cómo se puntúa cada **ranking**? El catálogo y los comentarios ya están resueltos ([`decision:0008`](../decisions/0008-catalogue-ordering.md) y `FEAT-COM-006`); faltan los rankings | Sin fórmula no hay clasificación |
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
| CM-14 | ¿La lista de seguidores de un usuario es pública o solo la ve él? | **Resuelta** en `FEAT-COM-027`: se ve tanto como el perfil que la tiene, y cada fila tanto como quien la ocupa |
| CM-15 | ¿«Mis Amigos» es el nombre adecuado para una relación asimétrica? | Seguir no es ser amigo (`P-8`) |
