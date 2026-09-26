---
id: FEAT-WRK-001
title: Crear obra con el editor WYSIWYG
context: Work
concept: Manuscript
actors: [Writer]
spec_status: APPROVED
impl_status: PARTIAL
priority: P0
sources:
  - _sources/use-cases.pdf#p1
endpoints: [POST /works, POST /works/{workId}/chapters]
events: [WorkCreated, ChapterContentUpdated]
depends_on: [FEAT-USR-004]
updated: 2026-09-24
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

## Cómo se almacena el contenido

**HTML saneado con lista blanca corta, más texto plano derivado** (`Q-4`, resuelta).

```text
content_html  ← lo que se guarda, saneado al escribir
content_text  ← derivado, fuente de verdad para el recuento y la búsqueda
```

### La lista blanca

| Se admite | Para qué |
|---|---|
| `p`, `br` | Párrafos y saltos |
| `strong`, `em` | **Negrita** y *cursiva* |
| `blockquote` | Citas |
| `h2`, `h3` | Subtítulos dentro de un capítulo |
| `hr` | Separador de escena |

Y **nada más**. En particular:

- **Sin enlaces.** Ni `<a>` ni imágenes incrustadas. La literatura no los necesita, y quitarlos
  elimina de golpe el vector de spam más obvio y la mitad de la complejidad del saneado. Es
  una restricción que casi nadie echará de menos y que ahorra problemas durante años.
- Sin tablas, sin estilos en línea, sin `span`, sin `div`.

### Dos reglas que son la mitad del valor

**Se sanea al escribir, nunca al renderizar.** No se guarda nada que no se estaría dispuesto a
servir. Si el saneado ocurriera en la lectura, cualquier camino nuevo hacia esos datos —una
exportación, un correo, una API futura— se convertiría en un agujero.

**El recuento de palabras se hace sobre `content_text`**, no sobre el HTML. No es un detalle de
implementación: de ese número depende **el precio de toda corrección**
([`FEAT-CRD-016`](../credits/FEAT-CRD-016-effort-based-pricing.md) `RN-8`), así que tiene que
ser estable y no depender del marcado.

### Por qué HTML y no Markdown

Markdown sería más seguro por construcción y más fácil de contar. Pierde en lo que más ocurre
aquí: **un autor pegando cincuenta mil palabras desde Word**. Un editor visual sobre HTML
saneado absorbe ese pegado; uno sobre Markdown produce un desastre que el autor tiene que
limpiar a mano.

La tercera opción —una estructura propia tipo JSON— tenía sentido cuando el feedback se
anclaba a fragmentos del texto. Desde que la corrección es un cuestionario
([`decision:0006`](../../decisions/0006-credit-system.md)) esa necesidad desapareció, y con
ella su principal ventaja.

## Reglas de negocio

- `RN-1` Toda obra tiene exactamente un autor: el usuario autenticado.
- `RN-2` Una obra tiene al menos un fragmento. Una obra sin contenido no es válida.
- `RN-3` El número de palabras se calcula a partir del contenido de todos sus fragmentos.
- `RN-4` ~~El `TextTier` se deriva del número de palabras.~~ **Derogado** por
  [`decision:0006`](../../decisions/0006-credit-system.md): no hay tramos. El precio es una
  fórmula continua sobre las palabras del capítulo
  ([`FEAT-CRD-016`](../credits/FEAT-CRD-016-effort-based-pricing.md)). Lo que sigue en pie es
  que **el recuento lo calcula el servidor y nunca lo fija el usuario**.
- `RN-5` Por defecto la obra se crea con `Visibility: HIDDEN` y
  `BetaReaderAccessMode: ON_REQUEST`. *(Pendiente de confirmar, `Q-1`.)*
- `RN-6` El título es obligatorio y no puede estar vacío.
- `RN-7` Al crear la obra se genera un `AuthorshipRecord`. *(Momento exacto pendiente, `W-1`.)*
- `RN-8` El contenido enriquecido se almacena de forma saneada: no se acepta HTML arbitrario.
- `RN-9` La obra declara **de cero a tres temáticas**, por código, del catálogo que posee
  `User` ([`FEAT-USR-023`](../user/FEAT-USR-023-onboarding-select-genres.md)). Son opcionales:
  una obra sin clasificar existe, solo que no aparece cuando alguien filtra el catálogo. Un
  código desconocido se rechaza **nombrándolo**, nunca se descarta en silencio.

`RN-9` cierra `Q-6` para la temática y `W-7` para el catálogo. El tope de tres no es
cosmético: el filtro del catálogo es multiselección **en `O`**
([`FEAT-WRK-012`](FEAT-WRK-012-browse-catalogue.md), `L-7`), y una obra que declarase ocho
temáticas aparecería en casi cualquier búsqueda.

## Flujo principal

1. El escritor abre el editor y escribe el título y el contenido.
2. Opcionalmente divide la obra en varios fragmentos (`FEAT-WRK-003`).
3. Opcionalmente indica temática, sinopsis y otros metadatos.
4. Envía la obra.
5. El sistema valida el título.
6. El sistema persiste la obra, **vacía y en `DRAFT`**, y publica `WorkCreated`.
7. Por cada capítulo, el sistema sanea su contenido, cuenta sus palabras y actualiza los
   totales de la obra.
8. El registro de autoría se genera cuando lo decida `W-1` (`FEAT-WRK-009`).
10. Se devuelve la obra creada.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Título vacío o ausente | Se rechaza | `422` con detalle del campo |
| Sin contenido en ningún fragmento | Se rechaza (`RN-2`) | `422` |
| Contenido con HTML no permitido | Se sanea, no se rechaza | `201` con el contenido saneado |
| Contenido que tras sanear se queda sin texto | Se rechaza | `422` con `code: EMPTY_CHAPTER` |
| Capítulo sobre una obra ajena | Se rechaza **como si no existiera** | `404` con `code: WORK_NOT_FOUND` |
| Cuenta sin activar | Se rechaza | `403` con `code: ACCOUNT_NOT_ACTIVATED` |
| Contenido que supera las 75.000 palabras | Sin definir (`C-6`) | Pendiente |
| Sin sesión | Se rechaza | `401` |
| Fallo al generar el registro de autoría | Sin definir: ¿falla la creación o se reintenta? (`Q-3`) | Pendiente |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Crear obra | `POST /works` | `createWork` |
| Añadir fragmento | `POST /works/{workId}/chapters` | `addChapter` |
| Clasificar la obra | `PUT /works/{workId}/genres` | `setWorkGenres` |

`setWorkGenres` es un `PUT` con la lista entera, como el modo de acceso y el estado: lo que el
autor envía es **cómo queda clasificada su obra**, no un cambio incremental. No hay «añadir
una temática», y no es una omisión — clasificar es decir qué es una obra, no ir apilando
etiquetas.

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
| `WorkCreated` | Tras persistir la obra | `workId`, `authorId`, `title`, `accessMode`, `status`, `createdAt` |
| `ChapterContentUpdated` | Tras persistir un capítulo | `chapterId`, `workId`, `authorId`, `position`, `wordCount`, `updatedAt` |

El contenido de la obra **no viaja en ningún evento**, y tampoco nada derivado de leerlo salvo
el recuento. Una obra nace en `DRAFT`: es obra inédita, y una cola que persiste, reintenta y
aparca mensajes es el último sitio donde debe estar.

`WorkCreated` va sin `wordCount` porque al crearse la obra es cero, y sin `textTier`, que ya
no existe. El recuento llega con el capítulo, que es la unidad que se corrige y se cobra
([`FEAT-CRD-016`](../credits/FEAT-CRD-016-effort-based-pricing.md) `RN-2`).

`position` acompaña al recuento porque el último capítulo de una obra responde también las
preguntas de alcance `LAST_CHAPTER`, y `Credits` no puede saber cuál es preguntándoselo a
`Work`.

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

Índices necesarios: `work(author_id)`, `chapter(work_id, position)` y `work_genre(genre_code)`
para el filtro del catálogo.

La temática **no es una columna de `work`**: es una tabla `work_genre` con una fila por obra y
código. Una obra declara varias, y el índice por código es el que usa el catálogo.

## Diseño (Figma)

Pendiente. Se completará con la página correspondiente.

Aspectos que el diseño debe resolver: capacidades exactas del editor, si hay guardado
automático, cómo se crean y ordenan los fragmentos, y qué metadatos se piden al crear frente
a después.

## Criterios de aceptación

- [ ] Un usuario autenticado y **con la cuenta activada** crea una obra y recibe `201` con su `workId`.
- [ ] Una cuenta sin activar recibe `403` con `ACCOUNT_NOT_ACTIVATED`.
- [ ] La obra creada tiene como autor al usuario autenticado, aunque la petición incluya otro `authorId`.
- [ ] Una obra sin título se rechaza con `422`.
- [ ] Un capítulo que tras sanear se queda sin texto se rechaza con `422`.
- [ ] El `wordCount` devuelto coincide con el número de palabras del contenido.
- [ ] El usuario no puede fijar `wordCount` en la petición.
- [x] Una obra puede nacer clasificada, y reclasificarse después sin consecuencias.
- [x] Una temática que no está en el catálogo se rechaza **diciendo cuál**.
- [x] Una obra no declara más de tres temáticas.
- [ ] Se publica `WorkCreated` y su payload no contiene el contenido de la obra.
- [ ] Añadir un capítulo publica `ChapterContentUpdated` con el recuento y sin una palabra del texto.
- [ ] Una petición sin sesión recibe `401`.
- [ ] El contenido enriquecido se almacena saneado frente a inyección de HTML y scripts.

## Estado inicial y forma de la creación

- `Q-1`, resuelta: una obra nace en **`DRAFT`**. Es el único valor seguro: nacer visible
  expondría contenido inédito por un descuido.
- `Q-2`, resuelta: la creación es **incremental**. Se crea la obra con sus metadatos y después
  se añaden capítulos. Una novela de cuarenta capítulos no cabe en una petición, y obligar a
  ello convertiría cada guardado en un envío completo.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| Q-3 | ¿Qué ocurre si falla la generación del registro de autoría? | Sigue abierta, y **bloquea a `W-1`**: hasta saber *cuándo* se genera no tiene sentido decidir qué pasa si falla |
| Q-5 | ¿Hay borradores con guardado automático? | Cambia el ciclo de vida de la obra (`W-5`) |
| Q-6 | ¿Qué metadatos son obligatorios: sinopsis, portada? | **La temática ya no**: es opcional (`RN-9`). Sigue abierto para el resto |

## Estado

**Especificación:** `APPROVED` (2026-09-24). `Q-4`, `Q-1` y `Q-2` resueltas. Lo que queda
—registro de autoría, guardado automático, metadatos obligatorios— no impide implementar
la creación de una obra.

**Implementación:** `PARTIAL`.

Hecho: `POST /api/v1/works` y `POST /api/v1/works/{workId}/chapters`, con el saneado contra
lista blanca, el recuento sobre texto plano y la publicación de `WorkCreated`. Cubierto por
`tests/Functional/Work/CreateWorkTest.php` y `tests/Unit/Work/ContentSanitiserTest.php`.

Es además la primera escritura **no exenta** del proyecto, así que sus tests son los que por
fin comprueban que la barrera de
[`FEAT-USR-025`](../user/FEAT-USR-025-block-writes-until-activation.md) **bloquea**, y no solo
que deja pasar lo que debe.

El detalle que más caro habría salido: el saneador, por defecto, tira un elemento
desconocido **con sus hijos**. Un pegado desde un procesador de textos —todo `span` y `div`—
habría llegado vacío mientras la petición respondía «creado». Por eso `ContentPolicy` tiene
dos listas: la de lo que se conserva y la de lo que **pierde el marcado y conserva el texto**.

**Falta:**

- el **registro de autoría** (`RN-7`): depende de `W-1`, que sigue sin decidir **cuándo** se
  genera, y generarlo al crear una obra vacía no significaría nada;
- reordenar, editar y borrar capítulos (`FEAT-WRK-003`);
- el límite superior de extensión (`C-6`) y los metadatos obligatorios (`Q-6`).
