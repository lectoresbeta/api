---
id: FEAT-CRD-009
title: Retener créditos al empezar una corrección
context: Credits
concept: Reservation
actors: []
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - conversation:2026-09-23 (rediseño del sistema de créditos)
  - docs/decisions/0006-credit-system.md
endpoints: []
events: [CorrectionStarted, CreditsHeld, CreditHoldRejected, CreditHoldReleased]
depends_on: [FEAT-CRD-016]
updated: 2026-09-23
---

# FEAT-CRD-009 — Retener créditos al empezar una corrección

## Resumen

Cuando un lector pulsa **«Empezar corrección»**, `Credits` retiene del autor el precio de ese
capítulo. El saldo del autor no baja: **una parte deja de estar disponible**.

| | Ejemplo |
|---|---|
| Saldo total | 20 |
| Retenido | 6 |
| **Disponible** | **14** |

Es el mismo mecanismo que la preautorización de una tarjeta al reservar un hotel: el importe
sigue siendo tuyo, pero no puedes gastarlo dos veces.

## Por qué en ese momento y no en otro

La retención existe para que **nadie escriba una crítica y descubra después que no hay quien
la pague**. Ese riesgo empieza exactamente cuando el lector se pone a trabajar, así que ese es
el momento de retener.

| Momento | Qué se retiene | Problema |
|---|---|---|
| Al abrir la obra a corrección | Un número de plazas que el autor declara | Obliga al autor a entender y gestionar un concepto nuevo |
| Al conceder acceso de lector beta | El coste por cada lector con acceso | Retiene por gente que quizá nunca corrija, y obliga a compensar entre `Reading` y `Credits` |
| **Al empezar la corrección** | **El precio del capítulo que se está corrigiendo** | — |

La tercera acota por sí sola: solo hay tantas retenciones como lectores escribiendo ahora
mismo. Y **elimina la compensación entre contextos**: la retención vive entera en `Credits` y
se dispara con un hecho de `Feedback`, sin que `Reading` intervenga.

Esto resuelve `R-1`, abierta desde el principio, y sustituye a la parte correspondiente de
[`decision:0004`](../../decisions/0004-credit-reservation-on-access-grant.md).

## Reglas de negocio

- `RN-1` La retención es **por lector y por capítulo**, al empezar.
- `RN-2` Solo se retiene si el autor tiene **disponible** el precio completo. Si no, el
  capítulo no admite esa corrección en ese momento.
- `RN-3` El importe retenido **queda fijado**: es lo que se cargará y lo que se abonará,
  aunque el cuestionario o el texto cambien después.
- `RN-4` La retención **caduca a los 7 días** (`C-16`). Al caducar se libera y el capítulo
  vuelve a estar disponible para otros.
- `RN-5` Al entregar la corrección, la retención se **convierte en cargo** y se abona al
  lector la misma cifra.
- `RN-6` Si el lector **descarta** su borrador, la retención se libera de inmediato.
- `RN-7` Un autor **no retiene contra sí mismo**: no puede corregir su propia obra, así que no
  hay caso.
- `RN-8` La retención es **idempotente** por `(chapterId, readerId)`: empezar dos veces no
  retiene dos veces.
- `RN-9` Una retención **nunca se pierde en silencio**. Toda retención acaba confirmada,
  liberada o caducada, y las tres dejan movimiento.

`RN-4` es la que impide que un lector bloquee indefinidamente el saldo de un autor abriendo el
panel y desapareciendo. Sin caducidad, un solo lector podría congelar una obra entera.

## Flujo principal

```text
Lector pulsa «Empezar corrección»
        ↓
Feedback publica CorrectionStarted
        ↓
Credits comprueba el disponible del autor
        ↓
   ┌────┴────┐
 alcanza   no alcanza
   ↓            ↓
CreditsHeld  CreditHoldRejected
   ↓            ↓
El panel se   El panel no se abre;
abre; 7 días  se explica por qué
   ↓
FeedbackSubmitted → cargo al autor + abono al lector
```

## Flujos alternativos y errores

| Caso | Comportamiento |
|---|---|
| Sin disponible suficiente | `CreditHoldRejected`. `Feedback` no abre el panel |
| Dos lectores a la vez, saldo para uno | Gana el primero; el segundo recibe rechazo |
| El lector no entrega en 7 días | Se libera. El borrador se conserva, pero para entregar hay que volver a retener |
| El lector descarta el borrador | Se libera |
| El autor cierra la obra a corrección | Las retenciones vivas **se respetan**: quien empezó, termina |
| Autor seleccionado para reactivación | Se retiene en descubierto ([`FEAT-CRD-019`](FEAT-CRD-019-overdraft-correction.md)) |
| Evento repetido | No retiene dos veces (`RN-8`) |

El caso «no entrega en 7 días» merece cuidado: el lector puede haber escrito una crítica
larga. Conviene avisarle antes de que caduque, y que al volver pueda recuperar la retención
si el autor sigue teniendo saldo (`C-17`).

## Eventos

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `CorrectionStarted` | `Feedback` | Intenta retener |
| `FeedbackSubmitted` | `Feedback` | Confirma: cargo y abono |
| `CorrectionDraftDiscarded` | `Feedback` | Libera |

**Publica**

| Evento | Cuándo | Consumidores |
|---|---|---|
| `CreditsHeld` | Se retiene | `Feedback` (abre el panel), `Notification` |
| `CreditHoldRejected` | No hay disponible | **`Feedback`** (no abre el panel), `Notification` |
| `CreditHoldReleased` | Caduca o se descarta | `Feedback`, `Notification` |

`CreditHoldRejected` es el único de los tres que **bloquea** algo en otro contexto, y por eso
`Feedback` debe esperarlo antes de dejar escribir. Es el punto donde la asincronía tiene
coste: entre pulsar el botón y poder escribir hay un viaje por la cola.

Si esa latencia resultara inaceptable en la práctica, la alternativa es una **consulta
síncrona con contrato explícito** a `Credits`, que `decision:0002` permite: prohíbe el
acoplamiento al modelo, no la consulta. Ver `C-18`.

## Modelo de datos afectado

`credit_hold`: `id`, `user_id`, `chapter_id`, `reader_id`, `amount`, `status`
(`HELD` / `CONFIRMED` / `RELEASED`), `held_at`, `expires_at`, `resolved_at`.

Índice único parcial por `(chapter_id, reader_id)` sobre las retenciones en `HELD`, que es lo
que garantiza `RN-8` bajo concurrencia.

El **saldo disponible** es el saldo menos la suma de retenciones en `HELD`. Conviene
materializarlo en la cuenta y no calcularlo en cada lectura: se consulta en cada pantalla.

## Criterios de aceptación

- [ ] Empezar una corrección retiene el precio exacto del capítulo.
- [ ] El saldo total no cambia al retener; el disponible sí.
- [ ] Sin disponible suficiente, el panel de corrección no se abre.
- [ ] Dos lectores simultáneos con saldo para uno: solo uno obtiene la retención.
- [ ] Una retención caducada libera el importe y deja movimiento.
- [ ] Entregar convierte la retención en cargo por el importe **retenido**, no por el vigente.
- [ ] Cerrar la obra a corrección no cancela retenciones vivas.
- [ ] Repetir el evento de inicio no duplica la retención.
- [ ] No existen retenciones huérfanas: toda retención acaba en un estado final.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| C-16 | ¿Siete días es el plazo adecuado? | Demasiado corto castiga al lector cuidadoso; demasiado largo congela el saldo del autor |
| C-17 | Al caducar, ¿puede el lector recuperar la retención y entregar igualmente? | Sin ello se pierde trabajo real |
| C-18 | ¿La comprobación de saldo es asíncrona o una consulta síncrona con contrato? | Entre pulsar y escribir hay un viaje por la cola |
| C-19 | ¿Se avisa al lector antes de que caduque su retención? | Es la diferencia entre perder una tarde o no |

## Estado

**Especificación:** `DRAFT`. El mecanismo está decidido; `C-18` afecta a la experiencia y
conviene cerrarla antes de implementar.

**Implementación:** `TODO`.
