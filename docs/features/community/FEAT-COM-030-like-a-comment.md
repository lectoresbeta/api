---
id: FEAT-COM-030
title: «Me gusta» en un comentario o respuesta
context: Community
concept: Interaction
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/ui/post-interactions.md
  - conversation:2026-09-25
endpoints: [PUT /comments/{commentId}/like, DELETE /comments/{commentId}/like]
events: []
depends_on: [FEAT-COM-006, FEAT-COM-008]
updated: 2026-09-25
---

# FEAT-COM-030 — «Me gusta» en un comentario

## Resumen

Lo mismo que `FEAT-COM-008` sobre otro objetivo: un comentario del muro, o una respuesta a
uno.

Salió de las capturas de `post-interactions.md`, donde un comentario tiene sus propias
acciones —«Me gusta · `n` Me gusta · Responder · `n` respuestas»— con un contador
independiente del de la publicación. **No estaba documentado en ningún sitio.**

## Por qué es una ficha aparte y no un parámetro

Podría haberse hecho una sola tabla con un `targetType`. No se ha hecho, y conviene decir por
qué: un objetivo polimórfico obliga a que cada consulta sepa qué es cada fila, no admite
clave foránea, y convierte «borra los apoyos de esto» en una consulta que hay que acordarse
de filtrar.

Dos tablas con la misma forma cuestan una clase más y se leen solas. Y si algún día hay un
tercer objetivo, unirlas sigue siendo posible; separar una que ya mezcla, no.

## Cuidado con el nombre, otra vez

En `Feedback` la **valoración positiva de un comentario de corrección** (`FEAT-FBK-006`)
otorga créditos. Esto no. Son dos cosas con el mismo nombre en dos contextos distintos, y es
exactamente por lo que `Feedback` y `Community` están separados: el comentario de una
corrección es trabajo pagado, y el de una publicación es conversación.

## Reglas de negocio

Las mismas que `FEAT-COM-008`, sobre un comentario:

- `RN-1` Uno por persona y comentario, alternable e idempotente.
- `RN-2` Solo sobre comentarios de publicaciones que quien pulsa **puede ver**.
- `RN-3` El contador vive en el comentario.
- `RN-4` La tarjeta del comentario dice si quien mira lo ha apoyado.
- `RN-5` Vale igual para un comentario de primer nivel y para una respuesta: **son la misma
  entidad** con un `parentCommentId`, así que no hay nada que distinguir.
- `RN-6` Un comentario borrado no se puede apoyar, y borrarlo se lleva sus apoyos.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Apoyar | `PUT /api/v1/comments/{commentId}/like` | `likePostComment` |
| Retirar | `DELETE /api/v1/comments/{commentId}/like` | `unlikePostComment` |

Bajo `/comments/`, que es el prefijo que este contexto **ya usa** para editar, borrar y
responder comentarios: esto es una acción más sobre el mismo recurso. Los comentarios de
`Feedback` cuelgan de `/corrections/`, así que no hay ambigüedad que resolver con el
nombre.

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `post_comment_like` | `comment_id`, `member_id`, `liked_at`. Clave compuesta por el par |
| `post_comment` | `like_count`, nuevo |

## Criterios de aceptación

- [x] Apoyar un comentario sube su contador, y no el de la publicación.
- [x] Apoyar una respuesta funciona igual que apoyar un comentario.
- [x] Apoyar dos veces deja el contador en uno.
- [x] Retirar lo que no se puso no falla.
- [x] La lista de comentarios dice cuáles ha apoyado quien mira.
- [x] No se puede apoyar un comentario de una publicación que no se puede ver.
- [x] Un comentario borrado no se puede apoyar.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| CM-9 | ¿El «me gusta» pesa en el orden «Más relevantes» de los comentarios? | Es el mismo vacío que `I-1` y `CM-4`: sin fórmula de relevancia no hay consulta |

## Estado

**Especificación:** `APPROVED` (2026-09-25).

**Implementación:** `DONE` (2026-09-25).
