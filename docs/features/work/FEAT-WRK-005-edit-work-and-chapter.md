---
id: FEAT-WRK-005
title: Editar una obra o un capítulo
context: Work
concept: Chapter
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P0
sources:
  - conversation:2026-09-25 (bloque de ciclo de vida de la obra)
  - docs/ui/my-works.md
endpoints:
  - PATCH /works/{workId}
  - PUT /chapters/{chapterId}
events: [ChapterContentUpdated, WorkUpdated]
depends_on: [FEAT-WRK-001, FEAT-WRK-016, FEAT-FBK-003]
updated: 2026-09-25
---

# FEAT-WRK-005 — Editar una obra o un capítulo

## Resumen

Hoy una obra se crea y ya no se toca. No hay forma de corregir una errata, cambiar una
sinopsis ni reescribir un párrafo: lo único que se puede hacer con un capítulo es añadir otro.

Esto lo arregla, y al hacerlo resuelve un problema que no se ve: **`Credits` recalcula el
precio de un capítulo cuando su texto cambia**, y ese cálculo nunca se ha ejecutado, porque
el texto nunca cambia. `ChapterContentUpdated` existe, viaja y tiene consumidor desde el
primer día; solo le faltaba ocurrir.

## El problema de fondo: alguien ya leyó ese texto

Editar un capítulo no es editar un documento privado. Puede haber:

- **alguien corrigiéndolo ahora mismo**, que lleva media hora escribiendo sobre un párrafo
  que está a punto de desaparecer;
- **correcciones ya entregadas y pagadas**, que hablan de un texto que dejaría de existir.

Una corrección que dice «el diálogo de la página tres no suena» y apunta a un texto donde ya
no hay ningún diálogo **no es que envejezca: es que deja de tener sentido**, y el autor pagó
por ella.

De ahí la decisión: **el capítulo se versiona**. Es el mismo movimiento que ya hace
`Correction` con `questionnaireVersion`, que existe exactamente por esto —para que el autor
sepa a qué preguntas responde lo que le llega— aplicado ahora al contenido.

## Cómo funciona el versionado

**Se guarda una copia solo cuando alguien ha leído esa versión.** Un autor que teclea y
guarda veinte veces antes de abrir su obra a nadie no deja veinte filas; la primera edición
después de que alguien empiece a corregir, sí.

```text
Capítulo v1 ─── nadie lo ha abierto ──▶ editar sobrescribe. Sigue siendo v1
Capítulo v1 ─── alguien empieza a corregir ──▶ v1 queda «leída»
Capítulo v1 ─── editar ──▶ copia de v1 al archivo, el capítulo pasa a v2
```

Lo que marca una versión como leída es `CorrectionStarted`, que este contexto pasa a
consumir. No hace falta preguntarle nada a `Feedback`: el hecho ya viaja.

**Quien está corrigiendo sigue viendo el texto que empezó a leer.** Es coherente con lo que
ya decide [`decision:0006`](../../decisions/0006-credit-system.md) `RN-7`: al empezar una
corrección se congelan el cuestionario y el precio, porque quien acepta un trabajo bajo unas
condiciones las conserva. El texto es la tercera de esas condiciones y la más importante.

**Se versiona el contenido y el título del capítulo.** Lo que se corrige y nada más. El
título, la sinopsis y los géneros de la obra se editan libremente: nadie corrige una
sinopsis, y arrastrar la obra entera al versionado duplicaría el modelo por un dato que no lo
necesita.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| Autor | Editar su obra y sus capítulos | Siempre, en cualquier estado de la obra |
| Cualquier otro | Nada | Ni un lector beta con acceso vigente |

## Reglas de negocio

- `RN-1` Solo el autor edita. No hay edición delegada ni colaborativa (`W-23`).
- `RN-2` Editar el **contenido o el título de un capítulo** cuya versión actual ha sido leída
  **archiva esa versión y crea una nueva**. Si nadie la ha leído, se sobrescribe y el número
  de versión no cambia.
- `RN-3` Una versión se considera **leída** cuando alguien ha empezado una corrección sobre
  ella. No basta con que el capítulo sea visible: leer sin corregir no congela nada.
- `RN-4` Una corrección **conserva la versión que su autor leyó**, tanto mientras se escribe
  como después de entregarse. El panel de corrección no cambia de texto bajo quien escribe.
- `RN-5` Editar el contenido **recalcula el recuento de palabras** y publica
  `ChapterContentUpdated`, que es lo que lleva a `Credits` a reprecio. El precio nuevo rige
  para las correcciones que **empiecen después**; las ya empezadas conservan su cotización.
- `RN-6` Editar **no cambia el estado de la obra**. Un capítulo de una obra `IN_CORRECTION`
  se edita sin cerrar la puerta a nadie.
- `RN-7` Un capítulo **bloqueado por moderación no se edita**
  ([`FEAT-MOD-003`](../moderation/FEAT-MOD-003-block-work.md) `RN-6`): el contenido reclamado
  se conserva tal cual, porque es justo lo que puede hacer falta si alguien discute la
  decisión.
- `RN-8` El contenido pasa por el **mismo saneado** que al crearlo
  ([`FEAT-WRK-001`](FEAT-WRK-001-create-work-with-editor.md)). Una puerta de edición que
  acepta lo que la de creación rechaza es la puerta que se usa.
- `RN-9` Editar la obra (título, sinopsis) **no versiona nada** y no toca el precio.
- `RN-10` Una edición que no cambia nada **no es una edición**: ni versiona, ni publica hecho,
  ni actualiza la fecha. Guardar sin tocar es lo más frecuente que hace un editor de texto.

## Flujo principal

1. El autor abre un capítulo suyo y edita el texto.
2. El sistema sanea el HTML y recalcula las palabras.
3. Si la versión actual fue leída, se archiva y el capítulo pasa a la siguiente.
4. Se publica `ChapterContentUpdated`.
5. `Credits` reprecia el capítulo; `Work` actualiza su propio recuento y el de la obra.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| No es el autor | Se rechaza sin revelar nada | `404` |
| La obra o el capítulo no existen | Igual | `404` |
| El capítulo está bloqueado por moderación | Se rechaza | `409` `CHAPTER_BLOCKED` |
| El contenido supera el máximo | Se rechaza | `413` |
| El contenido queda vacío tras sanear | Se rechaza | `422` |
| Nada ha cambiado | Se acepta y no ocurre nada | `204` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Editar la obra | `PATCH /works/{workId}` | `updateWork` |
| Editar un capítulo | `PUT /chapters/{chapterId}` | `updateChapter` |

`PATCH` para la obra porque se tocan campos sueltos —título, sinopsis— y `PUT` para el
capítulo porque se sustituye el texto entero, que es lo que hace un editor al guardar.

Géneros y clasificación de contenido tienen ya sus propias operaciones (`setWorkGenres`,
`setWorkContentRating`) y no se duplican aquí.

La **portada** queda fuera: depende de `FEAT-USR-028` `F-5` y `P-13`, que siguen abiertas.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `ChapterContentUpdated` | Cambia el texto de un capítulo | `chapterId`, `workId`, `authorId`, `position`, `wordCount`, `version`, `updatedAt` |
| `WorkUpdated` | Cambian título o sinopsis | `workId`, `authorId`, `updatedAt`. **Sin el texto** |

`ChapterContentUpdated` gana un campo, `version`, y es el único cambio de contrato: los
consumidores que no lo miren siguen funcionando igual.

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `CorrectionStarted` | `Feedback` | Marca la versión actual del capítulo como **leída** |

## Efectos en créditos

Se publica el hecho «este capítulo tiene otro texto». **Ningún importe**: cuánto vale
corregirlo lo decide `Credits`, como siempre.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `work_ctx.chapter` | `version INT NOT NULL DEFAULT 1`, `current_version_read_at TIMESTAMPTZ NULL` |
| `work_ctx.chapter_version` | **Nueva.** `chapter_id`, `version`, `title`, `content_html`, `content_text`, `word_count`, `archived_at`. Única por `(chapter_id, version)` |
| `feedback_ctx.correction` | `chapter_version INT NULL`. Nulo en las correcciones anteriores a esta funcionalidad, que no tienen versión que recordar |

`chapter_version` es **solo de lectura una vez escrita**: archivar es copiar, nunca editar.

`CorrectionBrief`, el contrato con el que `Feedback` abre un panel de corrección, gana
`chapterVersion`: es el dato que permite congelar el texto sin que `Feedback` sepa nada de
cómo se versiona.

## Criterios de aceptación

- [x] El autor edita el título y la sinopsis de su obra.
- [x] El autor edita el título y el contenido de un capítulo.
- [x] Editar un capítulo que nadie ha empezado a corregir **no** crea una versión.
- [x] Editar un capítulo con una corrección empezada **sí** la crea, y el número sube.
- [x] Quien tenía una corrección en curso sigue viendo el texto que empezó a leer.
- [x] Una corrección entregada recuerda la versión sobre la que se escribió.
- [x] Editar recalcula las palabras y reprecia el capítulo para las correcciones futuras.
- [x] Una corrección ya empezada conserva su cotización aunque el texto crezca.
- [x] Un capítulo bloqueado por moderación no se puede editar.
- [x] Guardar sin cambios no versiona, no publica nada y no cambia la fecha.
- [x] Nadie que no sea el autor puede editar, y recibe `404`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| W-23 | ¿Habrá edición colaborativa o delegada (un editor, un corrector de estilo)? | Cambia la autorización de raíz |
| W-24 | Entre que alguien pulsa «empezar corrección» y llega el hecho, el autor puede guardar. ¿Se acepta esa ventana? | Quien empezó en ese instante vería el texto nuevo. La alternativa es preguntarle a `Feedback` de forma síncrona en cada guardado |
| W-25 | ¿Se purgan las versiones que ninguna corrección referencia? | Sin purga, una obra muy editada acumula copias que nadie leerá |
| W-26 | ¿Se avisa al autor de que su edición afecta a una corrección en curso? | Hoy edita a ciegas |

## Estado

**Especificación:** `APPROVED` (2026-09-25). Las decisiones de diseño
del versionado (cuándo se crea, qué texto ve quien corrige, quién lee una versión antigua y
qué entra en ella) se tomaron el 2026-09-25.

**Implementación:** `DONE` (2026-09-25).

El versionado quedó tal y como se decidió: copia solo cuando alguien ha empezado a corregir
esa versión, texto congelado para quien está escribiendo, y la corrección entregada enlazando
al texto sobre el que se escribió.

**Lo que marca una versión como leída llega por evento.** `Work` consume `CorrectionStarted`
—un hecho que ya viajaba para `Credits` y `Reading`— y marca el capítulo. La alternativa era
preguntarle a `Feedback` de forma síncrona en cada guardado, que convertiría cada pulsación de
«Guardar» en una llamada a otro contexto. Queda la ventana de `W-24`, sin cerrar y sin
esconder.

Dos detalles que solo aparecen al escribirlo:

- **el `DEFAULT 1` de la columna nueva hay que retirarlo en la misma migración.** Sirve para
  las filas que ya existen y, si se queda, `doctrine:schema:validate` canta una diferencia con
  el mapeo en cada despliegue;
- **el recuento de la obra se ajusta por diferencia**, restando las palabras que tenía el
  capítulo y sumando las que tiene ahora. Volver a sumarlas todas leería un estado en el que
  el capítulo recién tocado todavía no es visible.

Con esto, `FEAT-FBK-004` `RN-8` queda entero: `getCorrectedChapterText` sirve la versión
archivada cuando la hay, sin que su API cambiara.

`W-23`, `W-24`, `W-25` y `W-26` siguen abiertas. `W-25` (purgar las versiones que ninguna
corrección referencia) gana importancia ahora que la tabla existe y crece.
