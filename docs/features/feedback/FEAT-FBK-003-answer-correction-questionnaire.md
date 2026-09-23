---
id: FEAT-FBK-003
title: Responder y enviar el cuestionario de corrección
context: Feedback
concept: Correction
actors: [BetaReader]
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - conversation:2026-09-22 (formulario de corrección)
  - docs/ui/read-chapter.md
  - _sources/use-cases.pdf
endpoints:
  - GET /chapters/{chapterId}/questionnaire
  - POST /chapters/{chapterId}/corrections
  - PUT /chapters/{chapterId}/correction/draft
depends_on: [FEAT-WRK-014, FEAT-RDG-001, FEAT-CRD-016]
events: [FeedbackSubmitted]
updated: 2026-09-22
---

# FEAT-FBK-003 — Responder y enviar el cuestionario de corrección

## Resumen

El lector beta responde el cuestionario que el autor ha configurado para su obra y lo envía.
**Ese envío es el hecho de negocio que mueve los créditos**: confirma el gasto del autor y
genera la recompensa del lector.

**La corrección es por capítulo** (`R-2`, resuelta): un lector puede corregir cada capítulo
de una obra por separado, y cada corrección es una operación de créditos independiente.

Es la funcionalidad central del producto. Todo lo demás —descubrir obras, pedir acceso,
leer— existe para que esto ocurra.

## Una corrección no es un comentario

La distinción es estructural y conviene fijarla antes de nada:

| | Comentario de capítulo | Corrección |
|---|---|---|
| Dónde vive | Bajo el texto, a la vista de todos | Panel «Empezar corrección» |
| Qué es | Reacción social libre | Respuesta al cuestionario del autor |
| Quién puede | Cualquiera que pueda leer | Solo un lector beta con acceso |
| Créditos | Ninguno | **Cuesta al autor y recompensa al lector** |
| Contexto propietario | `Community` (`FEAT-COM-036`) | **`Feedback`** |

Ambas conviven en la misma pantalla, lo que hace fácil confundirlas. Esto resuelve `D-1` y
`F-1`: el feedback que el sistema paga **es el cuestionario respondido**, no un comentario
suelto sobre un fragmento.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `BetaReader` | Responder y enviar una corrección | Tiene acceso concedido a la obra y la obra está `IN_CORRECTION` |
| `User` sin acceso | Leer, si la obra lo permite | **No** ve el panel de corrección (`R-4`) |
| `Writer` | Recibir y leer la corrección | Es el autor de la obra |
| `Writer` sobre su propia obra | **No** puede corregirse a sí mismo | `RN-8` |

La cuenta debe estar activada
([`FEAT-USR-025`](../user/FEAT-USR-025-block-writes-until-activation.md)): enviar una
corrección es una operación de escritura.

## Precondiciones

1. La obra está en estado `IN_CORRECTION`
   ([`FEAT-WRK-016`](../work/FEAT-WRK-016-work-status.md)).
2. El lector tiene acceso de lector beta concedido (`FEAT-RDG-001` o `FEAT-RDG-002`).
3. El capítulo era **corregible** al empezar: el saldo del autor cubría su precio
   ([`FEAT-CRD-009`](../credits/FEAT-CRD-009-balance-check-on-correction-start.md)).
4. El autor ha configurado el cuestionario ([`FEAT-WRK-014`](../work/FEAT-WRK-014-configure-questionnaire.md)).

**La precondición 3 es orientativa, no una garantía**, y es importante entender por qué no
importa: el sistema **no retiene créditos**, así que entre empezar y entregar el autor puede
quedarse sin saldo. Si ocurre, **el lector cobra igualmente** y el autor queda en negativo
([`FEAT-CRD-018`](../credits/FEAT-CRD-018-negative-balance.md)).

Nadie escribe una crítica y se queda sin cobrar. Esa es la única garantía que el lector
necesita, y no hace falta apartar créditos para dársela.

## Reglas de negocio

- `RN-1` Una corrección responde a **un cuestionario concreto de una obra concreta**. La
  versión del cuestionario vigente al empezar es la que se responde, aunque el autor lo
  cambie después (`RN-3` de [`FEAT-CRD-016`](../credits/FEAT-CRD-016-effort-based-pricing.md)).
- `RN-2` Un lector puede enviar **una sola corrección por capítulo**. Sí puede corregir
  varios capítulos de la misma obra, cada uno con su coste y su recompensa (`R-2`).
- `RN-3` Una corrección enviada es **inmutable**. No se edita ni se borra: el autor ya ha
  pagado por ella.
- `RN-4` Todas las preguntas obligatorias deben tener respuesta. El autor decide cuáles lo
  son al configurar el cuestionario.
- `RN-5` Cada respuesta tiene una **longitud mínima y máxima en palabras**, definidas por la
  pregunta (`R-5`, resuelta). El mínimo evita respuestas vacías de contenido («ok», «bien»)
  que cobrarían igual.
- `RN-6` El envío es **idempotente** por `(reader, work)`: reintentar no genera una segunda
  corrección ni un segundo abono.
- `RN-7` El envío publica `FeedbackSubmitted`. **`Feedback` no calcula ni menciona importes**
  ([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md)).
- `RN-8` El autor no puede corregir su propia obra.
- `RN-9` La corrección es **privada entre lector y autor** mientras el autor no decida otra
  cosa. No aparece en el capítulo ni es pública.

`RN-5` es la primera defensa contra el fraude por volumen, pero **no es suficiente**: una
respuesta larga puede ser igual de vacía. El control real será un sistema antifraude
específico, todavía por definir ([`FEAT-FBK-012`](FEAT-FBK-012-correction-fraud-control.md)).

Con la corrección por capítulo, `RN-2` cambia de naturaleza: ya no impide repetir trabajo
sobre la misma obra, solo sobre el mismo capítulo. Quien trocee una novela en cuarenta
capítulos multiplica por cuarenta las correcciones que puede recibir **y pagar**, lo que
convierte el troceo en una decisión económica del autor, no solo editorial. Merece un tope
(`R-10`).

## Flujo principal

1. El lector abre el panel «Empezar corrección» mientras lee.
2. El sistema devuelve el cuestionario de la obra y, si existe, el **borrador** guardado
   previamente ([`FEAT-FBK-011`](FEAT-FBK-011-save-correction-draft.md)).
3. El lector escribe sus respuestas. El contador muestra el avance frente al límite.
4. Puede pulsar **«Guardar»** las veces que quiera: conserva lo escrito sin entregarlo y **no
   mueve créditos**.
5. Pulsa **«Enviar»**.
6. El sistema valida obligatoriedad y longitudes (`RN-4`, `RN-5`).
7. La corrección pasa a `SUBMITTED` y el borrador desaparece.
8. Se publica `FeedbackSubmitted`.
9. `Credits` consume el evento, carga al autor y abona al lector.
10. `Notification` consume el evento y avisa al autor.

Los pasos 9 y 10 son **asíncronos**. La respuesta HTTP no espera a que los créditos se
muevan: confirma que la corrección se ha registrado, que es el hecho que el lector necesita
conocer.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Falta una respuesta obligatoria | Se rechaza entera | `422` con la lista de preguntas sin responder |
| Una respuesta es más corta que el mínimo | Se rechaza | `422` indicando la pregunta y el mínimo |
| El lector no tiene acceso a la obra | Se rechaza | `403` |
| La obra ya no está `IN_CORRECTION` | Se rechaza | `409` |
| Ya envió una corrección de ese capítulo | Se rechaza | `409` |
| El autor intenta corregir su obra | Se rechaza | `403` |
| Reintento del mismo envío | Devuelve la corrección ya creada | `200` |
| El cuestionario cambió mientras escribía | Se envía contra la versión que respondió | `200`, con aviso al autor |
| Cuenta sin activar | Se rechaza | `403` (`FEAT-USR-025`) |

El caso «el cuestionario cambió» merece atención: rechazar el envío castigaría al lector por
algo que hizo el autor. Se acepta contra la versión respondida y el autor ve que esa
corrección responde a un cuestionario anterior.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Obtener el cuestionario y el borrador | `GET /chapters/{chapterId}/questionnaire` | `getChapterQuestionnaire` |
| Guardar borrador | `PUT /chapters/{chapterId}/correction/draft` | `saveCorrectionDraft` |
| Enviar corrección | `POST /chapters/{chapterId}/corrections` | `submitCorrection` |

Las rutas cuelgan del **capítulo**, que es la unidad que se corrige. El cuestionario lo sigue
definiendo el autor para la obra (`FEAT-WRK-014`), pero se sirve por capítulo porque es ahí
donde se responde.

El envío admite `Idempotency-Key`
([`concurrencia e idempotencia`](../../api/conventions/concurrency-and-idempotency.md)).

La respuesta de envío **no incluye importes de créditos**: el abono es asíncrono y lo decide
otro contexto. Devolver una cifra ahí obligaría a `Feedback` a conocer las reglas de
`Credits`, que es justamente lo que `decision:0002` prohíbe.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `FeedbackSubmitted` | Al enviar la corrección | `eventId`, `correctionId`, `workId`, **`chapterId`**, `authorId`, `readerId`, `questionnaireVersion`, `answeredAt` |

El evento describe **el hecho**, no sus consecuencias. No lleva importes, no lleva el texto de
las respuestas y no le dice a nadie qué hacer.

Que no lleve el texto es deliberado: el contenido de una corrección es material privado del
autor, y un evento que circula por RabbitMQ y se reintenta no es sitio para él.

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `WorkClosedForCorrection` | `Work` | Los borradores en curso de esa obra dejan de poder enviarse (`Q-5`) |

## Efectos en créditos

El hecho publicado es: **un lector beta ha entregado una corrección de una obra**.

`Credits` decide qué significa: cargar al autor y abonar al lector el importe anotado al
empezar ([`FEAT-CRD-006`](../credits/FEAT-CRD-006-charge-author-for-received-feedback.md),
[`FEAT-CRD-016`](../credits/FEAT-CRD-016-effort-based-pricing.md)).

`Feedback` no conoce ninguna de las dos cifras y no debe conocerlas.

## Modelo de datos afectado

`Correction` (agregado de `Feedback`):

| Campo | Notas |
|---|---|
| `id` | |
| `workId` | Referencia por identificador, no por entidad de `Work` |
| `chapterId` | **La unidad que se corrige** |
| `readerId` | |
| `questionnaireVersion` | Qué versión se respondió |
| `status` | `DRAFT` / `SUBMITTED` |
| `answers` | Colección de `Answer` (`questionId`, `text`) |
| `submittedAt` | |

Índices: único **`(chapterId, readerId)`** para garantizar `RN-2` a nivel de base de datos, y
`(workId, submittedAt)` para el listado del autor, que sigue siendo por obra: el autor quiere
ver todo el feedback de su novela junto, no capítulo a capítulo.

El índice único no es una optimización: es la única garantía real de `RN-2` bajo
concurrencia.

## Diseño (Figma)

[`../../ui/read-chapter.md`](../../ui/read-chapter.md) — panel «Responde y envía el
cuestionario creado por el autor», con contador por respuesta y los botones «Enviar» y
«Guardar».

## Criterios de aceptación

- [ ] Un lector beta con acceso puede enviar una corrección de un capítulo de una obra `IN_CORRECTION`.
- [ ] El mismo lector puede corregir varios capítulos de la misma obra.
- [ ] Quien no tiene acceso recibe `403` y no ve el cuestionario.
- [ ] El autor no puede corregir su propia obra.
- [ ] Enviar dos veces la misma corrección no crea dos correcciones ni dos abonos.
- [ ] Una respuesta por debajo del mínimo **de palabras** se rechaza con `422` indicando la pregunta.
- [ ] El envío publica exactamente un `FeedbackSubmitted` con `eventId` estable.
- [ ] El evento **no contiene importes ni el texto de las respuestas**.
- [ ] Una corrección enviada no puede modificarse ni borrarse.
- [ ] Guardar un borrador no publica ningún evento ni mueve créditos.
- [ ] Existe un índice único `(chapterId, readerId)`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **R-4** | ¿Hace falta acceso de lector beta previo, o «Empezar corrección» lo concede? | Decide si la reserva ocurre antes o al abrir el panel |
| **C-41** | ¿Cuántas correcciones simultáneas admite un capítulo? | Acota el descubierto por carrera **sin apartar créditos** |
| **R-10** | ¿Hay tope de correcciones por obra o por capítulo? | Trocear una obra en cuarenta capítulos multiplica el coste por cuarenta |
| Q-5 | Si el autor cierra la corrección, ¿qué pasa con los borradores en curso? | Trabajo del lector perdido |
| Q-6 | ¿Ve el autor quién le corrigió, o es anónimo? | Cambia el payload y el modelo |
| Q-7 | ¿Puede el lector ver después sus propias correcciones enviadas? | `FEAT-FBK-010` |
| Q-9 | ¿Se notifica al lector cuando el autor lee o valora su corrección? | Cierra el bucle de reputación |

Resueltas: `R-2` (**por capítulo**), `R-5` (**palabras**), `Q-3`/`Q-4` (habrá control
antifraude, [`FEAT-FBK-012`](FEAT-FBK-012-correction-fraud-control.md)) y **`R-1`**, que
desaparece al eliminarse la retención.

**`R-1` ha dejado de existir.** Preguntaba si la retención era por obra o por capítulo, y
[`decision:0006`](../../decisions/0006-credit-system.md) elimina la retención: se comprueba el
saldo al empezar y se cobra al entregar.

Lo que queda en su lugar es `C-41`, más acotada: **cuántas correcciones simultáneas admite un
capítulo**. Es la palanca para limitar el descubierto por carrera sin reintroducir nada de lo
que se ha quitado.

## Estado

**Especificación:** `DRAFT`. El modelo de datos ya está determinado (`R-2` resuelta). Falta
`R-1` y `R-4` para llegar a `APPROVED`.

**Implementación:** `TODO`.
