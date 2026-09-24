---
id: FEAT-RDG-001
title: Convertirse en lector beta al empezar a corregir (obra `PUBLIC`)
context: Reading
concept: BetaReaderAccess
actors: [Reader]
spec_status: APPROVED
impl_status: DONE
priority: P0
sources:
  - conversation:2026-09-22 (modalidades de acceso)
  - conversation:2026-09-24 (R-4 y las tres preguntas que quedaban)
  - docs/features/feedback/FEAT-FBK-003-answer-correction-questionnaire.md
endpoints: []
events: [CorrectionStarted, CorrectionResumed, CorrectionDraftDiscarded, BetaReaderAccessGranted, BetaReaderAccessRevoked]
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
  le concede uno, con origen `PUBLIC_JOIN`. Un `CorrectionResumed` hace lo mismo: los dos
  significan «esta persona está corrigiendo esto ahora», que es lo único que este contexto
  necesita saber.
- `RN-2` Si ya lo tiene, no ocurre nada. Un acceso vivo por par (lector, obra) sigue siendo la
  invariante, y corregir cinco capítulos de la misma obra no produce cinco accesos.
- `RN-2b` **Un hecho abre un acceso como mucho, para siempre** (`R-22`). El acceso guarda cuál
  fue, así que una reentrega lo encuentra aunque el acceso ya esté retirado. Sin esto `RN-2`
  no basta: revocado el acceso no hay nada vivo que encontrar, y la reentrega lo concedería
  otra vez — una persona expulsada volvería a entrar porque la cola repitió un mensaje.
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
pestaña no es un hecho y nadie lo publica. La alternativa —caducar accesos por inactividad,
con su reloj y su estado nuevo— sigue descartada, y ya no hace falta: el autor lo resuelve él
con [`FEAT-RDG-010`](FEAT-RDG-010-revoke-beta-reader-access.md), que existe desde el
2026-09-24.

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
  (o CorrectionResumed)               │ no
                                      ▼
                                  ¿este hecho ya abrió uno?
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
| `CorrectionResumed` | `Feedback` | Lo mismo. Es lo que devuelve el acceso a quien perdió el suyo en una obra `PUBLIC` conservando el borrador (`R-22`) |
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

- [x] Empezar una corrección en una obra `PUBLIC` deja al lector como lector beta de la obra.
- [x] Corregir varios capítulos de la misma obra no crea varios accesos.
- [x] Reentregar el hecho no crea un segundo acceso.
- [x] El acceso sobrevive a que el autor pase la obra a `PRIVATE`.
- [x] Descartar el borrador sin haber entregado nada revoca el acceso.
- [x] Descartar el borrador **después** de haber entregado una corrección de esa obra no lo revoca.
- [x] Un acceso concedido por solicitud o invitación no se revoca al descartar un borrador. *Lo
      garantiza el propio modelo: solo se deshace lo que vino por `PUBLIC_JOIN`. No hay prueba
      funcional porque no existe todavía ninguna otra vía de conceder acceso.*
- [x] El autor nunca acaba siendo lector beta de su propia obra.
- [x] `Reading` no consulta a `Work` ni a `Credits` en todo el proceso.

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

**Implementación:** `DONE` (2026-09-24). Tres consumidores, ninguna ruta nueva, ninguna tabla
nueva: un booleano en `beta_reader_access` —si el acceso se **ganó** entregando— y nada más.

### Lo que hizo falta arreglar por el camino

Esta ficha es la primera en que **un mismo hecho tiene dos dueños**: `CorrectionStarted` lo
escuchan `Credits`, para anotar el precio, y `Reading`, para conceder el acceso, cada uno con
su clase.

`Messenger` decodifica un mensaje del transporte en **un** objeto, y el serializador se
quedaba con la primera clase registrada. Hasta hoy no se notaba porque ningún hecho tenía dos
consumidores; el síntoma habría sido el peor posible: **el segundo contexto no se entera de
nada, en silencio**.

Ahora la decodificación produce todas las reconstrucciones y un repartidor las despacha una a
una. En un despliegue con un proceso por contexto esto no haría falta —cada worker tendría su
cola y su única clase—, y conviene saber que la pieza existe por ejecutarlos juntos.

La consecuencia a tener presente: si el consumidor de un contexto falla, **el hecho entero se
reintenta** y los demás lo ven otra vez. No rompe nada porque la idempotencia ya era requisito
de todos ellos, pero es la razón de que lo sea.

### Y una consecuencia que sí se ve

`FEAT-WRK-004` gana su quinta puerta: **un lector beta lee la obra sea cual sea la modalidad**.
Hasta ahora `WorkReadPolicy` terminaba en «¿es `PUBLIC`?», así que conceder acceso a alguien le
dejaba corregir una obra que no podía leer. Ya no: el acceso llega a esa regla como un
booleano, por el contrato publicado de este contexto.
