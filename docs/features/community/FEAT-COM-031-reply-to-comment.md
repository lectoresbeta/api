---
id: FEAT-COM-031
title: Responder a un comentario
context: Community
concept: Interaction
actors: [User]
spec_status: APPROVED
impl_status: PARTIAL
priority: P2
sources:
  - conversation:2026-09-22 (secuencia de interacciones)
  - docs/ui/post-interactions.md
endpoints: [POST /comments/{commentId}/replies, GET /comments/{commentId}/replies]
events: [PostCommented]
depends_on: [FEAT-COM-006, FEAT-COM-032]
updated: 2026-09-25
---

# FEAT-COM-031 — Responder a un comentario

## Resumen

Cada comentario tiene «Responder» y un contador de respuestas. Al pulsarlo aparece un
compositor bajo el comentario, **ya rellenado con una mención a su autor**, y la respuesta
queda anidada debajo.

## Profundidad del anidamiento

Es la decisión de diseño de esta ficha.

| Opción | Resultado |
|---|---|
| **A. Un solo nivel** | Toda respuesta cuelga del comentario raíz. Responder a una respuesta añade otra al mismo hilo, con una mención a quien se responde |
| B. Anidamiento libre | Hilos arbitrariamente profundos |

**Decidido: A** (`I-2`, resuelta). Tres razones:

1. El diseño muestra **un único nivel**.
2. La **mención automática** del compositor es justo el mecanismo que sustituye a la
   profundidad: se sabe a quién se responde sin necesidad de anidar más.
3. Un hilo plano se pagina; uno arbitrariamente profundo, no.

Con la opción A, `parent_comment_id` apunta **siempre al comentario raíz**, nunca a otra
respuesta. Es una invariante sencilla de comprobar y fácil de romper si no se declara.



## Reglas de negocio

- `RN-1` Una respuesta es un `PostComment` con `parent_comment_id`. No es una entidad
  distinta.
- `RN-2` `parent_comment_id` apunta siempre a un comentario **de primer nivel**. Responder a
  una respuesta cuelga del mismo raíz.
- `RN-3` Al pulsar «Responder» se precarga una **mención** al autor del comentario o de la
  respuesta (`FEAT-COM-032`).
- `RN-4` La mención precargada **se puede borrar**: es una comodidad, no una obligación.
- `RN-5` Se aplican las mismas reglas que a un comentario: audiencia, saneado, cuenta
  activada y ausencia de efecto en créditos (`FEAT-COM-006`).
- `RN-6` Una respuesta tiene su propio «me gusta» (`FEAT-COM-030`).
- `RN-7` Eliminar un comentario raíz elimina sus respuestas.
- `RN-8` El contador de respuestas del comentario refleja cuántas cuelgan de él.

## Flujo principal

1. El usuario pulsa «Responder» en un comentario.
2. Aparece el compositor bajo el comentario, con la mención precargada.
3. Escribe y envía.
4. La respuesta aparece anidada, con su autor, antigüedad y acciones.
5. El contador de respuestas sube.
6. Se avisa al autor del comentario y a los mencionados.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Responder | `POST /comments/{commentId}/replies` | `replyToComment` |
| Listar respuestas | `GET /comments/{commentId}/replies` | `listCommentReplies` |

Si `commentId` es una respuesta, el servidor **resuelve al comentario raíz** (`RN-2`). El
cliente no necesita saber cuál es: lo resuelve el backend.

## Criterios de aceptación

- [x] Responder crea la respuesta anidada bajo el comentario.
- [x] El contador de respuestas sube, y baja al retirarla.
- [x] Responder a una respuesta la cuelga **del mismo comentario raíz**, no de la respuesta.
- [x] Ninguna respuesta tiene como padre otra respuesta.
- [x] Pedir las respuestas de una respuesta devuelve las del hilo entero.
- [x] El hilo se lee de la más antigua a la más reciente, y se pagina.
- [x] La mención precargada se puede borrar antes de enviar.
- [x] No se puede responder en una publicación cuya audiencia excluye al usuario.
- [x] Tampoco leer el hilo.
- [x] Eliminar el comentario raíz elimina sus respuestas, y el contador de la publicación baja
  por todas.
- [ ] Una respuesta se puede valorar con «me gusta». **Es `FEAT-COM-030`**, que no existe.
- [x] Con la cuenta sin activar devuelve `403`.

El tercer y cuarto criterio son los que sostienen la invariante de `RN-2`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~I-2~~ | ¿Se confirma el anidamiento de un solo nivel? | Resuelta: sí |
| I-4 | ¿Se cargan las respuestas de inicio o bajo demanda? | Con hilos largos importa; es de cliente, el endpoint pagina |
| I-9 | ¿El autor de la publicación puede eliminar respuestas ajenas en su publicación? | Moderación propia, sin diseño |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `PARTIAL` (2026-09-25). El hilo entero: responder, leerlo paginado,
retirar una respuesta y retirar el raíz con todo lo que cuelga.

La invariante de `RN-2` —ninguna respuesta cuelga de otra respuesta— vive en un solo sitio,
`ReachableComment`, que usan las tres operaciones del hilo. Es fácil de romper si no se
declara: basta con que alguien guarde el `commentId` que le llegó sin subir al raíz, y a
partir de ahí leer un hilo es una consulta recursiva.

**Falta el «me gusta» de una respuesta**, que es [`FEAT-COM-030`](../README.md) y no existe —
la misma ausencia que impide ordenar los comentarios por relevancia.
