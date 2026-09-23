---
id: FEAT-CRD-009
title: Comprobar el saldo al empezar una corrección
context: Credits
concept: Pricing
actors: []
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - conversation:2026-09-23 (rediseño del sistema de créditos)
  - docs/decisions/0006-credit-system.md
endpoints: []
events: [CorrectionStarted, ChapterCorrectabilityChanged]
depends_on: [FEAT-CRD-016]
updated: 2026-09-23
---

# FEAT-CRD-009 — Comprobar el saldo al empezar una corrección

## Resumen

Un capítulo admite correcciones **mientras el saldo del autor cubra su precio**. Al empezar
una corrección se anota el precio, que queda fijado hasta que se entregue.

**No se retiene nada.** El saldo del autor no cambia hasta que recibe la corrección.

## Por qué no hay retención

Retener sería más preciso: casi eliminaría el descubierto. Se descarta igualmente porque
**apartarle créditos al autor por una corrección que todavía no existe** cuesta más de lo que
ahorra.

| | Con retención | **Sin retención** |
|---|---|---|
| Lo que ve el autor | Dos cifras: saldo y disponible | **Una: su saldo** |
| Estados a gestionar | `HELD` → `CONFIRMED` / `RELEASED`, con caducidad | **Ninguno** |
| Casos límite | Caducar, liberar, reconciliar huérfanas | **Ninguno** |
| ¿Espera `Feedback` a `Credits`? | Sí, antes de dejar escribir | **No** |
| Descubierto | Casi nunca | **Habrá** (ver abajo) |

La última fila es el precio de las otras cuatro, y se acepta a conciencia
([`decision:0006`](../../decisions/0006-credit-system.md)).

## Reglas de negocio

- `RN-1` Un capítulo es **corregible** mientras `saldo del autor ≥ precio del capítulo`.
- `RN-2` Al empezar una corrección se **anota su precio**. Ese importe es el que se cargará y
  el que se abonará, aunque después cambien el texto o el cuestionario.
- `RN-3` **Anotar el precio no bloquea nada.** El saldo del autor sigue íntegro y disponible
  para cualquier otra cosa.
- `RN-4` La anotación **no caduca**. El lector no tiene plazo para entregar.
- `RN-5` La comprobación es **orientativa**: dos lectores pueden empezar a la vez sobre un
  saldo que solo cubre a uno. Los dos cobrarán
  ([`FEAT-CRD-018`](FEAT-CRD-018-negative-balance.md)).
- `RN-6` Con saldo **negativo**, ningún capítulo del autor es corregible, salvo el descubierto
  deliberado ([`FEAT-CRD-019`](FEAT-CRD-019-overdraft-correction.md)).
- `RN-7` Un autor no corrige su propia obra, así que no hay caso.

`RN-4` es una consecuencia directa de `RN-3`: sin nada bloqueado, un lector lento no perjudica
a nadie. Con retención hacía falta una caducidad para que no congelase el saldo del autor;
aquí no hace falta, y con ella desaparecen los avisos de caducidad y la pérdida de trabajo.

## La comprobación no necesita ser autoritativa

Es la consecuencia arquitectónica más útil de no retener.

Si hubiera que reservar, `Feedback` tendría que **preguntar a `Credits` y esperar respuesta**
antes de dejar escribir: un viaje por la cola entre pulsar el botón y poder empezar. Era el
único punto del sistema donde la asincronía tenía coste visible.

Como no hay nada que reservar, basta con que `Feedback` mantenga una **proyección** de qué
capítulos son corregibles, alimentada por `ChapterCorrectabilityChanged`. El panel se abre al
instante.

Que la proyección vaya ligeramente retrasada no importa: el peor caso es exactamente el que
ya está aceptado —el autor queda en negativo— y no uno nuevo.

**La proyección lleva un booleano, no un importe.** `Feedback` no debe conocer saldos ajenos:
solo si este capítulo se puede corregir ahora.

## Flujo principal

```text
Lector pulsa «Empezar corrección»
        ↓
Feedback consulta su proyección: ¿corregible?
        ↓                              ↓
       sí                             no
        ↓                              ↓
Se abre el panel                Se explica por qué
Feedback publica CorrectionStarted
        ↓
Credits anota el precio de esta corrección
        ↓
   (el lector escribe, sin plazo)
        ↓
FeedbackSubmitted → cargo al autor + abono al lector
```

## Flujos alternativos y errores

| Caso | Comportamiento |
|---|---|
| Saldo insuficiente | El capítulo no aparece como corregible |
| Dos lectores a la vez, saldo para uno | **Ambos escriben y ambos cobran.** El autor queda en negativo y una corrección llega bloqueada |
| El autor gasta su saldo mientras alguien corrige | Igual que el anterior |
| El lector tarda semanas | No pasa nada: no hay plazo |
| El lector descarta el borrador | Se descarta la anotación. Nada que liberar |
| El autor cierra la obra a corrección | Quien empezó, termina y cobra |
| Evento repetido | La anotación es idempotente por `(chapterId, readerId)` |

## Eventos

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `CorrectionStarted` | `Feedback` | Anota el precio de esa corrección |
| `CorrectionDraftDiscarded` | `Feedback` | Descarta la anotación |

**Publica**

| Evento | Cuándo | Consumidores |
|---|---|---|
| `ChapterCorrectabilityChanged` | Un capítulo pasa a ser corregible o deja de serlo | `Feedback`, `Work` (insignia del catálogo) |

Se deriva de dos cosas: el saldo del autor y el precio del capítulo. **No lleva importes ni
saldos**, solo el capítulo y si se puede corregir.

Que sea un evento derivado y no una consulta es lo que mantiene a `Feedback` sin saber nada de
la economía.

## Modelo de datos afectado

`correction_price`: `chapter_id`, `reader_id`, `amount`, `quoted_at`.

Una fila por corrección en curso. **No es una retención**: no participa en el cálculo del
saldo y no hay ningún estado que reconciliar. Es una cotización.

Índice único `(chapter_id, reader_id)` para `RN-7` de idempotencia.

## Criterios de aceptación

- [ ] Un capítulo es corregible si y solo si el saldo del autor cubre su precio.
- [ ] Empezar una corrección **no modifica el saldo del autor**.
- [ ] En ningún momento existe un «saldo disponible» distinto del saldo.
- [ ] El importe cobrado al entregar es el anotado al empezar, no el vigente.
- [ ] Dos lectores simultáneos con saldo para uno: los dos escriben y los dos cobran.
- [ ] Un lector sin prisa no pierde su anotación por tiempo.
- [ ] Con saldo negativo, ningún capítulo del autor aparece como corregible.
- [ ] `Feedback` abre el panel sin esperar respuesta de `Credits`.
- [ ] La proyección de corregibilidad no contiene importes.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| C-39 | ¿Se avisa al autor de que alguien ha empezado a corregirle? | Le permitiría reponer saldo a tiempo y evitar el bloqueo |
| C-40 | ¿Se le enseña al lector que el autor va justo de saldo? | Sería transparente y podría desanimar sin motivo: él cobra igual |
| C-41 | ¿Cuántas correcciones simultáneas admite un mismo capítulo? | Un tope acotaría el descubierto por carrera sin reintroducir la retención |

`C-41` es la palanca que queda si el descubierto por carrera resulta más frecuente de lo
tolerable: limitar cuántas correcciones abiertas admite un capítulo a la vez acota el problema
**sin apartar un solo crédito**.

## Estado

**Especificación:** `DRAFT`.

**Implementación:** `TODO`.
