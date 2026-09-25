---
id: FEAT-COM-001
title: Ver las publicaciones del muro principal
context: Community
concept: Post
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - _sources/use-cases.pdf#p3
  - docs/ui/home.md
  - conversation:2026-09-25 (composición del muro)
endpoints: [GET /posts]
events: []
depends_on: [FEAT-COM-002, FEAT-COM-010]
updated: 2026-09-25
---

# FEAT-COM-001 — Ver las publicaciones del muro principal

## Resumen

El muro de la Home, bajo «¿Qué está pasando en Lectores Beta?»: las publicaciones de la
comunidad, de la más reciente a la más antigua, paginadas.

Es la mitad que lee de [`FEAT-COM-002`](FEAT-COM-002-create-post.md). Sin ella publicar es
escribir en un sitio que nadie mira, y el filtro de audiencia —que es una regla de
privacidad— no tiene dónde ejercerse.

## Qué compone el muro

**Decidido** (`H-8`): **lo que la audiencia de cada publicación permita ver a quien mira.**

| Audiencia de la publicación | Quién la ve en el muro |
|---|---|
| `EVERYONE` | Cualquiera con sesión |
| `FOLLOWERS` | Solo quienes siguen a su autor, y su propio autor |

No es un muro **solo de a quién sigues**. La cabecera de la pantalla pregunta «¿qué está
pasando en Lectores Beta?», no «qué están diciendo los tuyos», y el propio diseño lo dice sin
ambigüedad: *«el muro no está vacío aunque no siga a nadie»*.

La consecuencia importante es que así **la audiencia es la única regla que decide**, que es lo
que dice [`FEAT-COM-002`](FEAT-COM-002-create-post.md) `RN-8`. En un muro restringido a
quienes sigues, `EVERYONE` y `FOLLOWERS` serían indistinguibles —solo verías a los tuyos de
todas formas— y el selector de audiencia del modal sería un adorno.

El bloque «todavía no sigues a ningún autor» de la Home sigue teniendo sentido: aparece porque
esa persona **no sigue a nadie**, no porque el muro esté vacío
([`FEAT-COM-018`](FEAT-COM-018-home-author-suggestions.md)).

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Ver el muro | Sesión iniciada. **No exige cuenta activada**: leer no es escribir |
| `Guest` | Nada | El muro no se sirve sin sesión |

Sin sesión no hay muro, y no es una limitación técnica: sin saber quién mira no se puede
resolver `FOLLOWERS`, y servir solo lo público sería una segunda vista de la misma pantalla
que habría que mantener.

## Reglas de negocio

- `RN-1` El orden es **cronológico descendente**. «Más relevante» necesita una fórmula que
  nadie ha definido y está bloqueada aparte
  ([`FEAT-COM-024`](../README.md), `H-7`).
- `RN-2` El filtrado por audiencia es **de servidor**. Una publicación que quien mira no debe
  ver **no se sirve**; no se envía para que el cliente la esconda.
- `RN-3` Una publicación **eliminada** no aparece, para nadie, ni siquiera para su autor.
- `RN-4` Se pagina por cursor, como el resto de listados de la plataforma
  ([`paginación`](../../api/conventions/pagination.md)).
- `RN-5` Cada tarjeta lleva sus **contadores** —comentarios, reposts— leídos de la propia
  publicación, no contados en cada consulta.
- `RN-6` El autor de cada publicación viaja **resuelto** —nombre, `@usuario`, avatar—, porque
  una tarjeta con un identificador no se puede pintar y pedirlos uno a uno sería una consulta
  por tarjeta.
- `RN-7` Quien **bloquea** a alguien no ve sus publicaciones, ni ese alguien las suyas
  ([`FEAT-COM-034`](FEAT-COM-034-block-user.md)).
- `RN-9` El perfil ajeno es un **techo** sobre el muro: quien no es visible
  para quien mira no aparece, y su publicación tampoco. Es la misma regla que
  ya aplican las listas de seguidores, y la razón es que un muro no es una
  lista de textos sino de **personas diciendo cosas**: sin la persona no hay
  tarjeta que pintar.
- `RN-8` El muro **no mueve créditos** y no es una superficie de lectura de obras: la tarjeta
  de un relato cita la obra, no la sirve.

`RN-2` es la que hay que vigilar. Es la misma regla que el catálogo aplica a la clasificación
de contenido: en cuanto una consulta del muro olvide el filtro, lo escrito para un círculo
cerrado aparece ante todos, y esa es exactamente la sorpresa que hace que la gente deje de
publicar.

## Flujo principal

1. El usuario abre la Home.
2. El servidor resuelve a quién sigue.
3. Devuelve las publicaciones visibles para él, de la más reciente a la más antigua.
4. Cada tarjeta lleva autor resuelto, cuerpo, adjunto y contadores.
5. Al llegar al final, el cursor pide la página siguiente.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| No hay ninguna publicación visible | Lista vacía, no error | `200` con `posts: []` |
| Cursor manipulado o caducado | Se rechaza | `422` con `code: INVALID_CURSOR` |
| Sin sesión | Se rechaza | `401` |
| Cuenta sin activar | **Se sirve igual**: leer no es escribir | `200` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Muro principal | `GET /posts` | `listPosts` |

Parámetros: la paginación estándar. El filtro por tipo de publicación y la ordenación por
relevancia son de [`FEAT-COM-009`](../README.md) y `FEAT-COM-024`, y no entran aquí.

## Eventos

Ninguno. Mirar no es un hecho.

## Modelo de datos afectado

Ninguna tabla nueva. Usa `community_ctx.post` y sus índices, que ya existen:
`idx_post_wall (created_at, audience)` para el recorrido general e `idx_post_author
(author_id, created_at)` para el muro de una persona.

Resolver `FOLLOWERS` se apoya en `author_subscription`, que es de este mismo contexto.

## Diseño (Figma)

[Home](../../ui/home.md), sección «3. Muro». La tarjeta, sus contadores y el menú «···».

## Criterios de aceptación

- [x] El muro devuelve las publicaciones de la más reciente a la más antigua.
- [x] Una publicación `EVERYONE` la ve cualquiera con sesión.
- [x] Una publicación `FOLLOWERS` **no la ve** quien no sigue a su autor.
- [x] Una publicación `FOLLOWERS` sí la ve quien sí le sigue.
- [x] El autor ve siempre las suyas, sea cual sea su audiencia.
- [x] Una publicación eliminada no aparece para nadie.
- [x] Lo excluido **no llega al cliente** y no cuenta en la paginación.
- [x] Cada tarjeta lleva el autor resuelto y sus contadores.
- [x] Se pagina por cursor y un cursor inválido devuelve `422`.
- [x] Funciona con la cuenta sin activar.
- [x] Sin sesión devuelve `401`.
- [x] Un bloqueo esconde las publicaciones **en las dos direcciones**.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~H-8~~ | ¿Qué compone el muro de quien no sigue a nadie? | Resuelta: lo compone la audiencia, no el seguimiento |
| H-7 | ¿Qué fórmula tiene «más relevante»? | Bloquea `FEAT-COM-024`, no este listado |
| C-12 | ¿Debe la plataforma poder fijar una publicación institucional arriba? | `FEAT-COM-038` no existe todavía |
| C-13 | ¿El muro esconde las publicaciones de quien has silenciado? | `FEAT-COM-033` no existe todavía |

## Estado

**Especificación:** `APPROVED` (2026-09-25). `H-8` resuelta. Lo que queda —relevancia, cuenta
institucional, silenciar— son funcionalidades propias que no cambian este listado.

**Implementación:** `DONE` (2026-09-25).

Dos decisiones que la ficha no traía y conviene tener escritas:

- **la imagen de una publicación se pide por la publicación**, no desde la
  carpeta pública de ficheros. Si viviera en `media/`, la foto de algo escrito
  para seguidores quedaría protegida solo por lo difícil que es adivinar una
  clave, y una clave se filtra el día que aparece en un registro o en una
  captura. El coste, anotado: esa respuesta depende de quién pregunta, así que
  no puede ir detrás de una caché compartida;
- **el filtro se escribe dos veces a propósito**, y las dos viven pegadas: en
  la consulta del muro, porque comprobar veinte publicaciones después de
  traerlas rompería la paginación, y en `VisiblePost`, para las operaciones
  que hablan de una sola. Si una cambia, la otra también.

Un límite conocido: resolver `FOLLOWERS` mete en la consulta la lista entera
de a quién sigue quien mira. Con seguimientos normales es un `IN` corto; quien
siga a miles lo notará, y el arreglo será una proyección, no otra consulta.
