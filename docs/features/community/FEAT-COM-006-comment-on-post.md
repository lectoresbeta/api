---
id: FEAT-COM-006
title: Comentar una publicación
context: Community
concept: Interaction
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P2
sources:
  - _sources/use-cases.pdf#p3
  - conversation:2026-09-22 (secuencia de interacciones)
  - docs/ui/post-interactions.md
endpoints: [GET /posts/{postId}/comments, POST /posts/{postId}/comments]
events: [PostCommented]
depends_on: [FEAT-COM-002]
updated: 2026-09-22
---

# FEAT-COM-006 — Comentar una publicación

## Resumen

Bajo cada publicación hay un compositor —«Añadir un comentario…» con selector de emoji— y la
lista de comentarios, ordenable.

> **No confundir con `Feedback`.** Un `PostComment` es un comentario social sobre una
> publicación del muro. Un `Feedback` es la crítica de un lector beta sobre una obra, mueve
> créditos y tiene reglas de acceso propias. Son conceptos de contextos distintos que la
> interfaz llama parecido.

## Reglas de negocio

- `RN-1` Solo puede comentar quien **puede ver la publicación**. Si su audiencia le excluye,
  la acción se rechaza (`FEAT-COM-029`).
- `RN-2` El autor del comentario es el usuario autenticado.
- `RN-3` El texto se sanea; los emojis se conservan.
- `RN-4` Comentar exige la cuenta activada (`FEAT-USR-025`).
- `RN-5` Comentar una publicación **no mueve créditos**. Los créditos solo se mueven con el
  feedback sobre obras.
- `RN-6` Eliminar la publicación elimina sus comentarios.
- `RN-7` El contador de comentarios de la publicación incluye **las respuestas**, o no; ver
  `I-8`.
- `RN-8` Se publica `PostCommented`, que `Notification` usa para avisar al autor de la
  publicación.

`RN-1` es la que hay que probar: con audiencias distintas de «cualquiera», comentar es otra
vía por la que alguien podría tocar contenido que no debería ver.

## Ordenación

El desplegable muestra **«Más relevantes»** como valor por defecto.

Eso implica una fórmula de relevancia que **nadie ha definido**. Es el mismo vacío que
bloquea los rankings (`CM-4`) y la ordenación del muro (`FEAT-COM-024`), y conviene
resolverlo una sola vez para los tres.

Hasta entonces, lo implementable es la ordenación por fecha. Ver `I-1`.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Listar comentarios | `GET /posts/{postId}/comments` | `listPostComments` |
| Comentar | `POST /posts/{postId}/comments` | `createPostComment` |

El listado se pagina y admite el criterio de orden. Cada entrada incluye sus acciones —«me
gusta» propio y ajeno, número de respuestas— para que el cliente no tenga que pedirlas
aparte.

## Eventos

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `PostCommented` | Se publica un comentario | `Notification` | `postId`, `commentId`, `postAuthorId`, `commentAuthorId` |

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `post_comment` | `post_id`, `author_id`, `parent_comment_id` (nulo si es de primer nivel), texto, fecha |

Índices: `post_comment(post_id, created_at)` y `post_comment(parent_comment_id)`.

`parent_comment_id` es lo que permite las respuestas (`FEAT-COM-031`) sin una tabla aparte.

## Criterios de aceptación

- [ ] Comentar una publicación visible crea el comentario y devuelve `201`.
- [ ] **No se puede comentar una publicación cuya audiencia excluye al usuario.**
- [ ] El autor es el usuario autenticado, aunque la petición diga otra cosa.
- [ ] El texto se almacena saneado y conserva los emojis.
- [ ] Comentar no mueve créditos.
- [ ] Eliminar la publicación elimina sus comentarios.
- [ ] El listado se pagina.
- [ ] Con la cuenta sin activar devuelve `403`.
- [ ] Se publica `PostCommented` y el autor de la publicación recibe el aviso.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **I-1** | ¿Cómo se calcula «Más relevantes»? ¿Qué otras opciones hay? | Sin fórmula no hay consulta. Compartido con `CM-4` |
| I-3 | ¿Se pueden editar o eliminar los comentarios propios? | El menú «···» no tiene diseño |
| I-4 | ¿Cuántos comentarios se cargan de inicio? | La captura muestra uno |
| I-7 | ¿Un comentario admite adjuntos? | El compositor solo ofrece emoji |
| I-8 | ¿El contador de la publicación cuenta también las respuestas? | Define qué número se muestra |

## Estado

**Especificación:** `DRAFT`. Lo básico está definido; `I-1` bloquea la ordenación por
relevancia, no la funcionalidad entera.

**Implementación:** `TODO`.
