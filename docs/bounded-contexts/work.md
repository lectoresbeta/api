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
- El cálculo del **número de palabras** de cada capítulo, que `Credits` usa para el precio.
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
| El precio en créditos de una corrección (calcula las **palabras**, no el precio) | `Credits` |
| Las publicaciones del muro que promocionan la obra | `Community` |

## Conceptos

| Concepto | Responsabilidad |
|---|---|
| `Manuscript` | La obra: metadatos, **estado**, modalidad de acceso |
| `Chapter` | Fragmentos y su contenido |
| `Questionnaire` | Preguntas que dirigen el feedback. **Versionado**: una corrección responde siempre a la versión con la que empezó. Su configuración determina el precio (`FEAT-CRD-016`) |
| `Authorship` | Registro de autoría inmutable |
| `PublicLink` | Acceso sin sesión a una obra |
| `Ingestion` | Importación desde `.doc`, `.docx`, `.pdf`, `.txt` |
| `Catalog` | Búsqueda y listado de obras |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `Work` | `WorkId` | Un único autor. Al menos un fragmento. El `TextTier` se deriva del contenido, no se fija a mano. Está siempre en **exactamente uno** de los tres `WorkStatus`. |
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
| `WorkStatus` | `DRAFT`, `PUBLISHED`, `IN_CORRECTION`. **Sustituye a `Visibility` en la obra** (`W-9`) |
| `Visibility` | `VISIBLE`, `HIDDEN` — queda **solo para el fragmento** |
| `BetaReaderAccessMode` | `PUBLIC`, `ON_REQUEST`, `PRIVATE` |
| `Genre` | Se apunta por **código** al catálogo de `User`. Una obra declara de cero a tres |
| `ContentWarning` | `SEXUAL_CONTENT`, `GRAPHIC_VIOLENCE`, `SELF_HARM`, `SUBSTANCE_USE`, `STRONG_LANGUAGE`. Catálogo **cerrado** y sin tope: estas etiquetas quitan obras del catálogo, así que pasarse se castiga solo. No apta para menores **no es una de ellas**: va por su propio eje (`FEAT-WRK-017`) |

## Eventos publicados

| Evento | Cuándo | Consumidores |
|---|---|---|
| `WorkCreated` | Se crea la obra | `Reading`, `Notification` |
| `WorkPublished` | La obra pasa a `PUBLISHED` | `Reading`, `Community`, `Notification` |
| `WorkOpenedForCorrection` | La obra pasa a `IN_CORRECTION` | **`Credits`**, `Reading`, `Notification` |
| `WorkClosedForCorrection` | La obra sale de `IN_CORRECTION` | **`Credits`** (libera retenciones), `Reading` |
| `ChapterContentUpdated` | Cambia el texto de un capítulo | **`Credits`** (calcula su precio), `Feedback` |
| `WorkAccessModeChanged` | Cambia la modalidad de acceso | `Reading` |
| `WorkDeleted` | Se elimina la obra | `Reading`, `Feedback`, `Community` |
| `QuestionnaireUpdated` | Cambia el cuestionario: nueva versión | **`Credits`** (recalcula coste **y** recompensa de esa obra) |

## Eventos consumidos

| Evento | Origen | Efecto |
|---|---|---|
| `ChapterCorrectabilityChanged` | **`Credits`** | El filtro duro del catálogo y su primer factor: si el capítulo admite corrección y cuántas puede pagar su autor |
| `ChapterPriceChanged` | **`Credits`** | La insignia de la tarjeta: lo que gana quien corrija ese capítulo |
| `FeedbackSubmitted` | **`Feedback`** | Cuenta una corrección recibida, que **baja** la obra en el catálogo |

Los tres alimentan el read model del catálogo y no tocan ningún agregado. Es lo que permite
ordenar por capacidad de pago **sin un solo `JOIN`** con las tablas de otro contexto
([`decision:0008`](../decisions/0008-catalogue-ordering.md)).

`ChapterPriceChanged` es el único que lleva una cifra de créditos, y llega **ya traducida**:
`Work` la copia en la señal y no sabe calcularla (`FEAT-CRD-013` `RN-1`). Los demás no llevan
dinero — `affordableCorrections` es una conclusión acotada a diez, no un saldo.

## Contratos publicados

| Contrato | Responde | Quién pregunta |
|---|---|---|
| `CorrectionBriefs` | Qué pregunta el autor en **un capítulo concreto**, de quién es la obra, si admite correcciones y bajo qué modalidad | `Feedback` |
| `WorkAccessBriefs` | De quién es una obra, cómo está abierta, qué declara contener y si existe para alguien que no sea su autor | `Reading` |

Es síncrono a conciencia: los enunciados del cuestionario son **texto del autor**, y por eso
`QuestionnaireUpdated` no los transporta. Quien abre el panel de corrección los necesita en
ese momento, que es el caso para el que
[`decision:0014`](../decisions/0014-published-contracts-between-contexts.md) permite un
contrato.

Filtra las preguntas por capítulo antes de entregarlas: cuál es el último capítulo solo lo
sabe este contexto.

`WorkAccessBriefs` es su hermano por obra, y el que **cierra el primer ciclo del sistema**:
`Work` ya preguntaba a `Reading` si alguien es lector beta, y ahora `Reading` le pregunta a él
por la obra. Los motivos y la regla que lo mantiene inofensivo están en
[`decision:0015`](../decisions/0015-work-and-reading-ask-each-other.md). Devuelve **hechos, no
un veredicto**: quién puede pedir qué lo decide `Reading`.

Lleva sinopsis y clasificación de contenido por una pantalla concreta: la lista de
invitaciones ([`FEAT-RDG-005`](../features/reading/FEAT-RDG-005-resolve-invitation.md)). Es el
único sitio del producto donde alguien acepta leer algo que no ha podido ojear —la obra puede
ser privada, o un borrador—, así que lo que en otro caso estaría en la tarjeta del catálogo
tiene que viajar con la oferta.

## Reglas de negocio

- `RN-1` Una obra tiene un único autor.
- `RN-2` Un fragmento pertenece a una sola obra.
- `RN-3` El `TextTier` se deriva siempre del número de palabras. Nunca se edita directamente.
- `RN-4` Una obra en `DRAFT`, o un fragmento `HIDDEN`, solo son accesibles para su autor.
- `RN-7` **Solo una obra en `IN_CORRECTION` admite feedback nuevo.** `PUBLISHED` se puede leer
  pero no comentar: el autor decide cuándo abre esa puerta, porque recibir feedback le cuesta
  créditos.
- `RN-8` `BetaReaderAccessMode` responde «quién puede comentar» y solo tiene efecto en
  `IN_CORRECTION`. `WorkStatus` responde «si se puede comentar». Son dos ejes distintos.
- `RN-5` El registro de autoría es inmutable: editar la obra genera uno nuevo, no modifica el anterior.
- `RN-6` Cambiar la modalidad de acceso no revoca los accesos ya concedidos.
- `RN-9` La clasificación de contenido sensible es **del autor y explícita**: el indicador de
  público no tiene valor por defecto, porque suponer «apta para menores» cuando nadie lo ha
  dicho es el error que esa clasificación existe para evitar (`FEAT-WRK-017`).

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| W-1 | ¿En qué momentos se genera el registro de autoría? ¿En cada edición, al publicar, o cuando lo decide el autor? | **El documento de origen deja esta pregunta abierta explícitamente** |
| W-2 | ¿Se versiona el contenido al editar o se sobrescribe? (`D-4`, `P-3`) | Afecta a autoría y a la validez del feedback previo |
| W-3 | ¿Qué formatos exactos se aceptan? El documento dice `.doc`, `.pdf`, `.txt`; ¿también `.docx`, `.odt`, `.epub`? | Adaptadores de ingesta |
| W-4 | ¿Cómo se dividen en fragmentos los ficheros subidos: automáticamente por capítulos, o a mano? | Complejidad de la ingesta |
| W-5 | ¿Existe un estado de publicación además de la visibilidad? | **Resuelta:** sí, `WorkStatus` con tres valores (`FEAT-WRK-016`) |
| **W-9** | ¿`WorkStatus` sustituye a `Visibility` en la obra? | Modelo de `Work` |
| W-10 | ¿Qué transiciones de estado son legales? ¿Se puede despublicar? | Máquina de estados |
| W-11 | ¿Se puede editar una obra mientras está en corrección? | El texto cambiaría bajo los pies del lector |
| W-14 | ¿Cuántas correcciones admite una obra a la vez? | Con reserva por obra habría que declararlo (`R-1`) |
| W-6 | ¿La visibilidad de un fragmento es independiente de la de la obra? | Reglas de autorización |
| ~~W-7~~ | ¿El catálogo de temáticas (`Genre`) es cerrado o libre? | **Resuelta:** cerrado, y **no es de este contexto**. Lo posee `User` y `Work` lo consulta por contrato publicado |
| W-8 | ¿Se puede limitar el número de lectores beta de una obra? | Control de coste en créditos |
