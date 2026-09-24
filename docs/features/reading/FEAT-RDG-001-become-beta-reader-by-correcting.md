---
id: FEAT-RDG-001
title: Convertirse en lector beta al empezar a corregir (obra `PUBLIC`)
context: Reading
concept: BetaReaderAccess
actors: [Reader]
spec_status: APPROVED
impl_status: TODO
priority: P0
sources:
  - conversation:2026-09-22 (modalidades de acceso)
  - conversation:2026-09-24 (R-4 y las tres preguntas que quedaban)
  - docs/features/feedback/FEAT-FBK-003-answer-correction-questionnaire.md
endpoints: []
events: [CorrectionStarted, CorrectionDraftDiscarded, BetaReaderAccessGranted, BetaReaderAccessRevoked]
depends_on: [FEAT-FBK-003]
updated: 2026-09-24
---

# FEAT-RDG-001 — Convertirse en lector beta al empezar a corregir

## Resumen

En una obra `PUBLIC`, **empezar una corrección convierte a quien la empieza en lector beta de
esa obra**. No hay solicitud, no hay espera y no hay pantalla: el acceso es la consecuencia
de ponerse a trabajar.

Es la funcionalidad más pequeña del contexto y la única de sus tres caminos de acceso que no
requiere que el autor haga nada.

## Esto no es una pantalla

Conviene decirlo antes que nada, porque el nombre de la funcionalidad sugiere lo contrario.
**No existe ningún endpoint.** Al resolverse `R-4` en
[`FEAT-FBK-003`](../feedback/FEAT-FBK-003-answer-correction-questionnaire.md), esta
funcionalidad dejó de ser una acción del usuario y pasó a ser **el efecto en `Reading` de un
hecho que ocurre en `Feedback`**.

Quien quiera verla funcionar no encontrará una llamada nueva: encontrará que, después de
empezar una corrección, existe un `BetaReaderAccess` que antes no estaba.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Reader` | Convertirse en lector beta de una obra | Empieza una corrección de uno de sus capítulos |
| `Writer` | — | **No puede ser lector beta de su propia obra** (`RN-1` del contexto) |

La autorización de fondo —quién puede empezar una corrección— **no se decide aquí**. La
decide `Feedback` antes de publicar el hecho
([`FEAT-FBK-003`](../feedback/FEAT-FBK-003-answer-correction-questionnaire.md)), que es quien
conoce la modalidad de la obra, su estado y la edad de quien llama.

## Reglas de negocio

- `RN-1` Un `CorrectionStarted` sobre una obra de la que el lector **no tiene acceso vivo**
  le concede uno, con origen `PUBLIC_JOIN`.
- `RN-2` Si ya lo tiene, no ocurre nada. Un acceso vivo por par (lector, obra) sigue siendo la
  invariante, y corregir cinco capítulos de la misma obra no produce cinco accesos.
- `RN-3` Conceder este acceso **no cuesta ni compromete créditos**
  ([`decision:0006`](../../decisions/0006-credit-system.md)).
- `RN-4` **El acceso se concede al empezar, no al entregar.** Quien está corrigiendo conserva
  la obra bajo los pies aunque el autor la restrinja mientras tanto.
- `RN-5` **Descartar el borrador revoca el acceso** si nació de esta vía y el lector nunca
  entregó ninguna corrección de esa obra. Abandonar en silencio no lo revoca.
- `RN-6` El acceso concedido **sobrevive** a que el autor cambie la modalidad de la obra
  (`RN-5` del contexto) y a que cierre la corrección.
- `RN-7` Este contexto **no comprueba la modalidad de la obra**. Ver abajo.

## `RN-4` — por qué al empezar y no al entregar

Es la decisión de fondo de la ficha, y las dos opciones eran defendibles.

| | Conceder al empezar | Conceder al entregar |
|---|---|---|
| Quien abandona | Se queda con el acceso hasta que descarte | No deja rastro |
| Quien está a medias y el autor restringe la obra | **Sigue pudiendo leer y entregar** | Pierde el texto y no puede entregar: su trabajo se evapora |
| Estados | Uno | Uno |

Decide la segunda fila. Este producto se sostiene sobre una promesa —**nadie escribe una
corrección y se queda sin cobrar**— y perder el acceso a mitad de la escritura la rompería de
la peor manera posible: en silencio y con el trabajo ya hecho.

El coste es el de la primera fila, y se acota con `RN-5`.

## `RN-5` — descartar sí revoca

Quien pulsa «descartar» está diciendo que no va a hacerlo. Dejarle un acceso permanente a una
obra inédita por haber abierto un panel una vez sería regalar lectura a cambio de nada, y este
producto custodia obra sin publicar.

Dos condiciones, y las dos importan:

- **solo si el acceso nació de aquí** (`PUBLIC_JOIN`): una solicitud aceptada o una invitación
  del autor no se deshacen porque alguien descarte un borrador;
- **solo si nunca entregó nada de esa obra**: quien ya corrigió un capítulo se ganó el acceso,
  y descartar el borrador de otro no se lo quita.

Queda un hueco conocido y aceptado: **abandonar sin descartar no revoca nada**. Cerrar la
pestaña no es un hecho, nadie lo publica, y la alternativa —caducar accesos por inactividad—
introduce un reloj y un estado nuevo para un caso que el autor puede resolver él mismo cuando
exista [`FEAT-RDG-010`](../README.md).

## `RN-7` — por qué no se comprueba la modalidad

Parecería que `Reading` debería confirmar que la obra era `PUBLIC` antes de conceder. No hace
falta, y evitarlo ahorra una proyección entera:

- si la obra era `PUBLIC`, corresponde conceder;
- si era `ON_REQUEST` o `PRIVATE`, el lector **ya tenía acceso** —`Feedback` no le habría
  dejado empezar— y `RN-2` hace que no ocurra nada.

En los dos casos el resultado es correcto sin preguntarle a `Work` nada. La alternativa era
mantener aquí una copia de la modalidad de cada obra alimentada por `WorkAccessModeChanged`,
con su retraso y su reconstrucción, para un dato que no cambia la respuesta.

## Flujo

```text
Feedback                         Reading
────────                         ───────
«Empezar corrección»
   │ comprueba modalidad,
   │ estado, edad y autoría
   ▼
CorrectionStarted ──────────────▶ ¿acceso vivo?
                                      │ no
                                      ▼
                                  BetaReaderAccess(PUBLIC_JOIN)
                                      │
                                      ▼
                                  BetaReaderAccessGranted ──▶ Notification
```

Es asíncrono, y el retraso no molesta a nadie: en una obra `PUBLIC` leer no exige acceso
(`FEAT-WRK-004`), así que durante esa ventana el lector no nota ninguna diferencia. Lo que el
acceso añade se nota más tarde, cuando el autor restringe la obra.

## Flujos alternativos y errores

| Caso | Comportamiento |
|---|---|
| El lector ya tiene acceso vivo | No se hace nada (`RN-2`) |
| El hecho se reentrega | Idempotente: el segundo intento encuentra el acceso y para |
| El acceso fue revocado antes | Se concede uno nuevo: la invariante es «uno **vivo**», no «uno en la historia» |
| Descarta el borrador y nunca entregó nada | Se revoca (`RN-5`) |
| Descarta el borrador y ya había entregado otra corrección de la obra | **No** se revoca |
| El lector es el autor | No puede ocurrir: `Feedback` lo para antes, y el agregado lo rechazaría igualmente |

## Eventos

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `CorrectionStarted` | `Feedback` | Concede el acceso si no había uno vivo |
| `CorrectionDraftDiscarded` | `Feedback` | Revoca el acceso nacido de esta vía si el lector nunca entregó nada de esa obra |
| `FeedbackSubmitted` | `Feedback` | Marca el acceso como **ganado**: a partir de aquí no se revoca por descartar |

**Publica**

| Evento | Cuándo | Consumidores |
|---|---|---|
| `BetaReaderAccessGranted` | Se concede | `Notification` |
| `BetaReaderAccessRevoked` | Se revoca por descarte | `Notification` |

Ninguno lleva importes: conceder acceso dejó de tener efecto económico con
[`decision:0006`](../../decisions/0006-credit-system.md).

## Modelo de datos afectado

El agregado `BetaReaderAccess` que ya existe, con **un campo nuevo**: si el acceso se ha
*ganado* entregando una corrección. Es lo que distingue `RN-5` de una revocación indebida, y
un booleano es todo lo que hace falta — no interesa cuántas ni cuáles.

No hay tabla nueva ni endpoint nuevo.

## Criterios de aceptación

- [ ] Empezar una corrección en una obra `PUBLIC` deja al lector como lector beta de la obra.
- [ ] Corregir varios capítulos de la misma obra no crea varios accesos.
- [ ] Reentregar el hecho no crea un segundo acceso.
- [ ] El acceso sobrevive a que el autor pase la obra a `PRIVATE`.
- [ ] Descartar el borrador sin haber entregado nada revoca el acceso.
- [ ] Descartar el borrador **después** de haber entregado una corrección de esa obra no lo revoca.
- [ ] Un acceso concedido por solicitud o invitación no se revoca al descartar un borrador.
- [ ] El autor nunca acaba siendo lector beta de su propia obra.
- [ ] `Reading` no consulta a `Work` ni a `Credits` en todo el proceso.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~A-1~~ | ¿Hace falta un endpoint para «hacerse lector beta»? | **No.** El acceso es consecuencia de empezar a corregir |
| ~~A-2~~ | ¿Al empezar o al entregar? | **Al empezar** (`RN-4`) |
| ~~A-3~~ | ¿Abandonar revoca? | **Solo al descartar explícitamente** (`RN-5`) |
| A-5 | ¿Debería el autor ver cómo llegó cada lector beta a su obra? | `grantedVia` ya lo guarda; falta decidir si se enseña |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las tres preguntas que la bloqueaban —si hace
falta pantalla, en qué momento se concede y si abandonar revoca— se responden arriba, en
`RN-4`, `RN-5` y la sección «Esto no es una pantalla».

**Implementación:** `TODO`.
