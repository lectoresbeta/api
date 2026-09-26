---
id: FEAT-COM-003
title: Publicar buscando lectores beta para una obra
context: Community
concept: Post
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - docs/ui/create-post.md
  - docs/features/community/FEAT-COM-002-create-post.md
  - conversation:2026-09-25
endpoints:
  - createPost
events: []
depends_on: [FEAT-COM-002, FEAT-WRK-016]
updated: 2026-09-25
---

# FEAT-COM-003 — Publicar buscando lectores beta para una obra

## Resumen

Una publicación del muro con intención `LOOKING_FOR_BETA_READERS`, que **señala una obra
concreta**: la de quien publica, y ya publicada.

Es la única de las cuatro intenciones que **impone algo**, y por lo que significa. `GENERAL`,
`LOOKING_FOR_WRITING_BUDDY` y `OFFERING_AS_BETA_READER` describen lo que alguien quiere; esta
pide algo concreto sobre un texto concreto. Sin ese texto, nadie puede atenderla.

## Qué añade sobre `FEAT-COM-002`

El mecanismo de publicar ya existía, y el tipo y el relato adjunto también. Lo que faltaba era
**la regla**, que es lo que convierte un tipo del enum en una funcionalidad:

| | Antes | Ahora |
|---|---|---|
| Obra | Opcional en cualquier tipo | **Obligatoria** en `LOOKING_FOR_BETA_READERS` |
| De quién | La propia o cualquiera visible | **Solo la propia** al reclutar |
| Estado | La propia, aunque fuera borrador | **Publicada** al reclutar |

## Reglas de negocio

- `RN-1` Una publicación `LOOKING_FOR_BETA_READERS` **lleva una obra**. «Busco lectores» sin
  decir para qué es una petición que nadie puede atender: quien la lee no sabe qué se le
  ofrece, y quien la escribe no recibe a nadie.
- `RN-2` La obra es **de quien publica**. Reclutar lectores para lo que escribió otro sería
  decidir por él a quién enseña su texto, que es justo lo que la modalidad de acceso existe
  para que decida su autor.
- `RN-3` La obra está **publicada**, no en borrador. Anunciar un borrador manda a quien
  responda a una puerta cerrada: no puede leerlo ni solicitar acceso, porque para él la obra
  no existe.
- `RN-4` **Cualquier modalidad de acceso vale.** Reclutar para una obra `PRIVATE` es legítimo:
  quien publica está recogiendo interesados a los que después invitará (`FEAT-RDG-004`), y
  esa es exactamente la secuencia que `PRIVATE` prevé.
- `RN-5` **Las otras tres intenciones no cambian.** Una publicación general sigue sin exigir
  obra y sigue pudiendo llevar un borrador propio: eso es promocionar lo tuyo, no pedirle
  nada a nadie.
- `RN-6` Una obra inventada responde `404` antes que cualquiera de las tres anteriores: si no
  existe, no hay nada más que decir de ella.
- `RN-7` **No concede acceso a nadie.** Es una publicación: quien se interese entra por donde
  entra todo el mundo, que es la solicitud o la invitación.

## Flujos alternativos y errores

| Caso | Respuesta |
|---|---|
| Sin obra | `422 WORK_REQUIRED` |
| Obra de otra persona | `403 NOT_YOUR_WORK` |
| Obra en borrador | `409 WORK_NOT_VISIBLE` |
| Obra inexistente | `404 WORK_NOT_FOUND` |

## Contrato de API

Ninguno nuevo: es `POST /api/v1/posts` (`createPost`) con `type: LOOKING_FOR_BETA_READERS` y
`workId`.

## Fuera de alcance, y por qué

**La tarjeta viva de la obra** —título, portada, estado— es
`FEAT-COM-028` cuando se escriba: es la forma de incluir un
relato en **cualquier** publicación, no solo en esta, y adelantarla aquí sería implementar otra
ficha por la puerta de atrás. Hoy la publicación viaja con su `workId` y el cliente pide la
obra.

**Filtrar el muro por intención** es `FEAT-COM-009`.

## Eventos

Ninguno propio. Publica `PostPublished`, como cualquier otra publicación.

## Efectos en créditos

Ninguno. Una publicación no consume ni genera créditos (`FEAT-COM-002` `RN-9`).

## Modelo de datos afectado

Ninguno. `PostType` y `Post::workId` ya existían.

## Criterios de aceptación

- [x] Con una obra propia y publicada, la publicación sale.
- [x] Sin obra, se rechaza diciendo que falta.
- [x] Con la obra de otra persona, se rechaza.
- [x] Con un borrador, se rechaza diciendo que la publique primero.
- [x] Con una obra inexistente, `404`.
- [x] Las otras tres intenciones siguen sin exigir nada.
- [x] La tarjeta del muro lleva la intención y la obra.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
