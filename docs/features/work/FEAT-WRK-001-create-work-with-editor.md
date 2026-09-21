---
id: FEAT-WRK-001
title: Crear obra con el editor WYSIWYG
context: Work
concept: Manuscript
actors: [Writer]
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - _sources/use-cases.pdf#p1
endpoints: [POST /works, POST /works/{workId}/chapters]
events: [WorkCreated]
depends_on: [FEAT-USR-004]
updated: 2026-09-21
---

# FEAT-WRK-001 — Crear obra con el editor WYSIWYG

## Resumen

Un escritor crea una obra escribiendo su contenido directamente en la plataforma mediante un
editor enriquecido. La obra puede tener un único fragmento (relato) o varios (novela). Al
crearla, el sistema calcula su extensión y genera el registro de autoría.

Es la funcionalidad de entrada de todo el producto: sin obras no hay nada que leer ni que
comentar.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Writer` | Crear una obra propia | Sesión iniciada |
| Cualquier otro | Nada | — |

El autor de la obra es siempre el usuario autenticado. **No se acepta un `authorId` en la
petición**: sería una vía directa a crear obras en nombre de otro.

## Precondiciones

- El usuario tiene sesión iniciada.
- La cuenta no está eliminada ni bloqueada.

Crear una obra **no requiere créditos**. Los créditos se consumen al recibir feedback, no al
publicar.

## Reglas de negocio

- `RN-1` Toda obra tiene exactamente un autor: el usuario autenticado.
- `RN-2` Una obra tiene al menos un fragmento. Una obra sin contenido no es válida.
- `RN-3` El número de palabras se calcula a partir del contenido de todos sus fragmentos.
- `RN-4` El `TextTier` se deriva del número de palabras según la tabla de
  [`credits.md`](../../bounded-contexts/credits.md#clasificación-por-extensión-texttier).
  Nunca lo fija el usuario.
- `RN-5` Por defecto la obra se crea con `Visibility: HIDDEN` y
  `BetaReaderAccessMode: ON_REQUEST`. *(Pendiente de confirmar, `Q-1`.)*
- `RN-6` El título es obligatorio y no puede estar vacío.
- `RN-7` Al crear la obra se genera un `AuthorshipRecord`. *(Momento exacto pendiente, `W-1`.)*
- `RN-8` El contenido enriquecido se almacena de forma saneada: no se acepta HTML arbitrario.

## Flujo principal

1. El escritor abre el editor y escribe el título y el contenido.
2. Opcionalmente divide la obra en varios fragmentos (`FEAT-WRK-003`).
3. Opcionalmente indica temática, sinopsis y otros metadatos.
4. Envía la obra.
5. El sistema valida título y contenido.
6. El sistema calcula el número de palabras y deriva el `TextTier` (`FEAT-WRK-013`).
7. El sistema persiste la obra y sus fragmentos.
8. El sistema genera el registro de autoría (`FEAT-WRK-009`).
9. El sistema publica `WorkCreated`.
10. Se devuelve la obra creada.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Título vacío o ausente | Se rechaza | `422` con detalle del campo |
| Sin contenido en ningún fragmento | Se rechaza (`RN-2`) | `422` |
| Contenido con HTML no permitido | Se sanea, no se rechaza | `201` con el contenido saneado |
| Contenido que supera las 75.000 palabras | Sin definir (`C-6`) | Pendiente |
| Sin sesión | Se rechaza | `401` |
| Fallo al generar el registro de autoría | Sin definir: ¿falla la creación o se reintenta? (`Q-3`) | Pendiente |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Crear obra | `POST /works` | `createWork` |
| Añadir fragmento | `POST /works/{workId}/chapters` | `addChapter` |

Documento: [`../../api/endpoints/work.md`](../../api/endpoints/work.md).
Esquemas: `openapi/paths/works.yaml`.

Decisión pendiente (`Q-2`): si la obra se crea con todos sus fragmentos en una sola petición,
o se crea vacía y los fragmentos se añaden uno a uno. La primera opción es atómica y
respeta la frontera del agregado; la segunda encaja mejor con un editor que guarda de forma
incremental.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `WorkCreated` | Tras persistir la obra | `workId`, `authorId`, `title`, `wordCount`, `textTier`, `accessMode`, `visibility` |

El contenido de la obra **no viaja en el evento**.

**Consume**: ninguno.

## Efectos en créditos

Ninguno. Crear una obra no mueve créditos.

`Credits` puede consumir `WorkCreated` en el futuro si se decide congelar el `TextTier` de
referencia en ese momento (`C-7`).

## Modelo de datos afectado

| Elemento | Cambio |
|---|---|
| `work` | Nuevo registro |
| `chapter` | Uno o varios registros |
| `authorship_record` | Nuevo registro inmutable |

Índices necesarios: `work(author_id)`, `work(genre, created_at)`, `chapter(work_id, position)`.

## Diseño (Figma)

Pendiente. Se completará con la página correspondiente.

Aspectos que el diseño debe resolver: capacidades exactas del editor, si hay guardado
automático, cómo se crean y ordenan los fragmentos, y qué metadatos se piden al crear frente
a después.

## Criterios de aceptación

- [ ] Un usuario autenticado crea una obra con título y contenido y recibe `201` con su `workId`.
- [ ] La obra creada tiene como autor al usuario autenticado, aunque la petición incluya otro `authorId`.
- [ ] Una obra sin título se rechaza con `422`.
- [ ] Una obra sin contenido se rechaza con `422`.
- [ ] El `wordCount` devuelto coincide con el número de palabras del contenido.
- [ ] El `textTier` devuelto corresponde al tramo del `wordCount` según la tabla.
- [ ] El usuario no puede fijar `wordCount` ni `textTier` en la petición.
- [ ] Se genera un `AuthorshipRecord` asociado a la obra.
- [ ] Se publica `WorkCreated` y su payload no contiene el contenido de la obra.
- [ ] Una petición sin sesión recibe `401`.
- [ ] El contenido enriquecido se almacena saneado frente a inyección de HTML y scripts.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| Q-1 | ¿Cuáles son la visibilidad y la modalidad de acceso por defecto? | Una obra visible por error expondría contenido inédito |
| Q-2 | ¿Creación atómica con fragmentos o incremental? | Diseño del endpoint y del agregado |
| Q-3 | ¿Qué ocurre si falla la generación del registro de autoría? | ¿Es parte de la transacción o asíncrono? |
| Q-4 | ¿Qué formato enriquecido se admite y cómo se almacena (HTML saneado, Markdown, estructura propia)? | Persistencia, saneado y cálculo de palabras |
| Q-5 | ¿Hay borradores con guardado automático? | Cambia el ciclo de vida de la obra (`W-5`) |
| Q-6 | ¿Qué metadatos son obligatorios: temática, sinopsis, portada? | Validación y búsqueda |

## Estado

**Especificación:** `DRAFT`. Para llegar a `APPROVED` hacen falta: la página de Figma del
editor y las respuestas a `Q-1`, `Q-2` y `Q-4`.

**Implementación:** `TODO`.
