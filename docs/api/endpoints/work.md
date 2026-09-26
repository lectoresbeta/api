# Endpoints — `Work`

> Estado: **esqueleto**. Las rutas son una propuesta.

## Resumen

| Método y ruta | `operationId` | Propósito | Funcionalidad | Estado |
|---|---|---|---|---|
| `POST /api/v1/works` | `createWork` | Crear obra. Nace vacía y en `DRAFT` | FEAT-WRK-001 | **Implementado** |
| `POST /works/uploads` | `uploadManuscript` | Crear obra desde fichero | FEAT-WRK-002 | PENDING |
| `GET /api/v1/works/{workId}` | `getWork` | Obra e **índice** de capítulos, sin texto | FEAT-WRK-004 | **Implementado** |
| `GET /api/v1/works/{workId}/share` | `getWorkShareCard` | Tarjeta de previsualización, **pública** | FEAT-WRK-011 | **Implementado** |
| `GET /works/{workId}/content` | `getWorkContent` | Contenido completo | FEAT-WRK-004 | PENDING |
| `PATCH /works/{workId}` | `updateWork` | Editar metadatos | FEAT-WRK-005 | PENDING |
| `DELETE /works/{workId}` | `deleteWork` | Eliminar obra | FEAT-WRK-006 | PENDING |
| `GET /api/v1/works` | `listWorks` | Catálogo: sección «Leer» | FEAT-WRK-012 | **Implementado** |
| `GET /me/works` | `listMyWorks` | Obras propias | FEAT-WRK-004 | PENDING |
| `POST /api/v1/works/{workId}/chapters` | `addChapter` | Añadir capítulo. El contenido se sanea al guardarlo | FEAT-WRK-001 | **Implementado** |
| `GET /works/{workId}/chapters` | `listChapters` | Listar fragmentos | FEAT-WRK-003 | PENDING |
| `GET /api/v1/chapters/{chapterId}` | `getChapter` | Leer el texto de un capítulo | FEAT-WRK-004 | **Implementado** |
| `PUT /chapters/{chapterId}` | `updateChapter` | Editar fragmento | FEAT-WRK-005 | PENDING |
| `DELETE /chapters/{chapterId}` | `deleteChapter` | Eliminar fragmento | FEAT-WRK-005 | PENDING |
| `PUT /chapters/{chapterId}/visibility` | `setChapterVisibility` | Visibilidad del fragmento | FEAT-WRK-008 | PENDING |
| `PUT /works/{workId}/visibility` | `setWorkVisibility` | Visibilidad de la obra | FEAT-WRK-008 | PENDING |
| `PUT /api/v1/works/{workId}/access-mode` | `setAccessMode` | Quién puede ser lector beta | FEAT-WRK-007 | **Implementado** |
| `PUT /api/v1/works/{workId}/genres` | `setWorkGenres` | Clasificar la obra | FEAT-WRK-001 | **Implementado** |
| `PUT /api/v1/works/{workId}/content-rating` | `setWorkContentRating` | Declarar el contenido sensible | FEAT-WRK-017 | **Implementado** |
| `GET /api/v1/content-warnings` | `listContentWarnings` | Catálogo de etiquetas de contenido | FEAT-WRK-017 | **Implementado** |
| `GET /api/v1/works/{workId}/questionnaire` | `getWorkQuestionnaire` | Ver el cuestionario vigente | FEAT-WRK-014 | **Implementado** |
| `GET /chapters/{chapterId}/questionnaire` | `getChapterQuestionnaire` | Cuestionario a responder y borrador | FEAT-FBK-003 | DRAFT |
| `PUT /api/v1/works/{workId}/questionnaire` | `updateWorkQuestionnaire` | Definir el cuestionario. Crea una versión | FEAT-WRK-014 | **Implementado** |
| `POST /works/{workId}/questionnaire/estimate` | `estimateQuestionnairePricing` | Simular coste y recompensa | FEAT-WRK-014 | BLOCKED |
| `PUT /api/v1/works/{workId}/status` | `changeWorkStatus` | Publicar, abrir o cerrar la corrección | FEAT-WRK-016 | **Implementado** |
| `POST /api/v1/works/{workId}/public-links` | `createPublicLink` | Crear un enlace público. **Devuelve el token una sola vez** | FEAT-WRK-010 | **Implementado** |
| `GET /api/v1/works/{workId}/public-links` | `listPublicLinks` | Los enlaces de una obra, sin sus tokens | FEAT-WRK-010 | **Implementado** |
| `DELETE /api/v1/public-links/{publicLinkId}` | `revokePublicLink` | Revocar. Inmediata e irreversible | FEAT-WRK-010 | **Implementado** |
| `GET /api/v1/public/{token}` | `getPublicCorrectionPage` | La obra y su índice, **sin sesión** | FEAT-WRK-010 | **Implementado** |
| `GET /api/v1/public/{token}/chapters/{chapterId}` | `getPublicChapter` | El texto y el cuestionario, **sin sesión** | FEAT-WRK-010 | **Implementado** |
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

`title` y, opcionalmente, `synopsis` y `genres`. **La obra nace vacía**: los capítulos se añaden
después (`Q-2`, resuelta). Una novela de cuarenta capítulos no cabe en una petición, y
obligar a ello convertiría cada guardado en un envío completo.

**No se acepta `authorId`**, y un `wordCount` enviado se ignora: aceptarlos permitiría
falsear la autoría o el coste en créditos de la obra. `textTier` ya no existe
([`decision:0006`](../../decisions/0006-credit-system.md)).

`genres` son códigos del catálogo que posee `User` (`GET /genres`), como mucho tres, y son
opcionales: una obra sin clasificar existe, solo que no aparece cuando alguien filtra el
catálogo. Se validan contra el catálogo vigente y un código desconocido se rechaza
**nombrándolo**.

---

## `PUT /api/v1/works/{workId}/genres`

**`operationId`:** `setWorkGenres` · **Funcionalidad:** [`FEAT-WRK-001`](../../features/work/FEAT-WRK-001-create-work-with-editor.md)

### Propósito

Sustituir las temáticas de la obra. **No hay «añadir una»**: lo que se envía es cómo queda
clasificada, igual que con el modo de acceso o el estado.

### Autorización

Solo el autor, con cuenta activada. Una obra ajena responde `404`.

### Reglas aplicadas

- Como mucho **tres** temáticas. El filtro del catálogo es en `O`, así que una obra con ocho
  aparecería en casi cualquier búsqueda.
- Solo códigos del catálogo vigente de `User`, consultado por **contrato publicado**: `Work`
  no tiene catálogo propio ni lee sus tablas (`W-7`).
- La lista vacía deja la obra sin clasificar, que es legítimo.
- Sustituir **conservando** una temática es el caso normal y funciona. Se dice porque durante
  un tiempo no: la sustitución borraba todo y volvía a insertar, lo que solo funciona con
  listas disjuntas, y reclasificar dejando una temática devolvía un `500`
  ([`FEAT-USR-009`](../../features/user/FEAT-USR-009-literary-preferences.md) lo destapó).

### Efectos

Ninguno fuera de este contexto. No cuesta créditos, no toca lo que ya se corrigió y no publica
ningún evento: nadie fuera de `Work` usa todavía las temáticas de una obra, y publicar un
hecho que nadie escucha es inventarse un contrato que luego hay que mantener.

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

## `PUT /api/v1/works/{workId}/content-rating`

**`operationId`:** `setWorkContentRating` · **Funcionalidad:**
[`FEAT-WRK-017`](../../features/work/FEAT-WRK-017-content-rating.md)

### Propósito

El autor declara **qué contiene su obra y para quién es**. Sustituye la clasificación entera,
igual que las temáticas o el modo de acceso.

La declaración funciona en los dos sentidos, y es lo que la hace algo más que una cortesía:
una reclamación por contenido fuerte **bien etiquetado se desestima** (`RN-6`) y **etiquetar
mal es reclamable** (`RN-7`). El sistema deja de castigar el contenido difícil y pasa a
castigar el engaño.

### Autorización

Solo el autor, con cuenta activada. Una obra ajena responde `404`, igual que una inexistente.

### Reglas aplicadas

- `adultsOnly` es **obligatorio**; `contentWarnings` puede ir vacío. La asimetría es
  deliberada: una lista vacía es una declaración —«no contiene nada de esto»—, mientras que
  omitir el indicador no significa «apta para menores», significa que nadie lo ha dicho.
- Las etiquetas salen de un **catálogo cerrado** de cinco valores (`RN-4`). Una que no existe
  se rechaza **por su nombre**: el autor responde de lo que ha declarado, así que tiene que
  saber qué se ha entendido.
- Etiquetar de más no se penaliza. Solo cuesta lectores.
- Es **por obra** (`RN-2`): una novela se etiqueta por lo más fuerte que contiene.
- Se puede cambiar en cualquier momento y **no afecta a las correcciones en curso** (`RN-5`).

### Respuesta

`200` con la clasificación tal y como ha quedado, en lugar de un `204`: quien acaba de
declarar algo de lo que responde quiere ver qué se ha entendido.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `AUDIENCE_NOT_DECLARED` | 422 | Falta `adultsOnly` |
| `UNKNOWN_CONTENT_WARNING` | 422 | Una etiqueta que no existe. El `detail` **dice cuál** |
| `ACCOUNT_NOT_ACTIVATED` | 403 | La cuenta no está activada |

### Efectos

Ninguno fuera de este contexto todavía. **No publica `WorkContentRatingSet`**, y es la misma
decisión que con las temáticas: los consumidores que la ficha prevé —`Community` para el muro,
`Moderation` para juzgar una reclamación— no existen, y publicar un hecho que nadie escucha es
inventarse un contrato que luego hay que mantener. Cuando el primero exista, se publica desde
aquí.

Lo que sí cambia de inmediato es quién encuentra la obra: `adultsOnly` la retira para quien no
tiene edad declarada, y las etiquetas la retiran para quien las ha excluido en el catálogo.

---

## `GET /api/v1/content-warnings`

**`operationId`:** `listContentWarnings` · **Funcionalidad:**
[`FEAT-WRK-017`](../../features/work/FEAT-WRK-017-content-rating.md)

### Propósito

La lista cerrada de etiquetas que un autor puede declarar. Existe para que nadie la copie en
el cliente: es cerrada, pero puede crecer.

### Autorización

**Público**, como `GET /genres`: no es información sensible y hace falta en pantallas que se
ven sin sesión.

### Respuesta

`contentWarnings`, **códigos y no nombres**, al revés que `GET /genres`. La diferencia no es
un descuido: las temáticas son datos curados que se pueden renombrar sin tocar código, y estas
cinco son un catálogo cerrado. Cómo se le dice cada una a una persona —y la ficha pide que
`SELF_HARM` se diga con claridad, no con un icono ambiguo— es decisión de la interfaz.

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

## `GET /api/v1/works`

**`operationId`:** `listWorks` · **Funcionalidad:** [`FEAT-WRK-012`](../../features/work/FEAT-WRK-012-browse-catalogue.md)

### Propósito

Catálogo de la sección «Leer»: obras disponibles, con filtros, ordenación, recuento total y
paginación **numerada**.

### Autorización

Sesión iniciada. Si es público para invitados está pendiente de `L-8`.

### Cómo ordena

Por **reparto de trabajo, nunca por popularidad**
([`decision:0008`](../../decisions/0008-catalogue-ordering.md)):

```text
puntuación = capacidad × desatención × frescura
```

Una obra **sin capítulos corregibles ahora mismo puntúa cero** y cae al final. Sigue
apareciendo —se puede leer— pero no ocupa el sitio más valioso de la pantalla.

La propiedad que lo sostiene: aparecer arriba trae correcciones, cada corrección gasta saldo
del autor y sube su contador, y los dos primeros factores bajan a la vez. La obra desciende
sola.

### Reglas aplicadas

- Nunca devuelve obras en `DRAFT`, **tampoco si el parámetro lo pide** (`FEAT-WRK-012` `RN-2`).
- Excluye las obras propias del usuario, las bloqueadas por reclamación y —para quien no ha
  declarado su fecha de nacimiento— las marcadas para adultos.
- Los datos de `Credits` y `Feedback` llegan **por evento**, a dos tablas de `work_ctx`: no
  hay ningún `JOIN` con las tablas de otro contexto (`L-9`).

### Entrada

`genres[]`, `excludeContentWarnings[]`, `status`, `sort`, `page`, `perPage`.

`excludeContentWarnings[]` es el filtro de `RN-10`, y va **al revés** que los demás: **quita**
obras en vez de buscarlas. Una obra que lleve cualquiera de las etiquetas dadas desaparece, y
tampoco cuenta en `total` — lo excluido no llega al cliente, no se esconde en la interfaz
(`RN-9`). Por eso se llama así y no `contentWarnings[]`, que es como lo nombraba la ficha: un
parámetro que quita y se llama como si buscara es una trampa para quien integre.

Una etiqueta que no existe devuelve `422`, al revés que una temática desconocida. Allí no hay
forma de distinguir un código inventado de uno retirado del catálogo; aquí la lista es cerrada,
así que un valor que no está es una errata, y darla por buena enseñaría justo lo que se ha
pedido no ver.

`readingTime` sigue especificado y **no implementado**: falta decidir qué rangos son (`L-1`).

### Respuesta

`total`, `totalPages`, `page`, `perPage` y `works`. **Metadatos, nunca contenido**: que una
obra aparezca aquí no significa que quien la ve pueda abrirla, y eso lo decide `getWork`.

Cada tarjeta lleva `correctableChapters` y `correctionsReceived`, que son señales del reparto
—cuánto queda por corregir y cuánto se ha corregido ya— y no importes.

Cada tarjeta lleva también `contentWarnings`, lo que la obra declara contener. Va aquí y no
solo en la cabecera porque una advertencia que solo aparece cuando ya estás leyendo no
advierte de nada (`FEAT-WRK-017` `RN-8`).

`credits` sí es un importe, y es el único: la insignia de
[`FEAT-CRD-013`](../../features/credits/FEAT-CRD-013-work-credit-badge.md), **lo que gana
quien corrija esta obra ahora mismo**. Es el mínimo de sus capítulos corregibles, para que la
tarjeta nunca prometa más de lo que después se abona. **Cero no significa gratis: significa
que ahora mismo no se puede corregir.** La cifra la calcula `Credits` y llega por evento; el
catálogo la copia sin saber de dónde sale.

Es una de las dos excepciones a la paginación por cursor
([`paginación`](../conventions/pagination.md)): un catálogo filtrado no es un flujo, y quien
lo usa quiere saber cuántos resultados hay y saltar a la página 4.

### Errores específicos

| Caso | Código |
|---|---|
| Valor de filtro desconocido | `422` con `UNKNOWN_FILTER_VALUE` |
| `status=DRAFT` | `422`. Un desplegable que no lo ofrece no es una autorización |
| `page` menor que 1 | `422` con `INVALID_PAGE` |
| Página fuera de rango | `200` con lista vacía |

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

---

## El enlace público

**`operationId`:** `createPublicLink`, `listPublicLinks`, `revokePublicLink`,
`getPublicCorrectionPage`, `getPublicChapter` ·
**Funcionalidad:** [`FEAT-WRK-010`](../../features/work/FEAT-WRK-010-public-correction-link.md)

### Propósito

Que el autor genere una URL de su obra y la reparta **fuera de la plataforma**. Quien la recibe
lee y corrige sin registrarse ([`FEAT-FBK-008`](../../features/feedback/FEAT-FBK-008-public-link-correction.md)).

Es la **válvula de seguridad de la economía de créditos**: un autor sin saldo no recibe
correcciones, y para conseguir saldo necesita textos que corregir. El enlace rompe ese círculo
por fuera, sin tocar la masa de créditos y sin depender de nadie más que de su agenda.

### El token

Opaco, largo y aleatorio. **No se deriva de nada** —ni del identificador de la obra, ni del
autor, ni de una fecha—: cualquiera de esas tres cosas convertiría la única credencial que
protege obra inédita en algo que se adivina sabiendo lo que ya se sabe.

Se guarda cifrado y **se devuelve una sola vez**, en la respuesta que lo crea. Después no hay
forma de recuperarlo: quien pierde la URL crea otro enlace.

Es incómodo y es deliberado. Guardarlo en claro para poder enseñarlo siempre dejaría una
credencial legible en la base de datos y en cada copia de seguridad, y eso no se deshace; crear
otro enlace cuesta un clic. El caso real —repartirlo a tu grupo de escritura— se resuelve
copiando la URL en el momento, que es cuando se está mirando.

### Autorización

Las tres primeras operaciones son **del autor de la obra**, y no existe ni se contesta lo que
no es suyo: «no existe» y «no es tuya» comparten `404`.

Las dos lecturas **no exigen sesión**, y son las únicas de una obra que no la exigen. Lo que
autoriza es el token.

**Pero sí la leen.** Está en `access_control` con `PUBLIC_ACCESS` y no en un cortafuegos sin
seguridad, y la distinción es la que sostiene `FEAT-FBK-008` `RN-1`: a quien ya tiene cuenta se
le manda al flujo normal, donde su corrección se paga, y para eso hay que saber que la tiene.
Sin esa línea, el autor podría pegar el enlace en su muro y conseguir que usuarios registrados
le corrigiesen gratis.

### Qué se sirve

La portada trae la obra y su índice de **capítulos visibles**. Un capítulo que su autor ha
ocultado no sale aquí ni por su URL directa: el enlace abre una puerta, no la levanta.

El texto va en su propio endpoint. Una novela entera en una respuesta son megabytes que casi
nadie lee de una sentada, y además es como se lee.

Los dos llevan `X-Robots-Tag: noindex, nofollow, noarchive, nosnippet` y `Cache-Control:
no-store`. Es obra inédita: no puede acabar en un buscador ni quedarse en un intermediario. La
cabecera y no una etiqueta `meta` porque esto devuelve JSON, y una etiqueta dentro del HTML solo
la pondría el cliente.

### Errores específicos

| Código HTTP | `code` | Cuándo |
|---|---|---|
| `404` | `WORK_NOT_FOUND` / `PUBLIC_LINK_NOT_FOUND` | No es suya, o ese token nunca existió |
| `409` | `USE_THE_NORMAL_FLOW` | Quien abre tiene sesión. Lleva `workId` |
| `410` | `PUBLIC_LINK_GONE` | Revocado, caducado, o de una obra bloqueada |
| `422` | `PUBLIC_LINK_CAP_OUT_OF_RANGE` | El tope va de 1 a 100 |
| `422` | `PUBLIC_LINK_EXPIRY_IN_THE_PAST` | La caducidad tiene que estar en el futuro |
| `429` | `TOO_MANY_ATTEMPTS` | Límite por origen. Lleva `Retry-After` |

**El `410` no es un `404` disfrazado.** Quien tiene la URL sabe que existió; fingir lo contrario
no protege nada y le deja sin entender por qué no funciona.

El límite es **por origen y no por token**, que es lo contrario de lo que parece natural:
limitar un token protegería al enlace de ser abierto mucho, que es justo lo que su autor quiere.
Lo que hay que frenar es a quien prueba tokens distintos.

### Efectos

Ninguno sobre créditos, en ninguna de las cinco operaciones. Crear y revocar no publican
eventos: el enlace es un asunto interno de `Work` hasta que alguien corrige por él, y entonces
el hecho lo publica `Feedback`.

---

## `GET /api/v1/works/{workId}/share`

**`operationId`:** `getWorkShareCard` · **Funcionalidad:** [`FEAT-WRK-011`](../../features/work/FEAT-WRK-011-share-a-work-outside.md)

### Propósito

Que pegar el enlace de una obra en WhatsApp o Twitter pinte una tarjeta en vez de una URL
desnuda.

### Autorización

**Pública.** Quien la pide es un rastreador, sin sesión. Se cachea en público cinco minutos.

### Semántica

**No genera ningún enlace**, aunque el título de la ficha diga «generar». El enlace es la
dirección canónica de la obra; lo que esto añade son los metadatos de la previsualización.

Se consideró que llevara un token y se descartó: una obra inédita es el activo del producto, y
un enlace que la abre circulando por redes sociales es el peor sitio posible para una
credencial. Para enseñar una obra inédita a quien tú quieras ya está
[`FEAT-WRK-010`](../../features/work/FEAT-WRK-010-public-correction-link.md), con su token y
su límite de usos.

**Solo obras visibles para cualquiera.** Un borrador responde `404`, el mismo que una obra
inexistente: decir cuál de las dos es contaría que hay una obra inédita ahí.

La tarjeta **no nombra a nadie**: poner el autor obligaría a resolver su privacidad de perfil
para un visitante sin sesión.
