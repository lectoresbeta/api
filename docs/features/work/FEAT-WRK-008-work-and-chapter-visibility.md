---
id: FEAT-WRK-008
title: Configurar la visibilidad de obra y capítulos
context: Work
concept: Chapter
actors: [Writer]
spec_status: REVIEW
impl_status: PARTIAL
priority: P1
sources:
  - conversation:2026-09-25 (bloque de ciclo de vida de la obra)
  - docs/glossary.md (Visibilidad)
endpoints:
  - PUT /chapters/{chapterId}/visibility
events: [ChapterVisibilityChanged]
depends_on: [FEAT-WRK-016, FEAT-WRK-004]
updated: 2026-09-25
---

# FEAT-WRK-008 — Configurar la visibilidad de obra y capítulos

## Resumen

El autor decide qué capítulos de una obra publicada se ven y cuáles no. Sirve para publicar
por entregas, para retirar un capítulo mientras se reescribe y para dejar de enseñar algo sin
borrarlo.

## Lo primero: la obra no tiene visibilidad

El enunciado original decía «obra y fragmentos» y arrastraba una confusión que conviene
cerrar aquí. **La obra tiene estado, no visibilidad**
([`FEAT-WRK-016`](FEAT-WRK-016-work-status.md)):

| Nivel | Qué lo gobierna | Valores |
|---|---|---|
| Obra | `WorkStatus` | `DRAFT` · `PUBLISHED` · `IN_CORRECTION` (y `BLOCKED`, que impone moderación) |
| Capítulo | `ChapterVisibility` | `VISIBLE` · `HIDDEN` |

El glosario ya lo dice —«visibilidad: **solo aplicable al fragmento**; en la obra queda
sustituida por `WorkStatus`»— y esta ficha lo hace explícito para que nadie añada un tercer
interruptor a la obra.

Así que lo único nuevo aquí es **la visibilidad del capítulo**, que el modelo ya tiene y
ninguna operación permite cambiar.

## Por qué ocultar y no borrar

Ocultar un capítulo corregido conserva las correcciones que alguien escribió sobre él y el
rastro de lo que se pagó. Borrarlo destruiría las dos cosas. Es la misma razón por la que un
comentario no se elimina, se oculta
([`bounded-contexts/feedback`](../../bounded-contexts/feedback.md) `RN-3`).

## Reglas de negocio

- `RN-1` Solo el autor cambia la visibilidad de sus capítulos.
- `RN-2` Un capítulo `HIDDEN` **solo lo ve su autor**. Para cualquier otra persona no existe:
  responde `404`, no `403` ([`errores`](../../api/conventions/errors.md)).
- `RN-3` Un capítulo oculto **no se puede corregir**, y el panel de corrección no se abre
  sobre él.
- `RN-4` Ocultar un capítulo **no cancela ni invalida las correcciones ya entregadas** sobre
  él. Su autor las sigue leyendo y quien las escribió las sigue viendo en «Mis correcciones».
- `RN-5` Un borrador de corrección sobre un capítulo que se oculta **queda inservible**: no se
  puede entregar mientras siga oculto, y se avisa a quien lo estaba escribiendo. No se
  descarta solo: el texto es suyo.
- `RN-6` Ocultar un capítulo **descuenta sus palabras** del recuento visible de la obra, y con
  ellas su precio deja de ofrecerse.
- `RN-7` La visibilidad es **independiente del estado de la obra**. Un capítulo oculto de una
  obra `IN_CORRECTION` sigue oculto.
- `RN-8` Un capítulo **bloqueado por moderación** no cambia de visibilidad por esta vía: el
  bloqueo lo levanta un moderador y nadie más
  ([`FEAT-MOD-003`](../moderation/FEAT-MOD-003-block-work.md) `RN-7`).
- `RN-9` Una obra publicada **con todos sus capítulos ocultos** sigue existiendo y se ve
  vacía. No se despublica sola: un efecto colateral que cambia el estado de la obra es un
  efecto que el autor no pidió.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| No es el autor | Se rechaza sin revelar nada | `404` |
| Capítulo bloqueado por moderación | Se rechaza | `409` `CHAPTER_BLOCKED` |
| Ya estaba en esa visibilidad | Se acepta y no ocurre nada | `204` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Cambiar la visibilidad de un capítulo | `PUT /chapters/{chapterId}/visibility` | `setChapterVisibility` |

`PUT` porque fija un estado, como `changeWorkStatus` y `setAccessMode`. El estado de la obra
sigue teniendo su propia operación y no se toca.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `ChapterVisibilityChanged` | Cambia | `chapterId`, `workId`, `authorId`, `visibility`, `changedAt` |

Lo escuchan `Credits`, que deja de ofrecer o vuelve a ofrecer ese capítulo, y `Feedback`, que
avisa a quien tuviera un borrador en curso.

## Criterios de aceptación

- [ ] El autor oculta y vuelve a mostrar un capítulo.
- [ ] Un capítulo oculto responde `404` a cualquiera que no sea su autor.
- [ ] Un capítulo oculto no admite empezar una corrección.
- [ ] Las correcciones entregadas sobre un capítulo oculto se siguen leyendo por las dos partes.
- [ ] Quien tenía un borrador sobre él recibe aviso y no puede entregarlo.
- [ ] Las palabras del capítulo oculto no cuentan en el recuento visible de la obra.
- [ ] Un capítulo bloqueado por moderación no cambia de visibilidad por esta vía.
- [ ] Una obra con todos los capítulos ocultos sigue publicada.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| W-29 | ¿Publicación programada de un capítulo a una fecha? | Es lo que pide la publicación por entregas, y hoy se resuelve a mano |
| W-30 | ¿Ve el lector que «faltan» capítulos, o la obra parece completa? | Un hueco silencioso confunde a quien lee por entregas |

## Estado

**Especificación:** `REVIEW` — completa, pendiente de validación. Resuelve de paso la
ambigüedad «visibilidad de la obra», que no existe: la obra tiene estado.

**Implementación:** `PARTIAL`. `ChapterVisibility` existe en el modelo y las lecturas ya la
respetan —`GetChapterHandler` y `GetWorkHandler` la comprueban—. **Falta** la operación que
la cambia, el hecho que publica y el aviso a quien tenga un borrador.
