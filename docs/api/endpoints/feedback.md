# Endpoints — `Feedback`

> Estado: **parcial**. Solo están especificadas las operaciones de corrección; el resto del
> contexto sigue pendiente.

## Resumen

| Método y ruta | `operationId` | Propósito | Funcionalidad | Estado |
|---|---|---|---|---|
| `GET /api/v1/chapters/{chapterId}/questionnaire` | `getChapterQuestionnaire` | Cuestionario a responder y lo ya escrito | FEAT-FBK-003 | **Implementado** |
| `POST /api/v1/chapters/{chapterId}/corrections/start` | `startCorrection` | Empezar: ocupa sitio y fija el precio | FEAT-FBK-003 | **Implementado** |
| `POST /api/v1/chapters/{chapterId}/corrections` | `submitCorrection` | Enviar la corrección | FEAT-FBK-003 | **Implementado** |
| `PUT /api/v1/chapters/{chapterId}/correction/draft` | `saveCorrectionDraft` | Guardar borrador | FEAT-FBK-011 | **Implementado** |
| `DELETE /api/v1/chapters/{chapterId}/correction/draft` | `discardCorrectionDraft` | Descartar borrador | FEAT-FBK-011 | **Implementado** |
| `GET /works/{workId}/corrections` | `listWorkCorrections` | Correcciones recibidas | FEAT-FBK-004 | PENDING |
| `POST /corrections/{correctionId}/reply` | `replyToCorrection` | Contestar | FEAT-FBK-005 | PENDING |
| `POST /corrections/{correctionId}/rating` | `rateCorrection` | Valorarla como útil | FEAT-FBK-006 | PENDING |
| `PUT /corrections/{correctionId}/visibility` | `setCorrectionVisibility` | Ocultarla | FEAT-FBK-007 | PENDING |
| `GET /me/corrections` | `listMyCorrections` | Mis correcciones | FEAT-FBK-010 | PENDING |
| `POST /works/{workId}/rating` | `rateWork` | Valorar la obra | FEAT-FBK-002 | PENDING |

Las rutas cuelgan del **capítulo**: la corrección es por capítulo (`R-2`). Las preguntas son
del autor y viven en `Work`; las respuestas son de `Feedback`, y el cuestionario del lector
lo sirve este contexto porque tiene que venir con el borrador dentro.

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

Respuestas, cada una referida a su pregunta.

`Idempotency-Key` **todavía no se implementa** (no lo hace ninguna operación del proyecto).
Mientras tanto, un segundo envío del mismo capítulo responde `409` en vez de devolver la
corrección existente: se prefiere un error claro a aceptar en silencio un juego de respuestas
distinto como si fuera un reintento.

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
| Reintento con la misma `Idempotency-Key` | `200` con la corrección existente. **Pendiente**: hoy `409` |

### Efectos

Publica `FeedbackSubmitted`. `Credits` carga al autor y abona al lector;
`Notification` avisa al autor. **Ambos efectos son asíncronos**: la respuesta HTTP confirma
que la corrección se ha registrado, que es lo que el lector necesita saber.

El evento **no transporta el texto de las respuestas**. Es material privado entre lector y
autor, y una cola con reintentos no es sitio para él.

---

## `POST /api/v1/chapters/{chapterId}/corrections/start`

**`operationId`:** `startCorrection` · **Funcionalidad:** [`FEAT-FBK-003`](../../features/feedback/FEAT-FBK-003-answer-correction-questionnaire.md)

### Propósito

«Empezar corrección»: **ocupa uno de los tres sitios del capítulo y fija el precio** que se
cobrará y se abonará al entregarla
([`FEAT-CRD-009`](../../features/credits/FEAT-CRD-009-balance-check-on-correction-start.md)).

### Por qué existe, si la ficha listaba tres operaciones

Porque **empezar es una escritura**. La ficha hacía coincidir «abrir el panel» con leer el
cuestionario, y eso pone dos efectos reales —consumir uno de los tres sitios del capítulo y
cotizar un precio— detrás de un método que HTTP define como seguro. Las consecuencias no son
teóricas:

- recargar la página cerraría el capítulo para el tercer lector que llegara;
- el guardián de cuenta activada solo cubre los métodos de escritura
  ([`FEAT-USR-025`](../../features/user/FEAT-USR-025-block-writes-until-activation.md)), así
  que una cuenta sin activar podría ocupar sitio;
- un prefetch del navegador empezaría correcciones solo.

Separarlo deja el `GET` como lo que es —una lectura— y hace explícito el momento en que el
lector se compromete.

### Autorización

Cuenta activada. En obra `PUBLIC`, **esta llamada es el permiso**: concede el acceso de lector
beta sin solicitud previa (`R-4`). En `ON_REQUEST` y `PRIVATE` hace falta tenerlo de antes.

### Reglas aplicadas

- No retiene créditos: el saldo del autor sigue íntegro y la cotización no caduca.
- El capítulo debe estar admitiendo correcciones, que es lo que dice la proyección de
  `ChapterCorrectabilityChanged`.
- Llamarla dos veces devuelve la misma corrección.

### Errores específicos

| Caso | Código |
|---|---|
| Sin acceso de lector beta, o es el autor | `403` |
| Cuenta sin activar | `403` |
| La obra no está en corrección o no tiene cuestionario | `409` |
| El capítulo no admite correcciones ahora mismo | `409` con `CHAPTER_NOT_TAKING_CORRECTIONS` |
| Ya envió una corrección de ese capítulo | `409` |

`CHAPTER_NOT_TAKING_CORRECTIONS` **no dice si es por saldo o por cupo**: distinguirlos
contaría a un desconocido cómo va el saldo del autor.

### Efectos

Publica `CorrectionStarted`. `Credits` anota el precio; nada más ocurre, y nadie espera.

---

## `GET /api/v1/chapters/{chapterId}/questionnaire`

**`operationId`:** `getChapterQuestionnaire` · **Funcionalidad:** [`FEAT-FBK-003`](../../features/feedback/FEAT-FBK-003-answer-correction-questionnaire.md)

### Propósito

Lo que el autor pregunta sobre **este** capítulo y lo que quien llama hubiera escrito ya, en
una sola llamada: qué preguntas aplican depende del capítulo y el borrador es de ese
capítulo, así que separarlas haría que abrir el panel pudiera llegar a medias.

Es la otra mitad de `getWorkQuestionnaire`, que es la vista del autor. Describen el mismo
objeto y no son la misma representación.

### Autorización

La misma que `startCorrection`, y por el mismo motivo: los enunciados nombran personajes y
suelen adelantar el final, así que quien no puede corregir tampoco los ve.

### Reglas aplicadas

- Las preguntas vienen **filtradas por capítulo**: una de alcance `LAST_CHAPTER` no aparece en
  el capítulo 3 ([`FEAT-WRK-014`](../../features/work/FEAT-WRK-014-configure-questionnaire.md), `W-17`).
- Es una **lectura pura**: no ocupa sitio ni fija precio.

### Efectos

Ninguno.

---

## `PUT /api/v1/chapters/{chapterId}/correction/draft`

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
- Guardar en un capítulo que no se había empezado **lo empieza**, con los efectos de
  `startCorrection`. Responde `201` la primera vez y `200` las siguientes.

### Respuesta

`correctionId`, la versión que se está respondiendo y `questionnaireVersionChanged`: si el
autor publicó una versión nueva mientras el lector escribía, lo que sigue respondiendo es la
antigua, y tiene derecho a enterarse antes de seguir.

### Efectos

**Guardar no publica nada y no mueve créditos.** Un borrador no es un hecho de negocio, y
avisar de que alguien está escribiendo sería justo lo que `RN-4` evita.

Es idempotente por naturaleza —el recurso es único por lector y capítulo—, así que no
necesita `Idempotency-Key`.

---

## `DELETE /api/v1/chapters/{chapterId}/correction/draft`

**`operationId`:** `discardCorrectionDraft` · **Funcionalidad:** [`FEAT-FBK-011`](../../features/feedback/FEAT-FBK-011-save-correction-draft.md)

### Propósito

Descartar el borrador. **Sí es un hecho de negocio**, al revés que guardarlo, porque empezar
había ocupado dos cosas que ahora se sueltan.

### Efectos

Publica `CorrectionDraftDiscarded`, que sueltan dos contextos a la vez:

| Consumidor | Qué deshace |
|---|---|
| `Credits` | La cotización del precio, y con ella **uno de los tres sitios** de corrección del capítulo ([`FEAT-CRD-009`](../../features/credits/FEAT-CRD-009-balance-check-on-correction-start.md)) |
| `Reading` | El acceso de lector beta, si nació de esta corrección y quien la descarta nunca entregó nada de esa obra ([`FEAT-RDG-001`](../../features/reading/FEAT-RDG-001-become-beta-reader-by-correcting.md)) |

### Errores específicos

| Caso | Código |
|---|---|
| No hay borrador | `204`. Lo que se pedía ya se cumple |
| La corrección ya se entregó | `409` con `CORRECTION_ALREADY_SUBMITTED` |

**No se exige seguir pudiendo corregir.** Cerrarle la salida a quien perdió el acceso, o cuya
obra se cerró, dejaría borradores imposibles de borrar ocupando sitio para siempre.
