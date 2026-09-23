---
id: FEAT-FBK-011
title: Guardar un borrador de corrección
context: Feedback
concept: Correction
actors: [BetaReader]
spec_status: APPROVED
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-22 (botón «Guardar» del panel de corrección)
  - docs/ui/read-chapter.md
endpoints:
  - PUT /chapters/{chapterId}/correction/draft
  - DELETE /chapters/{chapterId}/correction/draft
depends_on: [FEAT-FBK-003]
events: []
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

Se recupera junto con el cuestionario en `GET /chapters/{chapterId}/questionnaire`, para que
abrir el panel sea una sola llamada.

`PUT` es idempotente por naturaleza: el recurso es «el borrador de este lector para este
capítulo», que es único. No hace falta `Idempotency-Key`.

## Eventos

No publica ni consume ninguno. Es intencionado: un borrador no es un hecho de negocio.

## Modelo de datos afectado

Es el mismo agregado `Correction` de [`FEAT-FBK-003`](FEAT-FBK-003-answer-correction-questionnaire.md)
en estado `DRAFT`, no una tabla aparte. El índice único `(chapterId, readerId)` cubre también
`RN-1`.

Tener una sola tabla evita el problema clásico de dos modelos que deben mantenerse
sincronizados y acaban divergiendo: enviar es una transición de estado, no una copia.

## Diseño (Figma)

[`../../ui/read-chapter.md`](../../ui/read-chapter.md) — botón «Guardar» junto a «Enviar».

## Criterios de aceptación

- [ ] Guardar dos veces deja un solo borrador del mismo capítulo.
- [ ] Un lector puede tener borradores simultáneos en capítulos distintos de una misma obra.
- [ ] Un borrador guardado aparece precargado al reabrir el panel.
- [ ] Guardar no publica ningún evento ni mueve créditos.
- [ ] Un borrador no supera la validación de máximo, pero sí puede estar incompleto.
- [ ] El autor no puede acceder al borrador de nadie por ninguna vía de la API.
- [ ] Al enviar, el borrador deja de existir como tal.
- [ ] El lector puede descartar su borrador.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| R-7 | ¿Cuántos borradores simultáneos puede tener alguien? | Uno por capítulo ya está fijado; con la corrección por capítulo el número potencial se multiplica |
| R-8 | ¿Caduca un borrador? | **Ya no bloquea nada:** sin retenciones, un borrador eterno no inmoviliza saldo ajeno |
| Q-5 | Si el autor cierra la corrección, ¿qué pasa con los borradores? | Trabajo perdido sin aviso |
| Q-8 | ¿Se guarda solo o solo al pulsar «Guardar»? | La maqueta solo muestra el botón |

`R-8` está ligada a `R-4` y `R-1` de [`FEAT-FBK-003`](FEAT-FBK-003-answer-correction-questionnaire.md):
si abrir el panel reserva crédito del autor, un borrador eterno inmoviliza saldo ajeno y
debe caducar. Si la reserva ocurre antes, al conceder el acceso, el borrador no bloquea nada
y puede durar.

Con la corrección **por capítulo**, la pregunta gana peso: un lector con borradores abiertos
en los treinta capítulos de una novela podría inmovilizar el saldo del autor por treinta
correcciones que quizá nunca envíe.

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `TODO`.
