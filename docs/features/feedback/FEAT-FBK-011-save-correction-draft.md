---
id: FEAT-FBK-011
title: Guardar un borrador de corrección
context: Feedback
concept: Correction
actors: [BetaReader]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - conversation:2026-09-22 (botón «Guardar» del panel de corrección)
  - docs/ui/read-chapter.md
endpoints:
  - PUT /chapters/{chapterId}/correction/draft
  - DELETE /chapters/{chapterId}/correction/draft
depends_on: [FEAT-FBK-003]
events: [CorrectionDraftDiscarded]
updated: 2026-09-24
---

# FEAT-FBK-011 — Guardar un borrador de corrección

## Resumen

El panel de corrección tiene dos botones, **«Enviar»** y **«Guardar»**, y significan cosas
distintas. «Guardar» conserva lo escrito sin entregarlo, para continuar en otra sesión.

Un borrador **no mueve créditos, no notifica a nadie y no publica ningún evento**. Hasta que
no se envía, no ha ocurrido ningún hecho de negocio.

## Por qué existe

Una corrección seria de una obra larga no se escribe de una sentada. Sin borrador, el lector
tiene dos opciones: escribir en otro sitio y pegar, o perder el trabajo. Las dos perjudican
la calidad del feedback, que es el producto.

## Reglas de negocio

- `RN-1` Un lector tiene **como máximo un borrador por capítulo** (`R-2`). Guardar de nuevo
  sobrescribe. Puede tener borradores abiertos en varios capítulos de la misma obra.
- `RN-2` Un borrador **no valida obligatoriedad ni longitud mínima**: está a medias por
  definición. Solo se valida el máximo en palabras, para no almacenar texto sin límite.
- `RN-3` Guardar un borrador **no publica ningún evento**.
- `RN-4` El borrador es **privado del lector**. El autor no lo ve ni sabe que existe.
- `RN-5` Al enviar la corrección, el borrador **desaparece**: pasa a ser la corrección
  enviada, no una copia paralela.
- `RN-6` El borrador recuerda **contra qué versión del cuestionario** se escribió. Si el autor
  lo cambia, el lector debe saber que las preguntas ya no son las mismas.
- `RN-7` El lector puede **descartar** su borrador explícitamente.

`RN-4` importa más de lo que parece: si el autor supiera que alguien tiene un borrador a
medias, tendría información sobre una crítica que aún no ha recibido, y el lector sentiría
presión para enviarla.

## Flujo principal

1. El lector escribe respuestas en el panel.
2. Pulsa «Guardar».
3. El sistema sobrescribe el borrador de ese capítulo para ese lector.
4. Al volver a abrir el panel, las respuestas guardadas aparecen precargadas.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| No hay borrador previo | Se crea | `201` |
| Ya hay borrador | Se sobrescribe | `200` |
| Una respuesta supera el máximo | Se rechaza | `422` |
| El lector perdió el acceso a la obra | El borrador se conserva pero no puede enviarse | `403` al enviar |
| El autor cerró la corrección | Ver `Q-5` de `FEAT-FBK-003` | — |
| El cuestionario cambió de versión | El borrador se conserva con aviso | `200` con `questionnaireVersionChanged` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Guardar o sobrescribir | `PUT /chapters/{chapterId}/correction/draft` | `saveCorrectionDraft` |
| Descartar | `DELETE /chapters/{chapterId}/correction/draft` | `discardCorrectionDraft` |

Guardar en un capítulo que no se había empezado **lo empieza**: escribir es la señal más
clara posible de que alguien está corrigiendo, y obligar a pulsar dos botones para que el
sistema se entere sería inventar un trámite.

Se recupera junto con el cuestionario en `GET /chapters/{chapterId}/questionnaire`, para que
abrir el panel sea una sola llamada.

`PUT` es idempotente por naturaleza: el recurso es «el borrador de este lector para este
capítulo», que es único. No hace falta `Idempotency-Key`.

## Eventos

**Guardar no publica nada.** Es intencionado: un borrador a medias no es un hecho de negocio,
y avisar de que alguien está escribiendo sería exactamente lo que `RN-4` evita.

**Descartar sí lo es**, y esto corrige lo que esta ficha decía antes:

| Evento | Cuándo | Consumidores |
|---|---|---|
| `CorrectionDraftDiscarded` | El lector descarta su borrador | **`Credits`** (descarta la cotización), **`Reading`** (revoca el acceso nacido de empezar) |

Descartar libera dos cosas que empezar había ocupado: **la cotización del precio**
([`FEAT-CRD-009`](../credits/FEAT-CRD-009-balance-check-on-correction-start.md)), y con ella
uno de los tres sitios de corrección del capítulo, y **el acceso de lector beta** si nació de
esa corrección ([`FEAT-RDG-001`](../reading/FEAT-RDG-001-become-beta-reader-by-correcting.md)).

Los dos consumidores existían antes que el publicador, lo que hasta ahora dejaba esas dos
cosas sin soltarse nunca.

## Modelo de datos afectado

Es el mismo agregado `Correction` de [`FEAT-FBK-003`](FEAT-FBK-003-answer-correction-questionnaire.md)
en estado `DRAFT`, no una tabla aparte. El índice único `(chapterId, readerId)` cubre también
`RN-1`.

Tener una sola tabla evita el problema clásico de dos modelos que deben mantenerse
sincronizados y acaban divergiendo: enviar es una transición de estado, no una copia.

## Diseño (Figma)

[`../../ui/read-chapter.md`](../../ui/read-chapter.md) — botón «Guardar» junto a «Enviar».

## Criterios de aceptación

- [x] Guardar dos veces deja un solo borrador del mismo capítulo.
- [x] Un lector puede tener borradores simultáneos en capítulos distintos de una misma obra.
- [x] Un borrador guardado aparece precargado al reabrir el panel.
- [x] Guardar no publica ningún evento ni mueve créditos.
- [x] Un borrador no supera la validación de máximo, pero sí puede estar incompleto.
- [x] El autor no puede acceder al borrador de nadie por ninguna vía de la API.
- [x] Al enviar, el borrador deja de existir como tal.
- [x] El lector puede descartar su borrador.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| R-7 | ¿Cuántos borradores simultáneos puede tener alguien? | Uno por capítulo ya está fijado; con la corrección por capítulo el número potencial se multiplica |
| **R-8** | ¿Caduca un borrador? | **Reabierta:** no inmoviliza saldo, pero sí **uno de los tres sitios del capítulo** |
| Q-5 | Si el autor cierra la corrección, ¿qué pasa con los borradores? | Trabajo perdido sin aviso |
| Q-8 | ¿Se guarda solo o solo al pulsar «Guardar»? | La maqueta solo muestra el botón |

### `R-8` vuelve a estar viva, por otro motivo

Esta ficha daba la pregunta por resuelta: sin retenciones, un borrador eterno no inmoviliza
saldo ajeno. Sigue siendo cierto y ya no es lo único que importa.

[`FEAT-CRD-009`](../credits/FEAT-CRD-009-balance-check-on-correction-start.md) `RN-8` fijó
que **un capítulo admite tres correcciones a la vez**, y la cuenta se lleva sobre las
cotizaciones abiertas, que nacen al empezar y mueren al entregar o descartar. Así que un
borrador abandonado no bloquea créditos: **bloquea un sitio**. Tres lectores despistados
dejan un capítulo popular cerrado indefinidamente, y el autor no tiene forma de verlo ni de
arreglarlo.

Opciones, para cuando toque:

| Opción | A favor | En contra |
|---|---|---|
| No hacer nada | Ninguna complejidad | Un capítulo se puede quedar cerrado para siempre |
| Caducar la **cotización**, no el borrador | El trabajo del lector se conserva; el sitio se libera | El precio se recalcula si vuelve, lo que contradice `RN-2` de `FEAT-CRD-016` |
| No contar como ocupado un borrador sin tocar en N días | Igual, sin tocar el precio anotado | Hace falta guardar cuándo se tocó por última vez |

La tercera parece la buena, y no bloquea esta ficha: hasta que haya volumen, tres sitios por
capítulo sobran.

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `DONE` (2026-09-24). Las dos operaciones, con las siete reglas.

Dos cosas que la implementación aclaró, ya recogidas arriba:

- **guardar en un capítulo sin empezar lo empieza**, con los efectos de `startCorrection`.
  Escribir es la señal más clara posible de que alguien está corrigiendo, y pedir dos botones
  para que el sistema se entere sería inventar un trámite;
- **descartar publica `CorrectionDraftDiscarded`**, que `Credits` y `Reading` ya esperaban.
  Sus dos consumidores existían antes que el publicador, así que hasta hoy la cotización y el
  acceso no se soltaban nunca.

`R-8` queda abierta y **es la única deuda de esta ficha**: un borrador abandonado sin
descartar retiene uno de los tres sitios del capítulo para siempre.
