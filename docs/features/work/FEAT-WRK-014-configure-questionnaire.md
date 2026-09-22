---
id: FEAT-WRK-014
title: Definir el cuestionario que acompaña a la obra
context: Work
concept: Questionnaire
actors: [Writer]
spec_status: DRAFT
impl_status: TODO
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
updated: 2026-09-22
---

# FEAT-WRK-014 — Definir el cuestionario que acompaña a la obra

## Resumen

El autor decide **qué va a preguntar** a quienes corrijan su obra. Puede ser desde un único
campo de texto libre —«cuéntame qué te ha parecido»— hasta varias preguntas concretas sobre
personajes, ritmo o final.

Esa configuración no es solo una plantilla de formulario: **determina cuánto le cuesta al
autor recibir una respuesta y cuánto gana el lector que la escribe**
([`FEAT-CRD-016`](../credits/FEAT-CRD-016-questionnaire-based-pricing.md)).

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
| Longitud mínima y máxima | Por respuesta, en la unidad que fije `R-5` |

Las preguntas de la maqueta mencionan al protagonista por su nombre —«la conexión entre el
protagonista, Ryn, y su misión»—, lo que confirma que **las escribe el autor para su obra
concreta**, no salen de un catálogo fijo.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Writer` | Crear y editar el cuestionario de su obra | Es el autor |
| Cualquier otro | Leer el cuestionario | Tiene acceso de lector beta a la obra |

## Reglas de negocio

- `RN-1` Cada obra tiene **un cuestionario**. No hay uno por capítulo (pendiente de `R-2`).
- `RN-2` Un cuestionario tiene **al menos una pregunta**. El caso mínimo —un solo campo de
  texto libre— es un cuestionario de una pregunta, no la ausencia de cuestionario.
- `RN-3` Hay un **máximo de preguntas** (`W-11`). Sin tope, un autor con saldo podría pedir
  cuarenta respuestas y convertir la corrección en un trabajo inabordable.
- `RN-4` Editar el cuestionario **crea una versión nueva**. Las versiones anteriores se
  conservan porque hay correcciones que las responden.
- `RN-5` Cambiar el cuestionario **no altera las retenciones ya hechas** ni las correcciones
  en curso: quien empezó con unas condiciones las conserva
  ([`FEAT-CRD-016`](../credits/FEAT-CRD-016-questionnaire-based-pricing.md), `RN-3`).
- `RN-6` El autor ve **el coste mientras configura**, no después de guardar.
- `RN-7` Un cuestionario puede editarse con la obra `IN_CORRECTION`, pero solo afecta a las
  correcciones que empiecen después.
- `RN-8` El texto de las preguntas está sujeto a las mismas reglas de contenido que el resto
  de lo que un usuario publica.

`RN-4` es la regla que sostiene todo lo demás. Sin versionado, una corrección entregada
quedaría huérfana: respuestas sin las preguntas que las motivaron, y el autor leyendo un
texto que no sabe a qué contesta.

## Flujo principal

1. El autor abre la configuración del cuestionario de su obra.
2. Añade, edita, reordena o elimina preguntas.
3. Mientras edita, el sistema le muestra **el coste estimado** por corrección recibida y **la
   recompensa** que percibirá el lector.
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
| `QuestionnaireUpdated` | Al guardar una versión nueva | `eventId`, `workId`, `version`, `questionCount`, atributos que influyen en el precio |

El payload incluye **los atributos que `Credits` necesita para calcular**, no el enunciado de
las preguntas: el texto es contenido del autor y no tiene por qué circular por la cola.

Cuáles son exactamente esos atributos depende de `P-1` de
[`FEAT-CRD-016`](../credits/FEAT-CRD-016-questionnaire-based-pricing.md). Hasta que esa
pregunta se responda, el contrato del evento no puede cerrarse.

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

`Question`: `id`, `position`, `statement`, `example`, `required`, `minLength`, `maxLength`.

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
- [ ] El autor ve coste y recompensa **antes** de guardar.
- [ ] `QuestionnaireUpdated` se publica una sola vez por versión.
- [ ] El evento no contiene el enunciado de las preguntas.
- [ ] Solo el autor puede editar; un lector beta solo puede leer.
- [ ] Dos ediciones concurrentes no producen versiones perdidas.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **R-2** | ¿El cuestionario es de la obra o del capítulo? | Cambia `RN-1` y el modelo entero |
| **W-11** | ¿Cuál es el máximo de preguntas? | Sin tope, la corrección puede volverse inabordable |
| **W-12** | ¿Cómo consulta `Work` el precio a `Credits` para mostrarlo al autor? | Es una consulta síncrona entre contextos: necesita contrato explícito |
| W-13 | ¿Existe pantalla de configuración en Figma? | Sin ella, el detalle del formulario se está deduciendo |
| W-14 | ¿Hay tipos de pregunta además del texto libre (escala, sí/no, opción múltiple)? | Cambia el modelo y probablemente el precio |
| W-15 | ¿Hay cuestionarios plantilla sugeridos para autores que no saben qué preguntar? | Producto |
| R-5 | El contador `0 / 100`, ¿caracteres o palabras? | Define `minLength` y `maxLength` |
| W-16 | ¿Se puede editar el cuestionario con correcciones ya recibidas? | `RN-7` dice que sí; conviene confirmarlo |

`W-12` es la más incómoda: el autor necesita ver el precio mientras configura, y el precio lo
calcula `Credits`. Un evento no sirve, porque la respuesta se necesita en el momento. La vía
correcta es un **contrato de consulta explícito** que devuelva cifras y nada más, nunca un
acceso al modelo de `Credits`
([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md) lo permite:
prohíbe el acoplamiento, no la consulta con contrato).

## Estado

**Especificación:** `DRAFT`. El mecanismo está claro; falta la pantalla del autor (`W-13`) y
el contrato de consulta de precio (`W-12`).

**Implementación:** `TODO`.
