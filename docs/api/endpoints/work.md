# Endpoints — `Work`

> Estado: **esqueleto**. Las rutas son una propuesta.

## Resumen

| Método y ruta | `operationId` | Propósito | Funcionalidad | Estado |
|---|---|---|---|---|
| `POST /api/v1/works` | `createWork` | Crear obra. Nace vacía y en `DRAFT` | FEAT-WRK-001 | **Implementado** |
| `POST /works/uploads` | `uploadManuscript` | Crear obra desde fichero | FEAT-WRK-002 | PENDING |
| `GET /api/v1/works/{workId}` | `getWork` | Obra e **índice** de capítulos, sin texto | FEAT-WRK-004 | **Implementado** |
| `GET /works/{workId}/content` | `getWorkContent` | Contenido completo | FEAT-WRK-004 | PENDING |
| `PATCH /works/{workId}` | `updateWork` | Editar metadatos | FEAT-WRK-005 | PENDING |
| `DELETE /works/{workId}` | `deleteWork` | Eliminar obra | FEAT-WRK-006 | PENDING |
| `GET /works` | `listWorks` | Catálogo: sección «Leer» | FEAT-WRK-012 | DRAFT |
| `GET /me/works` | `listMyWorks` | Obras propias | FEAT-WRK-004 | PENDING |
| `POST /api/v1/works/{workId}/chapters` | `addChapter` | Añadir capítulo. El contenido se sanea al guardarlo | FEAT-WRK-001 | **Implementado** |
| `GET /works/{workId}/chapters` | `listChapters` | Listar fragmentos | FEAT-WRK-003 | PENDING |
| `GET /api/v1/chapters/{chapterId}` | `getChapter` | Leer el texto de un capítulo | FEAT-WRK-004 | **Implementado** |
| `PUT /chapters/{chapterId}` | `updateChapter` | Editar fragmento | FEAT-WRK-005 | PENDING |
| `DELETE /chapters/{chapterId}` | `deleteChapter` | Eliminar fragmento | FEAT-WRK-005 | PENDING |
| `PUT /chapters/{chapterId}/visibility` | `setChapterVisibility` | Visibilidad del fragmento | FEAT-WRK-008 | PENDING |
| `PUT /works/{workId}/visibility` | `setWorkVisibility` | Visibilidad de la obra | FEAT-WRK-008 | PENDING |
| `PUT /api/v1/works/{workId}/access-mode` | `setAccessMode` | Quién puede ser lector beta | FEAT-WRK-007 | **Implementado** |
| `GET /api/v1/works/{workId}/questionnaire` | `getWorkQuestionnaire` | Ver el cuestionario vigente | FEAT-WRK-014 | **Implementado** |
| `GET /chapters/{chapterId}/questionnaire` | `getChapterQuestionnaire` | Cuestionario a responder y borrador | FEAT-FBK-003 | DRAFT |
| `PUT /api/v1/works/{workId}/questionnaire` | `updateWorkQuestionnaire` | Definir el cuestionario. Crea una versión | FEAT-WRK-014 | **Implementado** |
| `POST /works/{workId}/questionnaire/estimate` | `estimateQuestionnairePricing` | Simular coste y recompensa | FEAT-WRK-014 | BLOCKED |
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
## `PUT /api/v1/works/{workId}/questionnaire`

**`operationId`:** `updateWorkQuestionnaire` · **Funcionalidad:** [`FEAT-WRK-014`](../../features/work/FEAT-WRK-014-configure-questionnaire.md)

### Propósito

Sustituir el cuestionario de la obra. **Crea una versión nueva**; las anteriores se conservan
enteras, con sus preguntas, porque hay correcciones que las responden. Nada se actualiza en
sitio, así que una corrección en curso nunca cambia de preguntas a mitad.

### Autorización

Solo el autor. Una obra ajena responde `404`, no `403`: decir «existe, pero no es tuya» sobre
una obra inédita ya es decir demasiado.

Requiere cuenta activada.

### Entrada

`questions`, lista ordenada. La posición en el array es la posición de la pregunta.

| Campo | Obligatorio | Notas |
|---|---|---|
| `statement` | Sí | El enunciado, texto libre |
| `example` | No | Texto de ayuda que el lector ve como marcador |
| `required` | No | Por defecto `true` |
| `minWords` | **Sí** | Mínimo de palabras de la respuesta |
| `maxWords` | No | Si se declara, no puede ser menor que `minWords` |
| `scope` | No | `EVERY_CHAPTER` (por defecto) o `LAST_CHAPTER` |

**`minWords` es obligatorio y no es una validación de formulario: es el precio.** La suma de
los mínimos es el término de escritura de la fórmula de
[`decision:0006`](../../decisions/0006-credit-system.md). Sin mínimos, una novela entera se
corregiría por 2 créditos.

### Reglas aplicadas

| Regla | `code` | HTTP |
|---|---|---|
| Al menos una pregunta | `QUESTIONNAIRE_WITHOUT_QUESTIONS` | 422 |
| Como mucho 20 | `TOO_MANY_QUESTIONS` | 422 |
| Como mucho 2.000 palabras exigidas en total | `TOO_MANY_REQUIRED_WORDS` | 422 |
| Enunciado no vacío | `EMPTY_QUESTION` | 422 |
| `minWords` declarado y mayor que cero | `MISSING_MINIMUM_WORDS` | 422 |
| `minWords` ≤ `maxWords` | `INVALID_WORD_RANGE` | 422 |
| Alguna pregunta aplica a todos los capítulos | `QUESTIONNAIRE_ONLY_FOR_LAST_CHAPTER` | 422 |
| `If-Match` coincide con la versión vigente | `STALE_VERSION` | 409 |

Las 2.000 palabras son el punto donde `ceil(requiredWords / 100)` alcanza el tope de 20
créditos: más allá, **el autor no paga más y el lector escribe más**.

La última regla de las `422` evita el cuestionario que se responde en un solo capítulo y deja
los demás sin nada que preguntar.

Se permite guardar aunque el autor no tenga saldo para el coste resultante: configurar no
gasta; abrir la obra a corrección, sí.

### Concurrencia

`If-Match: "<version>"` lleva la versión que el autor tenía en pantalla
([`concurrencia`](../conventions/concurrency-and-idempotency.md)). Es **opcional**: un cliente
que no lo envíe sigue funcionando y asume el riesgo de pisar a otra pestaña. La respuesta
`409` incluye la versión vigente, para que el cliente pueda releer y reintentar.

### Respuesta

`200` con la `version` creada.

### Efectos

Publica `QuestionnaireUpdated`, que `Credits` consume para recalcular el precio **de cada
capítulo**, no una cifra única de la obra.

El payload lleva `version`, `questionCount`, `requiredWords` y `requiredWordsForEveryChapter`,
y **no lleva el enunciado de ninguna pregunta**: el texto es contenido del autor y no tiene
por qué circular por la cola.

Los dos totales van por separado porque el precio de un capítulo intermedio cuenta solo las
preguntas de alcance `EVERY_CHAPTER`, mientras que el último capítulo las responde todas.
Mandar un solo total haría pagar en cada capítulo preguntas que solo se contestan en uno.

---

## `GET /api/v1/works/{workId}/questionnaire`

**`operationId`:** `getWorkQuestionnaire` · **Funcionalidad:** [`FEAT-WRK-014`](../../features/work/FEAT-WRK-014-configure-questionnaire.md)

### Propósito

Devolver el cuestionario vigente de la obra: su `version`, `requiredWords` y la lista ordenada
de preguntas.

### Autorización

La misma que decide quién lee los capítulos
([`FEAT-WRK-004`](../../features/work/FEAT-WRK-004-read-a-work.md)): un cuestionario describe
una obra inédita tan bien como su texto —los enunciados nombran a los personajes y suelen
adelantar el final—, así que no puede ser más accesible que ella.

### Respuesta

`200`. Una obra **sin cuestionario todavía** devuelve `version: 0` y una lista vacía, no un
`404`: es el estado normal de una obra recién creada, y un error ahí obligaría al cliente a
distinguir dos casos que para él son el mismo.

---

## `GET /chapters/{chapterId}/questionnaire`

**`operationId`:** `getChapterQuestionnaire` · **Funcionalidad:** [`FEAT-FBK-003`](../../features/feedback/FEAT-FBK-003-answer-correction-questionnaire.md) · **Estado:** PENDING

La vista del lector es una operación distinta sobre el mismo objeto, y conviene que lo sea:

| Ruta | Para quién | Qué devuelve |
|---|---|---|
| `/works/{workId}/questionnaire` | El autor | La configuración completa y su versión |
| `/chapters/{chapterId}/questionnaire` | El lector beta | Las preguntas que **aplican a ese capítulo** y **su borrador** |

Existe porque la corrección es por capítulo: qué preguntas aplican depende del capítulo —una
de alcance `LAST_CHAPTER` no aparece en el capítulo 3— y el borrador es de ese capítulo.
Devolver ambas cosas juntas hace que abrir el panel de corrección sea **una sola llamada**.

Nace con la pantalla que la usa, no antes.

---

## `POST /works/{workId}/questionnaire/estimate`

**`operationId`:** `estimateQuestionnairePricing` · **Funcionalidad:** [`FEAT-WRK-014`](../../features/work/FEAT-WRK-014-configure-questionnaire.md) · **Estado:** BLOCKED

Simula coste y recompensa **mientras el autor configura**, sin guardar nada (`RN-6`). Devuelve
cifras por capítulo y nada más.

Está bloqueada porque el cálculo es de `Credits` y todavía no existe quien lo haga
([`FEAT-CRD-016`](../../features/credits/FEAT-CRD-016-effort-based-pricing.md)). Cuando exista,
`Work` la resolverá con un **contrato de consulta de solo lectura** publicado por `Credits`
—cifras, nunca su modelo—, que es lo que
[`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md) permite. Es el
caso raro en que la consulta síncrona está justificada: el precio se necesita en el momento, y
un evento llega tarde.
