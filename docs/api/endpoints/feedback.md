# Endpoints — `Feedback`

> Estado: **parcial**. Solo están especificadas las operaciones de corrección; el resto del
> contexto sigue pendiente.

## Resumen

| Método y ruta | `operationId` | Propósito | Funcionalidad | Estado |
|---|---|---|---|---|
| `POST /chapters/{chapterId}/corrections` | `submitCorrection` | Enviar la corrección | FEAT-FBK-003 | DRAFT |
| `PUT /chapters/{chapterId}/correction/draft` | `saveCorrectionDraft` | Guardar borrador | FEAT-FBK-011 | DRAFT |
| `DELETE /chapters/{chapterId}/correction/draft` | `discardCorrectionDraft` | Descartar borrador | FEAT-FBK-011 | DRAFT |
| `GET /works/{workId}/corrections` | `listWorkCorrections` | Correcciones recibidas | FEAT-FBK-004 | PENDING |
| `POST /corrections/{correctionId}/reply` | `replyToCorrection` | Contestar | FEAT-FBK-005 | PENDING |
| `POST /corrections/{correctionId}/rating` | `rateCorrection` | Valorarla como útil | FEAT-FBK-006 | PENDING |
| `PUT /corrections/{correctionId}/visibility` | `setCorrectionVisibility` | Ocultarla | FEAT-FBK-007 | PENDING |
| `GET /me/corrections` | `listMyCorrections` | Mis correcciones | FEAT-FBK-010 | PENDING |
| `POST /works/{workId}/rating` | `rateWork` | Valorar la obra | FEAT-FBK-002 | PENDING |

Las rutas cuelgan del **capítulo**: la corrección es por capítulo (`R-2`). El cuestionario se
lee desde `Work` (`GET /chapters/{chapterId}/questionnaire`): las preguntas son del autor,
las respuestas son de `Feedback`.

---

## `POST /chapters/{chapterId}/corrections`

**`operationId`:** `submitCorrection` · **Funcionalidad:** [`FEAT-FBK-003`](../../features/feedback/FEAT-FBK-003-answer-correction-questionnaire.md)

### Propósito

Entregar al autor el cuestionario respondido. **Es el hecho de negocio que mueve los
créditos** del producto.

### Autorización

Lector beta con acceso vigente a la obra, cuenta activada, obra en `IN_CORRECTION`. El autor
no puede corregir su propia obra.

### Reglas aplicadas

- Una corrección por lector y **capítulo**, garantizada por índice único. El mismo lector
  puede corregir varios capítulos de la misma obra.
- Todas las preguntas obligatorias respondidas, con la longitud mínima **en palabras** de
  cada una.
- Se envía contra la **versión del cuestionario que se respondió**, aunque el autor lo haya
  cambiado entre medias.
- Una vez enviada es **inmutable**: no se edita ni se borra, porque el autor ya ha pagado.

### Entrada

Respuestas, cada una referida a su pregunta. Admite `Idempotency-Key`.

### Respuesta

La corrección creada. **Sin importes de créditos**: el abono es asíncrono y lo decide
`Credits`. Devolver una cifra aquí obligaría a `Feedback` a conocer reglas que no son suyas.

### Errores específicos

| Caso | Código |
|---|---|
| Falta una respuesta obligatoria o es demasiado corta | `422` |
| Sin acceso de lector beta | `403` |
| Cuenta sin activar | `403` |
| El autor intenta corregir su obra | `403` |
| La obra ya no está en corrección | `409` |
| Ya envió una corrección de ese capítulo | `409` |
| Reintento con la misma `Idempotency-Key` | `200` con la corrección existente |

### Efectos

Publica `FeedbackSubmitted`. `Credits` carga al autor y abona al lector;
`Notification` avisa al autor. **Ambos efectos son asíncronos**: la respuesta HTTP confirma
que la corrección se ha registrado, que es lo que el lector necesita saber.

El evento **no transporta el texto de las respuestas**. Es material privado entre lector y
autor, y una cola con reintentos no es sitio para él.

---

## `PUT /chapters/{chapterId}/correction/draft`

**`operationId`:** `saveCorrectionDraft` · **Funcionalidad:** [`FEAT-FBK-011`](../../features/feedback/FEAT-FBK-011-save-correction-draft.md)

### Propósito

Conservar una corrección a medias sin entregarla.

### Autorización

El propio lector. **El autor no puede acceder a un borrador por ninguna vía**: saber que
alguien tiene una crítica a medias es información que no le corresponde.

### Reglas aplicadas

- Un borrador por lector y **capítulo**; guardar de nuevo sobrescribe.
- **No se valida obligatoriedad ni longitud mínima**: está a medias por definición. Sí el
  máximo, para no almacenar texto sin límite.

### Efectos

**Ninguno.** No publica eventos ni mueve créditos. Un borrador no es un hecho de negocio.

Es idempotente por naturaleza —el recurso es único por lector y capítulo—, así que no
necesita `Idempotency-Key`.
