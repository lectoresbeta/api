# Endpoints — `Work`

> Estado: **esqueleto**. Las rutas son una propuesta.

## Resumen

| Método y ruta | `operationId` | Propósito | Funcionalidad | Estado |
|---|---|---|---|---|
| `POST /works` | `createWork` | Crear obra | FEAT-WRK-001 | DRAFT |
| `POST /works/uploads` | `uploadManuscript` | Crear obra desde fichero | FEAT-WRK-002 | PENDING |
| `GET /works/{workId}` | `getWork` | Metadatos de la obra | FEAT-WRK-004 | PENDING |
| `GET /works/{workId}/content` | `getWorkContent` | Contenido completo | FEAT-WRK-004 | PENDING |
| `PATCH /works/{workId}` | `updateWork` | Editar metadatos | FEAT-WRK-005 | PENDING |
| `DELETE /works/{workId}` | `deleteWork` | Eliminar obra | FEAT-WRK-006 | PENDING |
| `GET /works` | `listWorks` | Catálogo: sección «Leer» | FEAT-WRK-012 | DRAFT |
| `GET /me/works` | `listMyWorks` | Obras propias | FEAT-WRK-004 | PENDING |
| `POST /works/{workId}/chapters` | `addChapter` | Añadir fragmento | FEAT-WRK-003 | PENDING |
| `GET /works/{workId}/chapters` | `listChapters` | Listar fragmentos | FEAT-WRK-003 | PENDING |
| `GET /chapters/{chapterId}` | `getChapter` | Leer un fragmento | FEAT-WRK-004 | PENDING |
| `PUT /chapters/{chapterId}` | `updateChapter` | Editar fragmento | FEAT-WRK-005 | PENDING |
| `DELETE /chapters/{chapterId}` | `deleteChapter` | Eliminar fragmento | FEAT-WRK-005 | PENDING |
| `PUT /chapters/{chapterId}/visibility` | `setChapterVisibility` | Visibilidad del fragmento | FEAT-WRK-008 | PENDING |
| `PUT /works/{workId}/visibility` | `setWorkVisibility` | Visibilidad de la obra | FEAT-WRK-008 | PENDING |
| `PUT /works/{workId}/access-mode` | `setAccessMode` | Modalidad de acceso de LB | FEAT-WRK-007 | PENDING |
| `GET /works/{workId}/questionnaire` | `getWorkQuestionnaire` | Ver cuestionario (autor) | FEAT-WRK-014 | DRAFT |
| `GET /chapters/{chapterId}/questionnaire` | `getChapterQuestionnaire` | Cuestionario a responder y borrador | FEAT-FBK-003 | DRAFT |
| `PUT /works/{workId}/questionnaire` | `updateWorkQuestionnaire` | Definir cuestionario | FEAT-WRK-014 | DRAFT |
| `POST /works/{workId}/questionnaire/estimate` | `estimateQuestionnairePricing` | Simular coste y recompensa | FEAT-WRK-014 | DRAFT |
| `PUT /works/{workId}/status` | `setWorkStatus` | Borrador / visible / en corrección | FEAT-WRK-016 | DRAFT |
| `POST /works/{workId}/public-link` | `createPublicLink` | Crear enlace público | FEAT-WRK-010 | PENDING |
| `DELETE /public-links/{linkId}` | `revokePublicLink` | Revocar enlace público | FEAT-WRK-010 | PENDING |
| `GET /public-links/{token}` | `readByPublicLink` | Leer sin sesión | FEAT-WRK-010 | PENDING |
| `GET /works/{workId}/share-link` | `getShareLink` | Enlace para redes sociales | FEAT-WRK-011 | PENDING |
| `GET /works/{workId}/authorship-records` | `listAuthorshipRecords` | Registros de autoría | FEAT-WRK-009 | BLOCKED |

---

## `POST /works`

**`operationId`:** `createWork` · **Funcionalidad:** [`FEAT-WRK-001`](../../features/work/FEAT-WRK-001-create-work-with-editor.md)

### Propósito

Crear una obra con el editor. El autor es siempre el usuario autenticado.

### Autorización

Requiere sesión. Cualquier usuario autenticado puede crear obras propias.

### Reglas aplicadas

`RN-1` a `RN-8` de `FEAT-WRK-001`.

### Entrada

Título, contenido (uno o varios fragmentos) y metadatos opcionales.

**No se aceptan:** `authorId`, `wordCount` ni `textTier`. Los tres los determina el servidor;
aceptarlos permitiría falsear la autoría o el coste en créditos de la obra.

Pendiente (`Q-2`): si los fragmentos se envían en esta misma petición o se añaden después.

### Respuesta

`201 Created` con la obra, incluyendo `wordCount` y `textTier` calculados.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `VALIDATION_FAILED` | 422 | Título vacío o sin contenido |

### Efectos

Publica `WorkCreated`. Genera un `AuthorshipRecord`. **No mueve créditos.**

---

## Nota sobre autorización de lectura

`GET /works/{workId}/content` y `GET /chapters/{chapterId}` son los endpoints más sensibles
de la API: devuelven obra inédita.

- Solo el autor y quien tenga `BetaReaderAccess` vigente.
- Obra o fragmento `HIDDEN` para un tercero: `404`, nunca `403`. Confirmar que existe una
  obra oculta ya es una fuga.
- El acceso se comprueba **en cada petición**, no solo al conceder el acceso.


---

## `GET /works`

**`operationId`:** `listWorks` · **Funcionalidad:** [`FEAT-WRK-012`](../../features/work/FEAT-WRK-012-browse-catalogue.md)

### Propósito

Catálogo de la sección «Leer»: obras disponibles, con filtros, ordenación, recuento total y
paginación **numerada**.

### Autorización

Sesión iniciada. Si es público para invitados está pendiente de `L-8`.

### Reglas aplicadas

- Nunca devuelve obras en `DRAFT`, **tampoco si el parámetro lo pide** (`FEAT-WRK-012` `RN-2`).
- Excluye las obras propias del usuario y las de usuarios bloqueados.
- La insignia de créditos procede de `Credits`, nunca de un `JOIN` con sus tablas (`L-9`).

### Entrada

`genres[]`, `readingTime`, `status`, `sort`, `page`, `perPage`.

### Respuesta

Elementos del catálogo más `pageInfo` con `page`, `perPage`, `total` y `totalPages`. Es una
de las dos excepciones a la paginación por cursor
([`paginación`](../conventions/pagination.md)).

### Errores específicos

| Caso | Código |
|---|---|
| Valor de filtro desconocido | `422` |
| `status=DRAFT` | `422` |

Un filtro con valor inválido **no se ignora**: devolver resultados de otra consulta es peor
que devolver un error.

---

## `PUT /works/{workId}/questionnaire`

**`operationId`:** `updateWorkQuestionnaire` · **Funcionalidad:** [`FEAT-WRK-014`](../../features/work/FEAT-WRK-014-configure-questionnaire.md)

### Propósito

Sustituir el cuestionario de la obra. **Crea una versión nueva**; las anteriores se conservan
porque hay correcciones que las responden.

### Autorización

Solo el autor de la obra.

### Reglas aplicadas

- Al menos una pregunta; como máximo `N` (`W-11`).
- No altera retenciones ni correcciones en curso.
- Se permite guardar aunque el autor no tenga saldo para el coste resultante: configurar no
  gasta; abrir la obra a corrección, sí.

### Entrada

Lista ordenada de preguntas: enunciado, ejemplo, obligatoriedad, longitudes **en palabras** y
alcance (`EVERY_CHAPTER` / `LAST_CHAPTER`, pendiente de `W-17`).

### Respuesta

El cuestionario con su `version`. Requiere `If-Match`
([`concurrencia`](../conventions/concurrency-and-idempotency.md)).

### Efectos

Publica `QuestionnaireUpdated`, que `Credits` consume para recalcular coste y recompensa.
Como el precio depende también de la longitud del texto y la corrección es por capítulo, lo
que se recalcula es **el precio de cada capítulo**, no una cifra única de la obra.

El payload lleva **los atributos que determinan el precio, no el enunciado de las
preguntas**: el texto es contenido del autor y no tiene por qué circular por la cola.

---

## `GET /works/{workId}/questionnaire` y `GET /chapters/{chapterId}/questionnaire`

**`operationId`:** `getWorkQuestionnaire` / `getChapterQuestionnaire` · **Funcionalidades:** [`FEAT-WRK-014`](../../features/work/FEAT-WRK-014-configure-questionnaire.md), [`FEAT-FBK-003`](../../features/feedback/FEAT-FBK-003-answer-correction-questionnaire.md)

### Propósito

Son dos operaciones distintas sobre el mismo objeto, y conviene que lo sean:

| Ruta | Para quién | Qué devuelve |
|---|---|---|
| `/works/{workId}/questionnaire` | El autor | La configuración completa y su versión |
| `/chapters/{chapterId}/questionnaire` | El lector beta | Las preguntas que **aplican a ese capítulo** y **su borrador** |

La segunda existe porque la corrección es por capítulo: qué preguntas aplican puede depender
del capítulo (`W-17`), y el borrador es de ese capítulo. Devolver ambas cosas juntas hace que
abrir el panel de corrección sea **una sola llamada**.

Que la representación dependa de quién pregunta no es un detalle: el autor ve cómo está
configurado el formulario; el lector, solo lo que necesita para responderlo.
