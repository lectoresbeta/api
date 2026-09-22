# Bounded context: `Work`

> Estado: `DRAFT` — Prefijo: `WRK`

## Responsabilidad

Qué es una obra, qué contiene, quién la escribió, cómo se estructura, qué extensión tiene
y bajo qué modalidad se ofrece a los lectores beta.

> **La interfaz llama «relato» a una `Work`** («Mis relatos», «Explorar más relatos»). No
> confundir con `PublishedBook`, que es el libro ya editado fuera de la plataforma y vive en
> `User`.

## Qué posee

- Obras y sus fragmentos.
- El contenido del texto y su ingesta (editor o fichero subido).
- El cálculo del número de palabras y del nivel de extensión (`TextTier`).
- Visibilidad de obra y de fragmento.
- Modalidad de acceso de lectores beta (configuración, **no** los accesos concedidos).
- El cuestionario asociado a la obra.
- El registro de autoría.
- Los enlaces públicos y de redes sociales.
- La búsqueda en el catálogo de obras.

## Qué NO posee

| No es suyo | Es de |
|---|---|
| Las **obras publicadas** del perfil: libros editados fuera de la plataforma, con editorial, año y enlace de compra | `User` (`PublishedBook`) |
| Quién tiene acceso concedido a la obra | `Reading` |
| Los comentarios sobre la obra | `Feedback` |
| Las respuestas al cuestionario | `Feedback` |
| El coste en créditos del texto (calcula el `TextTier`, no el precio) | `Credits` |
| Las publicaciones del muro que promocionan la obra | `Community` |

## Conceptos

| Concepto | Responsabilidad |
|---|---|
| `Manuscript` | La obra: metadatos, visibilidad, modalidad de acceso |
| `Chapter` | Fragmentos y su contenido |
| `Questionnaire` | Preguntas que dirigen el feedback |
| `Authorship` | Registro de autoría inmutable |
| `PublicLink` | Acceso sin sesión a una obra |
| `Ingestion` | Importación desde `.doc`, `.docx`, `.pdf`, `.txt` |
| `Catalog` | Búsqueda y listado de obras |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `Work` | `WorkId` | Un único autor. Al menos un fragmento. El `TextTier` se deriva del contenido, no se fija a mano. |
| `AuthorshipRecord` | `AuthorshipRecordId` | Inmutable. Solo inserción. |
| `PublicLink` | `PublicLinkId` | Token no enumerable. Revocable. |

### Value objects y enums

| Nombre | Reglas |
|---|---|
| `WorkId`, `ChapterId` | UUID v7 |
| `WorkTitle` | Longitud por definir, no vacío |
| `ChapterContent` | Texto; calcula `WordCount` |
| `WordCount` | Entero no negativo |
| `TextTier` | Derivado de `WordCount` según la tabla de [credits.md](credits.md) |
| `Visibility` | `VISIBLE`, `HIDDEN` |
| `BetaReaderAccessMode` | `PUBLIC`, `ON_REQUEST`, `PRIVATE` |
| `Genre` | Catálogo de temáticas, por definir |

## Eventos publicados

| Evento | Cuándo | Consumidores |
|---|---|---|
| `WorkCreated` | Se crea la obra | `Reading`, `Notification` |
| `WorkPublished` | La obra se hace visible | `Reading`, `Community`, `Notification` |
| `WorkContentUpdated` | Cambia el contenido y posiblemente el `TextTier` | `Credits` (referencia de coste), `Feedback` |
| `WorkAccessModeChanged` | Cambia la modalidad de acceso | `Reading` |
| `WorkDeleted` | Se elimina la obra | `Reading`, `Feedback`, `Community` |
| `QuestionnaireUpdated` | Cambia el número de preguntas | `Credits` (coste por pregunta adicional) |

## Reglas de negocio

- `RN-1` Una obra tiene un único autor.
- `RN-2` Un fragmento pertenece a una sola obra.
- `RN-3` El `TextTier` se deriva siempre del número de palabras. Nunca se edita directamente.
- `RN-4` Una obra o fragmento `HIDDEN` solo es accesible para su autor.
- `RN-5` El registro de autoría es inmutable: editar la obra genera uno nuevo, no modifica el anterior.
- `RN-6` Cambiar la modalidad de acceso no revoca los accesos ya concedidos.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| W-1 | ¿En qué momentos se genera el registro de autoría? ¿En cada edición, al publicar, o cuando lo decide el autor? | **El documento de origen deja esta pregunta abierta explícitamente** |
| W-2 | ¿Se versiona el contenido al editar o se sobrescribe? (`D-4`, `P-3`) | Afecta a autoría y a la validez del feedback previo |
| W-3 | ¿Qué formatos exactos se aceptan? El documento dice `.doc`, `.pdf`, `.txt`; ¿también `.docx`, `.odt`, `.epub`? | Adaptadores de ingesta |
| W-4 | ¿Cómo se dividen en fragmentos los ficheros subidos: automáticamente por capítulos, o a mano? | Complejidad de la ingesta |
| W-5 | ¿Existe un estado de publicación (`DRAFT`/`PUBLISHED`) además de la visibilidad? **El modal de créditos habla de poner una obra «en corrección»**, lo que sugiere un estado más | Modelo de ciclo de vida. Ver `M-2` |
| W-6 | ¿La visibilidad de un fragmento es independiente de la de la obra? | Reglas de autorización |
| W-7 | ¿El catálogo de temáticas (`Genre`) es cerrado o libre? | Búsqueda y rankings |
| W-8 | ¿Se puede limitar el número de lectores beta de una obra? | Control de coste en créditos |
