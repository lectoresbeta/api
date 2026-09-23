---
id: FEAT-WRK-014
title: Definir el cuestionario que acompaña a la obra
context: Work
concept: Questionnaire
actors: [Writer]
spec_status: APPROVED
impl_status: PARTIAL
priority: P0
sources:
  - conversation:2026-09-22 (el formulario lo configura el autor y condiciona los créditos)
  - docs/ui/read-chapter.md
  - _sources/credit-system.pdf#p4
endpoints:
  - GET /works/{workId}/questionnaire
  - PUT /works/{workId}/questionnaire
depends_on: [FEAT-WRK-001]
events: [QuestionnaireUpdated]
updated: 2026-09-23
---

# FEAT-WRK-014 — Definir el cuestionario que acompaña a la obra

## Resumen

El autor decide **qué va a preguntar** a quienes corrijan su obra. Puede ser desde un único
campo de texto libre —«cuéntame qué te ha parecido»— hasta varias preguntas concretas sobre
personajes, ritmo o final.

Esa configuración no es solo una plantilla de formulario: **determina cuánto le cuesta al
autor recibir una respuesta y cuánto gana el lector que la escribe**
([`FEAT-CRD-016`](../credits/FEAT-CRD-016-effort-based-pricing.md)).

## Por qué esto sube de prioridad

El cuestionario parecía un accesorio de la obra. No lo es: es **el instrumento con el que el
autor fija el precio de su propia corrección**. Pasa de `P1` a `P0` porque ninguna corrección
puede existir sin él y porque ninguna cifra de créditos puede calcularse sin él.

## Qué contiene un cuestionario

| Elemento | Descripción |
|---|---|
| Preguntas | Lista ordenada. El autor escribe el enunciado |
| Enunciado | Texto libre, específico de la obra |
| Ejemplo | Texto de ayuda que el lector ve como marcador |
| Obligatoriedad | Si la pregunta debe responderse para poder enviar |
| Longitud mínima y máxima | Por respuesta, **en palabras** (`R-5`, resuelta) |

> **El mínimo de palabras no es solo una validación: es el precio.** La suma de los mínimos de
> todas las preguntas —`requiredWords`— es el segundo término de la fórmula de
> [`decision:0006`](../../decisions/0006-credit-system.md). Un cuestionario sin mínimos
> valdría 0 en ese término, y **una novela entera se corregiría por 2 créditos**.
>
> **`C-14` resuelta: declarar el mínimo es obligatorio.** Una pregunta sin mínimo obligaría a
> `Credits` a suponer un valor —el suelo de `ChapterPricing`, 25 palabras— sobre un total que
> llega ya sumado, y no puede deshacerse una suma para aplicar un suelo a los sumandos. Con el
> mínimo obligatorio, `requiredWords` es exacto y el suelo de `ChapterPricing` queda donde
> debe: guardando las entradas que **no** vienen de un cuestionario.

Las preguntas de la maqueta mencionan al protagonista por su nombre —«la conexión entre el
protagonista, Ryn, y su misión»—, lo que confirma que **las escribe el autor para su obra
concreta**, no salen de un catálogo fijo.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Writer` | Crear y editar el cuestionario de su obra | Es el autor |
| Cualquier otro | Leer el cuestionario | Tiene acceso de lector beta a la obra |

## Alcance de cada pregunta

**Decidido** (`W-17`): cada pregunta declara a qué capítulos aplica.

| `scope` | Se responde en |
|---|---|
| `EVERY_CHAPTER` | Todos los capítulos |
| `LAST_CHAPTER` | Solo el último |

Resuelve el problema que la maqueta dejaba a la vista: una pregunta como *«¿qué te pareció el
final de la historia?»* no tiene respuesta posible en el capítulo 1, y **el autor pagaría por
ella igualmente** al ser la corrección por capítulo.

Con el `scope`, el precio de cada capítulo cuenta **solo las preguntas que aplican en él**, así
que el autor no paga por lo que no puede recibir.

Por defecto, `EVERY_CHAPTER`: es lo que espera quien no se plantea la distinción.

## Cómo consulta `Work` el precio

**Decidido** (`W-12`): mediante un **contrato de consulta explícito** que expone `Credits`.

`POST /works/{workId}/questionnaire/estimate` devuelve **cifras y nada más** —coste por
capítulo y recompensa— sin exponer el modelo de `Credits` ni permitir navegarlo.

[`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md) prohíbe el
acoplamiento al modelo, no la consulta con contrato. Y aquí la consulta síncrona está
justificada: **el autor necesita ver el precio mientras configura**, y un evento no sirve
porque la respuesta se necesita en el momento.

## Reglas de negocio

- `RN-1` El autor define **un cuestionario por obra**, y ese cuestionario se responde **en
  cada capítulo** (`R-2`). Ver la tensión que eso abre más abajo (`W-17`).
- `RN-2` Un cuestionario tiene **al menos una pregunta**. El caso mínimo —un solo campo de
  texto libre— es un cuestionario de una pregunta, no la ausencia de cuestionario.
- `RN-3` Hay un **máximo de preguntas** —20, `W-11` resuelta— y un **máximo de palabras
  exigidas** —2.000—, que es lo que realmente acota el precio. Sin tope, un autor con saldo
  podría pedir cuarenta respuestas y convertir la corrección en un trabajo inabordable.
  Las 2.000 palabras no son una cifra redonda cualquiera: `ceil(requiredWords / 100)` alcanza
  ahí el tope de 20 créditos de [`decision:0006`](../../decisions/0006-credit-system.md), así
  que **a partir de ese punto el autor no paga más y el lector escribe más**.
- `RN-4` Editar el cuestionario **crea una versión nueva**. Las versiones anteriores se
  conservan porque hay correcciones que las responden.
- `RN-5` Cambiar el cuestionario **no altera los precios ya anotados** ni las correcciones
  en curso: quien empezó con unas condiciones las conserva
  ([`FEAT-CRD-016`](../credits/FEAT-CRD-016-effort-based-pricing.md), `RN-3`).
- `RN-6` El autor ve **el coste mientras configura**, no después de guardar.
- `RN-7` Un cuestionario puede editarse con la obra `IN_CORRECTION`, pero solo afecta a las
  correcciones que empiecen después.
- `RN-8` El texto de las preguntas está sujeto a las mismas reglas de contenido que el resto
  de lo que un usuario publica.
- `RN-9` El precio que el autor ve depende de **la longitud del capítulo y del cuestionario**
  (`P-2`), así que no es una cifra única por obra: **cada capítulo tiene la suya**. Lo que el
  autor configura una vez se traduce en tantos precios como capítulos tenga.

`RN-4` es la regla que sostiene todo lo demás. Sin versionado, una corrección entregada
quedaría huérfana: respuestas sin las preguntas que las motivaron, y el autor leyendo un
texto que no sabe a qué contesta.

## Flujo principal

1. El autor abre la configuración del cuestionario de su obra.
2. Añade, edita, reordena o elimina preguntas.
3. Mientras edita, el sistema le muestra **el coste estimado** por corrección recibida y **la
   recompensa** que percibirá el lector. Como el precio depende también de la longitud del
   texto (`P-2`) y la corrección es por capítulo, esa estimación es **por capítulo**
   (`W-18`).
4. Guarda.
5. Se crea una versión nueva y se publica `QuestionnaireUpdated`.
6. `Credits` recalcula las cifras asociadas a esa obra.

El paso 3 depende de que `Credits` pueda **simular** el cálculo sin comprometer nada. Es un
caso de consulta síncrona entre contextos y necesita un contrato explícito, no un acceso
directo al modelo de `Credits` (`W-12`).

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Cuestionario sin preguntas | Se rechaza | `422` |
| Más preguntas que el máximo | Se rechaza | `422` indicando el tope |
| Longitud mínima mayor que la máxima | Se rechaza | `422` |
| Todas las preguntas marcadas «solo último capítulo» | Se rechaza: los demás capítulos quedarían sin cuestionario | `422` |
| No es el autor | Se rechaza | `403` |
| Obra inexistente | | `404` |
| Edición concurrente de dos sesiones | Gana la primera; la segunda recibe conflicto | `409` con `If-Match` |
| El autor no tiene saldo para el coste resultante | **Se permite guardar** | `200` con aviso |

El último caso es deliberado: configurar el cuestionario no gasta nada. Lo que requiere saldo
es **abrir la obra a corrección** (`FEAT-WRK-016`). Bloquear la edición por falta de saldo
impediría al autor preparar su obra mientras ahorra créditos.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Consultar el cuestionario vigente | `GET /works/{workId}/questionnaire` | `getWorkQuestionnaire` |
| Sustituir el cuestionario | `PUT /works/{workId}/questionnaire` | `updateWorkQuestionnaire` |
| Simular coste y recompensa | `POST /works/{workId}/questionnaire/estimate` | `estimateQuestionnairePricing` |

`PUT` usa `If-Match` sobre la versión vigente
([`concurrencia`](../../api/conventions/concurrency-and-idempotency.md)).

La respuesta de `GET` es distinta según quién pregunte: el autor ve la configuración
completa; el lector ve solo lo que necesita para responder. **Las dos no son la misma
representación** aunque describan el mismo objeto.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `QuestionnaireUpdated` | Al guardar una versión nueva | `eventId`, `workId`, `version`, `questionCount`, **`requiredWords`** |

El payload incluye **los atributos que `Credits` necesita para calcular**, no el enunciado de
las preguntas: el texto es contenido del autor y no tiene por qué circular por la cola.

`requiredWords` —la suma de los mínimos de palabras de todas las preguntas— es el dato que
importa: es el **segundo término del precio**
([`FEAT-CRD-016`](../credits/FEAT-CRD-016-effort-based-pricing.md)).

Que un solo número baste es lo que mantiene simple este contrato: `Credits` no necesita saber
cuántas preguntas hay ni de qué tipo son.

**Consume**

Ninguno.

## Efectos en créditos

Ninguno directo: configurar no gasta. El hecho publicado permite a `Credits` mantener
actualizado el precio de esa obra, que se aplicará cuando alguien obtenga acceso.

## Modelo de datos afectado

`Questionnaire` (dentro del agregado `Manuscript` o adyacente a él):

| Campo | Notas |
|---|---|
| `id` | |
| `workId` | |
| `version` | Entero incremental |
| `questions` | Colección ordenada de `Question` |
| `updatedAt` | |

`Question`: `id`, `position`, `statement`, `example`, `required`, `minWords`, `maxWords` y
`scope` (`EVERY_CHAPTER` / `LAST_CHAPTER`, pendiente de `W-17`).

Las versiones antiguas se conservan (`RN-4`). Un índice `(workId, version)` sirve tanto para
recuperar la vigente como para resolver la que respondió una corrección concreta.

## Diseño (Figma)

No hay pantalla de configuración en el material recibido. Lo que se conoce del cuestionario
viene de [`../../ui/read-chapter.md`](../../ui/read-chapter.md), es decir, **del lado del
lector**. La pantalla del autor está pendiente (`W-13`).

Eso significa que la forma exacta de los controles —cómo se marca obligatoria una pregunta,
si se pueden reordenar arrastrando, si hay tipos de pregunta más allá del texto libre— no
está confirmada por diseño.

## Criterios de aceptación

- [ ] El autor puede definir entre una y `N` preguntas para su obra.
- [ ] Un cuestionario sin preguntas se rechaza.
- [ ] Guardar crea una versión nueva y conserva la anterior.
- [ ] Una corrección en curso sigue respondiendo a la versión con la que empezó.
- [ ] El autor ve coste y recompensa **antes** de guardar, con el detalle por capítulo.
- [ ] `QuestionnaireUpdated` se publica una sola vez por versión.
- [ ] El evento no contiene el enunciado de las preguntas.
- [ ] Solo el autor puede editar; un lector beta solo puede leer.
- [ ] Dos ediciones concurrentes no producen versiones perdidas.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~W-17~~ | Si la corrección es por capítulo, ¿tiene sentido repetir en cada capítulo preguntas que hablan de «la historia» o «el final»? | Ver abajo. Afecta al modelo y a la calidad del feedback |
| ~~W-11~~ | ¿Cuál es el máximo de preguntas? | **Resuelta:** 20 preguntas y 2.000 palabras exigidas (`RN-3`) |
| ~~W-12~~ | ¿Cómo consulta `Work` el precio a `Credits` para mostrarlo al autor? | Es una consulta síncrona entre contextos: necesita contrato explícito |
| W-13 | ¿Existe pantalla de configuración en Figma? | Sin ella, el detalle del formulario se está deduciendo |
| W-14 | ¿Hay tipos de pregunta además del texto libre (escala, sí/no, opción múltiple)? | Cambia el modelo y probablemente el precio |
| W-15 | ¿Hay cuestionarios plantilla sugeridos para autores que no saben qué preguntar? | Producto |
| W-18 | ¿El coste se muestra por capítulo o agregado para toda la obra? | `RN-9`: ya no hay una cifra única |
| ~~C-14~~ | ¿Es obligatorio declarar el mínimo de palabras? | **Resuelta:** sí. Ver arriba |
| W-16 | ¿Se puede editar el cuestionario con correcciones ya recibidas? | `RN-7` dice que sí; conviene confirmarlo |

### `W-17` — un cuestionario de obra respondido capítulo a capítulo

**Resuelta `R-2`: la corrección es por capítulo.** Eso deja una tensión que conviene no
esconder.

Las preguntas de la maqueta son de **obra entera**:

> 4. ¿Qué te pareció el final de la historia?

Preguntada en el capítulo 1 de una novela, no tiene respuesta posible. Si el mismo
cuestionario se responde en cada capítulo, el autor recibe tres o cuatro veces la misma
pregunta mal planteada, **y paga por cada una**.

Opciones, de menor a mayor esfuerzo:

| Opción | A favor | En contra |
|---|---|---|
| Un cuestionario por obra, idéntico en todos los capítulos | Simple; el autor configura una vez | Preguntas sin sentido en capítulos intermedios |
| Un cuestionario por obra, con preguntas marcadas «solo último capítulo» | Conserva la simplicidad y resuelve el caso real | Añade un atributo a la pregunta |
| Un cuestionario por capítulo | Máxima precisión | El autor de una novela de 30 capítulos configura 30 formularios |

**Recomendación: la segunda.** Un `scope` por pregunta —`EVERY_CHAPTER` o `LAST_CHAPTER`—
cubre el caso de la maqueta sin obligar a nadie a configurar treinta formularios, y el precio
de cada capítulo pasa a depender de las preguntas que realmente aplican en él.

La tercera puede añadirse después como excepción sin rehacer el modelo, siempre que el
cuestionario se identifique por obra **y** capítulo desde el principio.

`W-12` es la más incómoda: el autor necesita ver el precio mientras configura, y el precio lo
calcula `Credits`. Un evento no sirve, porque la respuesta se necesita en el momento. La vía
correcta es un **contrato de consulta explícito** que devuelva cifras y nada más, nunca un
acceso al modelo de `Credits`
([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md) lo permite:
prohíbe el acoplamiento, no la consulta con contrato).

## Estado

**Especificación:** `APPROVED` (2026-09-24). `W-17` y `W-12` resueltas: `scope` por pregunta y
contrato de consulta explícito para el precio. `W-11` y `C-14` resueltas al implementar.

**Implementación:** `PARTIAL` (2026-09-23).

### Hecho

- `GET /api/v1/works/{workId}/questionnaire` y `PUT /api/v1/works/{workId}/questionnaire`.
- Agregado `Questionnaire` con sus `Question`, en el esquema `work_ctx`, con índice
  `(work_id, version)`.
- **Versionado** (`RN-4`): guardar crea la versión `n+1` y conserva la anterior íntegra, con
  sus preguntas. Nada se actualiza en sitio, así que una corrección en curso sigue
  respondiendo a la versión con la que empezó (`RN-5`, `RN-7`).
- Validación completa de `RN-2` y `RN-3`, cada caso con su `code` propio:
  `QUESTIONNAIRE_WITHOUT_QUESTIONS`, `TOO_MANY_QUESTIONS`, `TOO_MANY_REQUIRED_WORDS`,
  `INVALID_WORD_RANGE`, `EMPTY_QUESTION`, `MISSING_MINIMUM_WORDS` y
  `QUESTIONNAIRE_ONLY_FOR_LAST_CHAPTER`.
- `If-Match` opcional sobre la versión vigente; una versión caduca produce `409`
  `STALE_VERSION`.
- `QuestionnaireUpdated` con `workId`, `version`, `questionCount`, `requiredWords`,
  `requiredWordsForEveryChapter` y `updatedAt`. **Sin el enunciado de ninguna pregunta**, y
  hay una prueba que lo comprueba buscando en el payload el nombre del protagonista de la
  maqueta.
- Lectura autorizada por `WorkReadPolicy`, la misma que decide quién ve los capítulos: un
  cuestionario describe una obra inédita tan bien como su texto.

### Por qué el evento lleva dos totales y no uno

`requiredWords` es la suma de todos los mínimos; `requiredWordsForEveryChapter`, solo la de
las preguntas con `scope: EVERY_CHAPTER`. El segundo es el que `Credits` necesita para el
precio de un capítulo cualquiera, y el primero para el último, que además responde las de
`LAST_CHAPTER`. Mandar solo el total obligaría a `Credits` a cobrar en todos los capítulos
preguntas que solo se responden en uno —exactamente lo que `W-17` vino a evitar—, y mandar la
lista de preguntas con su alcance sería poner contenido del autor en la cola.

### Falta

- `POST /works/{workId}/questionnaire/estimate` (`estimateQuestionnairePricing`, `RN-6`,
  `W-12`). Necesita un contrato de consulta **de lectura** publicado por `Credits`, que aún no
  existe: va con [`FEAT-CRD-016`](../credits/FEAT-CRD-016-effort-based-pricing.md), no aquí.
  Hasta entonces el autor configura sin ver el precio.
- La vista del lector (`GET /chapters/{chapterId}/questionnaire`) es de
  [`FEAT-FBK-003`](../feedback/FEAT-FBK-003-answer-correction-questionnaire.md). `GET` sobre
  la obra devuelve hoy la configuración completa a cualquiera que pueda leerla; la
  representación reducida nace con la pantalla que la usa.
- `RN-8` —el texto de las preguntas sujeto a las reglas de contenido— está pendiente de que
  existan esas reglas para todo lo demás (`FEAT-MOD-*`). El enunciado se guarda tal cual, y no
  se sirve como HTML.
- Reordenar y editar preguntas sueltas: el `PUT` sustituye el cuestionario entero. Es lo que
  `RN-4` pide —cada guardado es una versión— y basta para la pantalla, pero no hay operación
  de detalle.
