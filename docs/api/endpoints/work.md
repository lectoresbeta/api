# Endpoints — `Work`

> Estado: **esqueleto**. Las rutas son una propuesta.

## Resumen

| Método y ruta | `operationId` | Propósito | Funcionalidad | Estado |
|---|---|---|---|---|
| `POST /api/v1/works` | `createWork` | Crear obra. Nace vacía y en `DRAFT` | FEAT-WRK-001 | **Implementado** |
| `POST /works/uploads` | `uploadManuscript` | Crear obra desde fichero | FEAT-WRK-002 | PENDING |
| `GET /works/{workId}` | `getWork` | Metadatos de la obra | FEAT-WRK-004 | PENDING |
| `GET /works/{workId}/content` | `getWorkContent` | Contenido completo | FEAT-WRK-004 | PENDING |
| `PATCH /works/{workId}` | `updateWork` | Editar metadatos | FEAT-WRK-005 | PENDING |
| `DELETE /works/{workId}` | `deleteWork` | Eliminar obra | FEAT-WRK-006 | PENDING |
| `GET /works` | `listWorks` | Catálogo: sección «Leer» | FEAT-WRK-012 | DRAFT |
| `GET /me/works` | `listMyWorks` | Obras propias | FEAT-WRK-004 | PENDING |
| `POST /api/v1/works/{workId}/chapters` | `addChapter` | Añadir capítulo. El contenido se sanea al guardarlo | FEAT-WRK-001 | **Implementado** |
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
| `PUT /api/v1/works/{workId}/status` | `changeWorkStatus` | Publicar, abrir o cerrar la corrección | FEAT-WRK-016 | **Implementado** |
| `POST /works/{workId}/public-link` | `createPublicLink` | Crear enlace público | FEAT-WRK-010 | PENDING |
| `DELETE /public-links/{linkId}` | `revokePublicLink` | Revocar enlace público | FEAT-WRK-010 | PENDING |
| `GET /public-links/{token}` | `readByPublicLink` | Leer sin sesión | FEAT-WRK-010 | PENDING |
| `GET /works/{workId}/share-link` | `getShareLink` | Enlace para redes sociales | FEAT-WRK-011 | PENDING |
| `GET /works/{workId}/authorship-records` | `listAuthorshipRecords` | Registros de autoría | FEAT-WRK-009 | BLOCKED |

---

## `POST /api/v1/works`

**`operationId`:** `createWork` · **Funcionalidad:** [`FEAT-WRK-001`](../../features/work/FEAT-WRK-001-create-work-with-editor.md)

### Propósito

Crear una obra. El autor es siempre el usuario autenticado.

### Autorización

Requiere sesión **y cuenta activada**. Es la primera escritura del producto sujeta a
[`FEAT-USR-025`](../../features/user/FEAT-USR-025-block-writes-until-activation.md): una
cuenta en `PENDING_ACTIVATION` recibe `403` con `ACCOUNT_NOT_ACTIVATED`, que la interfaz
distingue para poder ofrecer el reenvío del correo.

### Entrada

Solo `title` y, opcionalmente, `synopsis`. **La obra nace vacía**: los capítulos se añaden
después (`Q-2`, resuelta). Una novela de cuarenta capítulos no cabe en una petición, y
obligar a ello convertiría cada guardado en un envío completo.

**No se acepta `authorId`**, y un `wordCount` enviado se ignora: aceptarlos permitiría
falsear la autoría o el coste en créditos de la obra. `textTier` ya no existe
([`decision:0006`](../../decisions/0006-credit-system.md)).

### Respuesta

`201 Created` con el `workId`. La obra nace en **`DRAFT`**: nacer visible expondría obra
inédita por un descuido.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `ACCOUNT_NOT_ACTIVATED` | 403 | La cuenta no está activada |
| `INVALID_VALUE` | 422 | Título vacío |

### Efectos

Publica `WorkCreated`, **sin el contenido de la obra ni nada derivado de leerlo**. No mueve
créditos: los créditos se mueven al entregarse una corrección, no al publicar.

El `AuthorshipRecord` **todavía no se genera**: depende de `W-1`, que no ha decidido en qué
momento se crea, y hacerlo sobre una obra vacía no significaría nada.

---

## `POST /api/v1/works/{workId}/chapters`

**`operationId`:** `addChapter` · **Funcionalidad:** [`FEAT-WRK-001`](../../features/work/FEAT-WRK-001-create-work-with-editor.md)

### Autorización

Requiere sesión, cuenta activada y **ser el autor de la obra**. Una obra ajena responde `404`,
no `403`: confirmar que una obra inédita existe ya es una fuga.

### Entrada

`content` es HTML y **se sanea al guardarlo, nunca al servirlo**. Lo almacenado puede no ser
exactamente lo enviado, y el cliente debe rehidratar el editor con lo guardado.

La lista blanca es `p`, `br`, `strong`, `em`, `blockquote`, `h2`, `h3`, `hr`, sin ningún
atributo. **Sin enlaces ni imágenes.**

Lo que no está en la lista **pierde el marcado y conserva el texto**: un pegado desde Word
llega lleno de `span`, y tirar las palabras con ellos sería una pésima bienvenida. Solo
`script`, `style`, `iframe` y compañía se eliminan con su contenido, porque su contenido no
es prosa.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `WORK_NOT_FOUND` | 404 | La obra no existe **o no es tuya** |
| `EMPTY_CHAPTER` | 422 | Tras sanear no queda texto |

### Efectos

Actualiza el recuento de palabras y de capítulos de la obra. Ese recuento se calcula sobre el
**texto plano** derivado, no sobre el marcado: de él depende el precio de toda corrección
([`FEAT-CRD-016`](../../features/credits/FEAT-CRD-016-effort-based-pricing.md)).

---

## `PUT /api/v1/works/{workId}/status`

**`operationId`:** `changeWorkStatus` · **Funcionalidad:** [`FEAT-WRK-016`](../../features/work/FEAT-WRK-016-work-status.md)

### Propósito

Publicar la obra, abrirla a corrección o cerrar la corrección. Una sola operación con el
destino en el cuerpo.

### Autorización

Sesión, cuenta activada y **ser el autor**. Una obra ajena responde `404`.

### Reglas aplicadas

| De | A | |
|---|---|---|
| `DRAFT` | `PUBLISHED` | Publicar. Exige al menos un capítulo |
| `PUBLISHED` | `IN_CORRECTION` | Abrir a corrección |
| `IN_CORRECTION` | `PUBLISHED` | Cerrar la corrección |

**El servidor decide qué caminos existen.** Un cliente que conociera la máquina de estados
habría que actualizarlo cada vez que cambiase, y el que no se actualizara sería el que
hiciera la llamada ilegal.

Publicar y abrir son **dos pasos**: saltárselos escondería una publicación dentro de otra
acción, y el autor empezaría a gastar créditos sobre un texto que nadie ha visto aún como lo
verán los demás. Volver a `DRAFT` **no se ofrece** mientras `W-10` no se decida: puede que
alguien ya lo haya leído.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `WORK_HAS_NO_CHAPTERS` | 409 | Publicar una obra vacía |
| `ILLEGAL_WORK_TRANSITION` | 409 | Transición no permitida, o un estado que no existe |
| `WORK_NOT_FOUND` | 404 | La obra no existe **o no es tuya** |
| `ACCOUNT_NOT_ACTIVATED` | 403 | La cuenta no está activada |

### Efectos

Publica `WorkPublished`, `WorkOpenedForCorrection` o `WorkClosedForCorrection` según la
transición. **Ninguno mueve créditos**: el diseño anterior reservaba al abrir la corrección, y
[`decision:0006`](../../decisions/0006-credit-system.md) eliminó las reservas.

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
- No altera el precio de las correcciones ya empezadas.
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
