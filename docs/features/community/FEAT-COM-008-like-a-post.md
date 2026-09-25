---
id: FEAT-COM-008
title: Apoyar una publicación con un «me gusta»
context: Community
concept: Interaction
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/ui/post-interactions.md
  - docs/ui/home.md
  - conversation:2026-09-25
endpoints: [PUT /posts/{postId}/like, DELETE /posts/{postId}/like]
events: []
depends_on: [FEAT-COM-002]
updated: 2026-09-25
---

# FEAT-COM-008 — «Me gusta» en una publicación

## Resumen

El primero de los cuatro contadores de la barra de acciones: `♡ 5 Me gusta`.

Es **alternable y uno por persona**. Pulsarlo resalta el botón y sube el contador; volver a
pulsarlo lo deshace.

## Cuidado con el nombre

En `Feedback` ya existe una **valoración positiva** de una corrección
(`FEAT-FBK-006`) que **otorga créditos**. Esto es otra cosa: un gesto social sobre una
publicación del muro, **sin ningún efecto económico**.

Comparten la palabra y no el concepto, y por eso viven en contextos distintos. Si algún día
un «me gusta» del muro moviera créditos, dejaría de ser un gesto y pasaría a ser una
transacción, con todo lo que eso arrastra: idempotencia contable, reversión, invariante. No
es lo que la pantalla ofrece.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Apoyar una publicación y deshacerlo | Con la cuenta activada, y viendo esa publicación |

**Ver la publicación es la condición que importa.** Un «me gusta» sobre algo que no se puede
ver revelaría que existe, y ese es el agujero clásico de los contadores: no hace falta leer
una publicación para saber, por el error que devuelve, que está ahí.

## Reglas de negocio

- `RN-1` Uno por persona y publicación. Repetirlo **no suma**.
- `RN-2` Es alternable: se pone y se quita.
- `RN-3` Poner uno que ya está, y quitar uno que no está, **responden bien**. Son
  idempotentes porque el botón se pulsa dos veces sin querer y porque el cliente reintenta.
- `RN-4` Solo sobre publicaciones que quien pulsa **puede ver**. Si no, responde igual que si
  no existiera.
- `RN-5` **Se puede apoyar lo propio.** Suena raro y es lo correcto: nadie lo prohíbe en
  ninguna red, y prohibirlo obligaría a explicar por qué.
- `RN-6` El contador vive **en la publicación**, no se cuenta en cada carga. Un muro que hace
  un `COUNT` por tarjeta se degrada justo cuando la plataforma empieza a funcionar.
- `RN-7` La tarjeta dice **si quien mira lo ha apoyado**, para que el botón salga resaltado.
- `RN-8` Un bloqueo corta: quien está bloqueado con el autor no ve la publicación, así que
  tampoco la apoya (`FEAT-COM-034`).
- `RN-9` Borrar una publicación se lleva sus apoyos. No quedan filas apuntando a nada.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Apoyar | `PUT /api/v1/posts/{postId}/like` | `likePost` |
| Retirar | `DELETE /api/v1/posts/{postId}/like` | `unlikePost` |

`PUT` y no `POST` porque **fija un estado**: la publicación acaba apoyada por quien llama,
haya pulsado una vez o cinco. Es la forma honesta de decir que es idempotente.

Las dos devuelven el contador, que es lo que la pantalla necesita para repintarse sin una
segunda petición.

## Eventos

**Ninguno.**

No es un olvido. Un «me gusta» no mueve créditos, no cambia el catálogo y no avisa a nadie:
`NotificationKind` no tiene tipo para esto, y el catálogo de `FEAT-USR-039` clasifica los
avisos sociales de ritmo alto como cosas que no llegan al buzón. Publicar un hecho que nadie
consume es un contrato que luego hay que mantener.

Si algún día se avisa al autor, será un tipo de aviso nuevo y una decisión aparte (`CM-7`).

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `post_like` | `post_id`, `member_id`, `liked_at`. Clave compuesta por el par |

La tabla y la entidad **ya existían** desde `FEAT-COM-002`, sin nada que las usara. El
contador `like_count` también.

## Criterios de aceptación

- [x] Apoyar sube el contador; retirar lo baja.
- [x] Apoyar dos veces deja el contador en uno.
- [x] Retirar lo que no se puso no falla ni baja de cero.
- [x] La tarjeta dice si quien mira lo ha apoyado.
- [x] No se puede apoyar una publicación que no se puede ver.
- [x] Se puede apoyar la propia.
- [x] Sin la cuenta activada, `403`.
- [x] Borrar la publicación se lleva sus apoyos.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| CM-7 | ¿Se avisa al autor de que le han apoyado? | Sería un tipo de aviso nuevo, y de los de ritmo alto |
| CM-8 | ¿Se puede ver **quién** ha apoyado una publicación? | El diseño solo enseña el número |

## Estado

**Especificación:** `APPROVED` (2026-09-25).

**Implementación:** `DONE` (2026-09-25).
