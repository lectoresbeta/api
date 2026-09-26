---
id: FEAT-COM-006
title: Comentar una publicación
context: Community
concept: Interaction
actors: [User]
spec_status: APPROVED
impl_status: PARTIAL
priority: P2
sources:
  - _sources/use-cases.pdf#p3
  - conversation:2026-09-22 (secuencia de interacciones)
  - docs/ui/post-interactions.md
endpoints: [GET /posts/{postId}/comments, POST /posts/{postId}/comments, PATCH /comments/{commentId}, DELETE /comments/{commentId}]
events: [PostCommented]
depends_on: [FEAT-COM-002]
updated: 2026-09-25
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
- `RN-7` El contador de comentarios de la publicación incluye **las respuestas** (`I-8`,
  resuelta): «n comentarios» significa cuánto se ha hablado ahí debajo, que es lo que espera
  quien lo lee y lo que hace comparable una publicación con otra.
- `RN-8` Se publica `PostCommented`, que `Notification` usa para avisar al autor de la
  publicación.
- `RN-9` El autor puede **editar y eliminar** lo suyo (`I-3`, resuelta), con el mismo criterio
  que una publicación: solo el texto, queda marcado como editado, y el de otra persona
  responde `404` y no `403` — un permiso denegado confirmaría que está ahí.
- `RN-10` Un comentario **no puede quedarse vacío**. No lleva adjunto, así que sin texto no
  hay comentario: vaciarlo es eliminarlo, y para eso hay una operación que lo dice.

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
| Editar el propio | `PATCH /comments/{commentId}` | `editPostComment` |
| Retirar el propio | `DELETE /comments/{commentId}` | `deletePostComment` |

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

- [x] Comentar una publicación visible crea el comentario y devuelve `201`.
- [x] **No se puede comentar una publicación cuya audiencia excluye al usuario.**
- [x] Tampoco se pueden leer sus comentarios.
- [x] El autor es el usuario autenticado, aunque la petición diga otra cosa.
- [x] El texto se almacena saneado y conserva los emojis.
- [x] Un comentario vacío se rechaza.
- [x] Comentar no mueve créditos.
- [x] Eliminar la publicación elimina sus comentarios.
- [x] El autor edita y retira lo suyo; nadie toca lo ajeno, y recibe `404`.
- [x] El contador de la publicación sigue toda la conversación.
- [x] El listado se pagina, por fecha en los dos sentidos.
- [x] Un criterio de orden no admitido devuelve `422` con los que sí valen.
- [x] Con la cuenta sin activar devuelve `403`.
- [x] Se publica `PostCommented` con a quién hay que avisar.
- [ ] El autor de la publicación **recibe** el aviso. `Notification` no lo escucha todavía.

## «Más relevantes»: la fórmula

```text
puntuación = apoyos + 2 × respuestas
```

Desempate: **el más antiguo primero**, que es quien abrió la conversación.

**Sin decaimiento temporal** (`I-1`, resuelta). Un comentario vive bajo una publicación que ya
tiene su propia antigüedad, así que penalizar el paso del tiempo dos veces no aporta nada y
complica la consulta.

Que una respuesta pese el doble que un apoyo es deliberado: responder cuesta más que pulsar un
botón, y un comentario que genera conversación es más relevante que uno que solo gusta.

Es una fórmula distinta —y más simple— que la del catálogo
([`decision:0008`](../../decisions/0008-catalogue-ordering.md)), porque resuelve un problema
distinto: ordenar cincuenta comentarios, no repartir trabajo entre miles de obras.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~I-3~~ | ¿Se pueden editar o eliminar los comentarios propios? | Resuelta: las dos, como en una publicación |
| ~~I-8~~ | ¿El contador de la publicación cuenta también las respuestas? | Resuelta: sí, toda la conversación |
| I-4 | ¿Cuántos comentarios se cargan de inicio? | La captura muestra uno |
| I-7 | ¿Un comentario admite adjuntos? | El compositor solo ofrece emoji |
| I-13 | ¿Puede el autor de la publicación retirar comentarios ajenos de lo suyo? | Es moderación propia y no tiene diseño (`I-9`) |

## Estado

**Especificación:** `APPROVED` (2026-09-24). `I-1` resuelta. Las preguntas que quedan
son detalles de interfaz.

**Implementación:** `PARTIAL` (2026-09-25). Comentar, listar, editar y retirar, con el filtro
de audiencia resuelto por `VisiblePost`, que es **la misma regla que usa el muro preguntada
por el otro lado**. Se escribe una vez y la usan cuatro operaciones: leer los comentarios,
escribir uno, responder y repostear.

**Falta «más relevantes»**, que es justo el orden que el desplegable enseña por defecto. Su
fórmula está decidida —`apoyos + 2 × respuestas`— y los apoyos son
[`FEAT-COM-030`](../README.md), que no existe: servir media fórmula y llamarla «relevancia»
sería ordenar por algo que no es lo que dice el nombre. Hoy responde `422` con los órdenes que
sí valen, igual que «más valorados» en «Mis relatos».

Hay además una razón técnica que conviene anotar para cuando llegue: un orden que cambia
mientras alguien lo lee —porque otro responde— no tiene una posición estable que codificar en
un cursor. Paginarlo bien exige meter el contador en el cursor, no solo sumar el término que
falta.

**El aviso ya existe** (2026-09-26): `NotifyAboutAPostComment` consume `PostCommented` y entrega
`POST_REPLY` a quien publicó y, si es una respuesta, también a quien escribió el comentario
padre. Lo que sigue abajo describe el hueco tal y como estaba:

~~`PostCommented` se publica con a quién avisar, y `Notification` no~~
lo escucha todavía (`FEAT-NOT-001`).
