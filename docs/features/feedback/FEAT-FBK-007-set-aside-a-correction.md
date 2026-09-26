---
id: FEAT-FBK-007
title: Apartar de la bandeja una corrección recibida
context: Feedback
concept: Correction
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/bounded-contexts/feedback.md
  - conversation:2026-09-25
endpoints:
  - setCorrectionVisibility
  - listCorrectionsReceived
events: []
depends_on: [FEAT-FBK-004, FEAT-CRD-018]
updated: 2026-09-25
---

# FEAT-FBK-007 — Apartar de la bandeja una corrección recibida

## Resumen

El autor aparta de su bandeja una corrección que ha recibido, y puede devolverla.

**Apartar no es borrar, y eso es toda la funcionalidad.** Una corrección entregada no se
elimina nunca: alguien la escribió, cobró por ella y la tiene en «Mis correcciones»
(`FEAT-FBK-010`). Lo que el autor decide aquí es **qué sigue viendo él**, no qué existe.

El registro lo llamaba «ocultar un comentario recibido». Se renombra a **apartar** porque
«ocultar» sugiere que se le esconde a alguien, y a nadie se le esconde nada: el nombre de la
operación debería decir lo que hace.

## Reglas de negocio

- `RN-1` Una corrección apartada **sale de la bandeja** del autor. No aparece en la lista
  normal de lo recibido.
- `RN-2` **Nunca se le quita a quien la escribió.** Su lista no cambia, su contador no baja y
  lo que cobró no se toca. Si apartar afectara a la otra parte, sería una forma de castigar
  una corrección que no gustó.
- `RN-3` **Se puede deshacer**, y por eso las apartadas tienen **su propia lista**
  (`?hidden=true`). Sin ella, apartar sería irreversible en la práctica: nadie recuerda el
  identificador de algo que apartó hace tres meses, y «apartar» habría sido «borrar» con otro
  nombre. Es el mismo razonamiento que obliga a
  [`FEAT-COM-034`](../community/FEAT-COM-034-block-user.md) a publicar la lista de
  bloqueados.
- `RN-4` Una corrección **retenida por descubierto** (`LOCKED`, `FEAT-CRD-018`) **no se puede
  apartar**. Apartar es una decisión sobre algo que se ha leído, y de una retenida no se ha
  leído nada. Mantiene además simple la máquina de estados: `VISIBLE` y `HIDDEN_BY_AUTHOR` se
  alternan, y `LOCKED` lo decide el descubierto.
- `RN-5` **Solo el destinatario.** Quien la escribió no aparta nada: no es su bandeja, y
  responde `404` igual que alguien ajeno.
- `RN-6` El destinatario **sigue pudiendo abrirla**, con su contenido. La apartó él y es quien
  pide verla; sin esto, la segunda bandeja sería una lista de títulos que no llevan a ninguna
  parte.
- `RN-7` Es **idempotente** en las dos direcciones, y la respuesta describe el estado final y
  no lo que se hizo para llegar a él.
- `RN-8` **No mueve créditos** ni publica ningún hecho. Es una decisión de bandeja, no de
  negocio: ningún otro contexto tiene nada que hacer con ella.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| No es el destinatario, o no existe | No se distinguen | `404 CORRECTION_NOT_FOUND` |
| Está retenida por descubierto | Se explica, con la salida | `409 CORRECTION_IS_LOCKED` |
| Ya estaba apartada | No cambia nada | `200` con el estado final |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Apartar o devolver | `PUT /api/v1/corrections/{correctionId}/visibility` | `setCorrectionVisibility` |
| La segunda bandeja | `GET /api/v1/me/corrections/received?hidden=true` | `listCorrectionsReceived` |

`PUT` con `hidden` y no dos rutas: fija un estado, y las dos direcciones son la misma decisión
vista al derecho y al revés.

Son **dos bandejas y no una lista con filtro**: la normal excluye las apartadas y la otra
enseña solo esas. Mezclarlas obligaría al cliente a filtrar lo que ya había pedido.

## Eventos

Ninguno, ni publicados ni consumidos. Es deliberado: qué corrección quiere seguir viendo un
autor en su bandeja no es un hecho de negocio que a otro contexto le importe.

## Efectos en créditos

Ninguno. Lo que se pagó, pagado está.

## Modelo de datos afectado

Ninguno nuevo. `CorrectionVisibility::HIDDEN_BY_AUTHOR` existía en el enum y en la columna
desde `FEAT-CRD-018` **sin que nada lo escribiera ni lo leyera**; esta ficha lo enciende.

## Criterios de aceptación

- [x] Apartar saca la corrección de la bandeja y devolverla la trae de vuelta entera.
- [x] Las apartadas tienen su propia lista, y no salen en la normal.
- [x] Quien la escribió la sigue viendo en su lista y la puede abrir.
- [x] Apartar no mueve ningún crédito.
- [x] Repetirlo en cualquiera de las dos direcciones no cambia nada.
- [x] Solo el destinatario puede; quien la escribió recibe `404`.
- [x] El destinatario puede abrir lo que apartó, con su contenido.
- [x] Una retenida por descubierto no se puede apartar, y se dice por qué.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
