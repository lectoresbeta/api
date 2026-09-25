---
id: FEAT-WRK-003
title: Estructurar la obra en capítulos
context: Work
concept: Chapter
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P0
sources:
  - conversation:2026-09-25 (bloque de ciclo de vida de la obra)
  - docs/glossary.md (Fragmento / Parte / Capítulo)
endpoints:
  - POST /works/{workId}/chapters
  - PUT /works/{workId}/chapters/order
  - DELETE /chapters/{chapterId}
events: [ChapterAdded, ChaptersReordered, ChapterRemoved]
depends_on: [FEAT-WRK-001, FEAT-WRK-005]
updated: 2026-09-25
---

# FEAT-WRK-003 — Estructurar la obra en capítulos

## Resumen

Una obra es uno o varios capítulos en un orden. Hoy solo se puede **añadir al final**: no hay
forma de intercalar uno, de moverlo ni de quitarlo. Una novela se escribe raramente en orden
y nunca queda bien a la primera.

> La interfaz los llama **«Partes»** en la tarjeta de obra («1/3 Partes») y **«capítulo»** en
> la pantalla de lectura. El identificador es `Chapter` ([`glosario`](../../glossary.md)).

## Por qué el orden es un dato y no una opinión

El capítulo es **la unidad que se corrige y la que se cobra**: el precio es por capítulo, y
las preguntas de alcance `LAST_CHAPTER` solo aparecen en el último
([`FEAT-WRK-014`](FEAT-WRK-014-configure-questionnaire.md) `W-17`).

Reordenar, por tanto, **cambia qué preguntas se hacen y dónde**. No es cosmético: mover un
capítulo al final hace que a partir de ese momento se le pregunte por el desenlace de la obra.

Lo que no cambia es lo ya escrito. Una corrección apunta a un `chapterId`, no a una posición,
así que reordenar nunca la desplaza ni la deja huérfana.

## Reglas de negocio

- `RN-1` Solo el autor estructura su obra.
- `RN-2` Las posiciones son **consecutivas desde 1** y no admiten huecos. Se recalculan
  enteras en cada cambio: una lista de capítulos con un hueco es una lista rota.
- `RN-3` Un capítulo nuevo se puede insertar **en cualquier posición**, no solo al final.
- `RN-4` Reordenar **no toca el contenido** y, por tanto, no versiona nada
  ([`FEAT-WRK-005`](FEAT-WRK-005-edit-work-and-chapter.md) `RN-2`).
- `RN-5` Reordenar **sí puede cambiar el precio** de dos capítulos: el que deja de ser el
  último y el que pasa a serlo, porque cambian las preguntas que les corresponden.
- `RN-6` Un capítulo **con correcciones entregadas o en curso no se elimina**; se oculta
  ([`FEAT-WRK-008`](FEAT-WRK-008-work-and-chapter-visibility.md)). Borrarlo destruiría el
  trabajo de quien lo corrigió y el rastro de un cobro.
- `RN-7` Un capítulo **bloqueado por moderación no se elimina ni se mueve**.
- `RN-8` Una obra **no puede quedarse sin capítulos** si está publicada: eliminar el último
  de una obra visible se rechaza. En borrador, sí.
- `RN-9` El recuento de palabras de la obra es la suma de los de sus capítulos, y se
  recalcula en cada alta y cada baja.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| No es el autor | Se rechaza sin revelar nada | `404` |
| La lista de orden no contiene exactamente los capítulos de la obra | Se rechaza entera | `422` `CHAPTER_ORDER_INCOMPLETE` |
| Eliminar un capítulo con correcciones | Se rechaza y se sugiere ocultarlo | `409` `CHAPTER_HAS_CORRECTIONS` |
| Eliminar el último capítulo de una obra publicada | Se rechaza | `409` `WORK_NEEDS_A_CHAPTER` |
| Capítulo bloqueado por moderación | Se rechaza | `409` `CHAPTER_BLOCKED` |

Que el reordenamiento se rechace **entero** cuando la lista no cuadra es deliberado: aplicar
la mitad de un reordenamiento deja la obra en un estado que el autor no pidió y no sabe leer.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Añadir un capítulo | `POST /works/{workId}/chapters` | `addChapter` |
| Reordenar | `PUT /works/{workId}/chapters/order` | `reorderChapters` |
| Eliminar un capítulo | `DELETE /chapters/{chapterId}` | `removeChapter` |

`addChapter` ya existe; gana un `position` opcional. Reordenar recibe **la lista completa de
identificadores en el orden deseado**, no un «mueve este de la 3 a la 7»: la lista entera es
idempotente y sobrevive a que dos pestañas hagan lo mismo a la vez.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `ChapterAdded` | Se añade un capítulo | `chapterId`, `workId`, `authorId`, `position` |
| `ChaptersReordered` | Cambia el orden | `workId`, `authorId`, `order` (lista de `chapterId`) |
| `ChapterRemoved` | Se elimina | `chapterId`, `workId`, `authorId` |

`ChaptersReordered` importa a `Credits`: el último capítulo puede ser otro, y con él las
preguntas que se cobran.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `work_ctx.chapter` | Índice único `(work_id, position)` **diferido**, para poder permutar dentro de una transacción sin pasar por posiciones duplicadas |

## Criterios de aceptación

- [x] Se añade un capítulo en una posición intermedia y el resto se desplaza.
- [x] Se reordena la obra entera con una sola operación.
- [x] Una lista de orden incompleta o con intrusos se rechaza sin aplicar nada.
- [x] Las posiciones quedan consecutivas desde 1 después de cualquier operación.
- [x] Una corrección entregada sigue apuntando a su capítulo después de reordenar.
- [x] Reordenar reprecia el capítulo que deja de ser el último y el que pasa a serlo.
- [x] Un capítulo con correcciones no se puede eliminar.
- [x] Una obra publicada no se queda sin capítulos.
- [x] El recuento de palabras de la obra cuadra con la suma de los capítulos.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| W-27 | ¿Se pueden agrupar capítulos en partes o secciones? | Una novela larga lo pide; el modelo hoy es plano |
| W-28 | ¿Dividir un capítulo en dos, o unir dos en uno? | Es lo que de verdad hace un autor al reestructurar, y no se resuelve con reordenar |
| W-34 | Sin índice único en `(work_id, position)`, ¿basta la transacción para mantenerlas consecutivas? | Hoy sí, porque solo el autor reordena su obra. Con edición delegada (`W-23`) dejaría de bastar |

## Estado

**Especificación:** `APPROVED` (2026-09-25).

**Implementación:** `DONE` (2026-09-25). Insertar en una posición, reordenar con la lista
entera y eliminar, con el recálculo de posiciones y el reprecio del capítulo que deja de ser
el último y del que pasa a serlo — que es lo que hace que `RN-5` sea verdad y no una
intención.

**Que un capítulo tenga correcciones se responde sin salir del contexto.** La versión del
capítulo solo sube al archivar una que alguien leyó
([`FEAT-WRK-005`](FEAT-WRK-005-edit-work-and-chapter.md)), así que estar por encima de la
primera —o tener la actual marcada— equivale exactamente a «aquí hubo correcciones».
Preguntárselo a `Feedback` daría el mismo sí con una llamada síncrona de más.

**Una desviación de `RN-2`, y conviene que se vea.** El índice único diferido sobre
`(work_id, position)` **no está**: Doctrine no modela restricciones `DEFERRABLE` en su mapeo,
así que añadirla dejaría el esquema permanentemente «fuera de sincronía» para
`doctrine:schema:validate`, y un despliegue que siempre avisa es un despliegue que nadie mira.
Lo que mantiene las posiciones consecutivas hoy es el recálculo entero dentro de una
transacción y el orden de las escrituras. Queda como `W-34`.

El hecho `ChapterAdded` de la tabla de eventos **no se publica**, y es deliberado:
`ChapterContentUpdated` ya anuncia un capítulo nuevo —es lo que `Credits` usa para estrenarle
precio— y dos nombres para el mismo hecho obligan a cada consumidor a decidir cuál escuchar.

`W-27` y `W-28` siguen abiertas.
