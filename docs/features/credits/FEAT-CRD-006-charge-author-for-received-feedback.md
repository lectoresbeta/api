---
id: FEAT-CRD-006
title: Confirmar el cargo al autor cuando recibe un comentario
context: Credits
concept: Account
actors: []
spec_status: APPROVED
impl_status: TODO
priority: P0
sources:
  - _sources/credit-system.pdf#p1
  - _sources/credit-system.pdf#p2
  - _sources/credit-system.pdf#p4
  - decision:0004
endpoints: []
events: [FeedbackSubmitted, CreditsSpent, CreditsAdded, CreditBalanceChanged, CreditBalanceWentNegative]
depends_on: [FEAT-CRD-009, FEAT-FBK-001, FEAT-WRK-013, FEAT-CRD-011]
updated: 2026-09-24
---

# FEAT-CRD-006 — Confirmar el cargo al autor cuando recibe un comentario

## Resumen

Cuando un lector beta envía su feedback, el autor de la obra **paga créditos**. Es el
mecanismo que hace que recibir crítica exija haberla dado antes, y por tanto el núcleo
económico del producto.

**Es el único momento en que los créditos se mueven.** El precio quedó anotado al empezar la
corrección (`FEAT-CRD-009`), pero nada cambió de saldo hasta ahora: no hay retención que
confirmar, hay un cargo que ejecutar.

Y es **una transferencia**: el mismo importe que se le carga al autor se le abona al lector.

## Precondiciones

- Se ha recibido `FeedbackSubmitted` con su `eventId` sin procesar.
- Existe un precio anotado para esa corrección.

## Reglas de negocio

- `RN-1` El importe es **el anotado al empezar** la corrección. No se recalcula al entregar.
- `RN-2` Se registran **dos movimientos**: cargo al autor con motivo `CORRECTION_CHARGED` y
  abono al lector con motivo `CORRECTION_EARNED`, ambos con el `eventId` de origen.
- `RN-3` **El abono al lector se ejecuta siempre**, aunque el autor no tenga saldo. Si no
  llega, su saldo queda negativo y la corrección se entrega **bloqueada**
  ([`FEAT-CRD-018`](FEAT-CRD-018-negative-balance.md)).
- `RN-4` El mismo `eventId` nunca produce dos cargos (`FEAT-CRD-011`).
- `RN-5` Los dos movimientos ocurren **juntos o no ocurren**: es una sola operación.
- `RN-6` El movimiento es inmutable. Una corrección es un movimiento nuevo.
- `RN-7` Si llega un `FeedbackSubmitted` **sin precio anotado**, no se inventa el importe: se
  registra como incidencia y se recalcula a partir del capítulo y el cuestionario vigentes,
  dejando traza de que fue una reconstrucción. Ver `C-15`.

`RN-3` es la regla que sostiene el producto entero: **un lector nunca trabaja sin cobrar.** Lo
que antes garantizaba la reserva lo garantiza ahora el saldo negativo, sin apartar un solo
crédito.

`RN-5` importa porque son dos apuntes que deben cuadrar: si se abona al lector y falla el
cargo al autor, se ha creado crédito de la nada y la invariante contable se rompe.

## Cómo se calcula el importe

El cálculo ocurre en `FEAT-CRD-009`, al empezar la corrección. Se documenta aquí porque es la
regla de negocio central del sistema.

```text
precio = techo(palabras del capítulo / 1.000) + techo(palabras exigidas / 100)
```

- El primer término es **leer**; el segundo, **escribir**. Entre **2** y **20**
  ([`FEAT-CRD-016`](FEAT-CRD-016-effort-based-pricing.md)).
- Las **palabras exigidas** son la suma de los mínimos que el autor fija en las preguntas de su
  cuestionario, con un suelo de **25 palabras** por pregunta sin mínimo declarado.
- El importe es **el anotado** al empezar la corrección
  ([`FEAT-CRD-009`](FEAT-CRD-009-balance-check-on-correction-start.md)), no el vigente al
  entregar. Cambiar el texto o el cuestionario entre medias no altera lo que cobra quien ya
  estaba corrigiendo.

### Ejemplos

| Capítulo | Palabras | Palabras exigidas | Leer | Escribir | Importe |
|---|---|---|---|---|---|
| Micro cuento | 380 | 50 | 1 | 1 | **2** |
| Relato corto | 2.400 | 300 | 3 | 3 | **6** |
| Relato corto | 2.400 | 600 | 3 | 6 | **9** |
| Capítulo de novela | 4.000 | 750 | 4 | 8 | **12** |

Las dos filas centrales muestran lo que el modelo antiguo no sabía expresar: **el mismo texto
cuesta distinto según lo que el autor pida**.

Y ya no hay saltos entre tramos: 2.999 y 3.004 palabras cuestan prácticamente lo mismo, que es
lo que cabe esperar de cinco palabras de diferencia.

## Flujo principal

1. `Feedback` publica `FeedbackSubmitted`.
2. `Credits` comprueba si el `eventId` ya se procesó; si sí, descarta.
3. Localiza el **precio anotado** de esa corrección (`CorrectionPrice`).
4. Registra los **dos** movimientos y la fila de deduplicación en la misma transacción.
5. Publica `CreditsSpent` y `CreditBalanceChanged`.

## Flujos alternativos

| Caso | Comportamiento |
|---|---|
| `eventId` repetido | Se descarta sin efecto ([`FEAT-CRD-011`](FEAT-CRD-011-deduplicate-integration-events.md)) |
| El autor no tiene saldo | **Se cobra igualmente.** El lector cobra y el autor queda en negativo (`RN-3`) |
| No existe precio anotado | No se inventa el importe: se recalcula dejando traza de la reconstrucción (`RN-7`) |

## Eventos

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `FeedbackSubmitted` | `Feedback` | Carga al autor y abona al lector el **precio anotado** |

**Publica**

| Evento | Cuándo | Consumidores |
|---|---|---|
| `CreditsSpent` | Cargo ejecutado al autor | `Notification` |
| `CreditsAdded` | Abono ejecutado al lector | `Notification` |
| `CreditBalanceChanged` | Cambia el saldo | `Reading` (proyección), read models, `Notification` |
| `CreditBalanceWentNegative` | El cargo deja al autor por debajo de cero | `Notification`, `Feedback` ([`FEAT-CRD-018`](FEAT-CRD-018-negative-balance.md)) |

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `correction_price` | Se consume la cotización de esa corrección. **No es una retención**: no hay estado que confirmar (`decision:0006`) |
| `credit_transaction` | **Dos** movimientos: `CORRECTION_CHARGED` al autor y `CORRECTION_EARNED` al lector |
| `credit_account` | Actualización de los **dos** saldos |
| `processed_event` | Registro del `eventId` para este consumidor ([`FEAT-CRD-011`](FEAT-CRD-011-deduplicate-integration-events.md)) |

Todos los cambios ocurren en **una única transacción**. Si no, una entrega duplicada puede
cobrar dos veces, o el lector puede cobrar sin que se cargue al autor: crédito creado de la
nada.

## Criterios de aceptación

- [ ] Recibir `FeedbackSubmitted` genera **dos** movimientos por el mismo importe.
- [ ] El importe coincide exactamente con el precio anotado al empezar la corrección.
- [ ] La suma de los dos movimientos es **cero**: la operación no crea ni destruye créditos.
- [ ] Procesar dos veces el mismo `eventId` produce un único par de movimientos.
- [ ] El lector cobra aunque el autor no tenga saldo, y el autor queda en negativo.
- [ ] Movimientos y registro del `eventId` son atómicos.
- [ ] El saldo resultante coincide con la suma de todos los movimientos.
- [ ] `Credits` no llama a `Work` ni a `Feedback` en todo el proceso.
- [ ] Un precio anotado de 15 créditos sigue cobrando 15 aunque la obra haya crecido
      entretanto.

El último criterio es el que da sentido a anotar el precio: se fija cuando se adquiere el
compromiso, no cuando se cumple.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~C-1~~ | ¿Qué ocurre si el autor no tiene saldo? | **Resuelto** por [`decision:0006`](../../decisions/0006-credit-system.md): el lector cobra igualmente y el autor queda en negativo ([`FEAT-CRD-018`](FEAT-CRD-018-negative-balance.md)) |
| ~~C-2~~, ~~M-1~~, ~~R-1~~ | Todo lo relativo a reservar o retener créditos | **Desaparecen** con `decision:0006`: no se retiene nada |
| ~~C-6~~, ~~C-10~~ | Tramos por extensión y nivel del texto | **Desaparecen** con el `TextTier`. El precio es una fórmula continua sobre las palabras **del capítulo** ([`FEAT-CRD-016`](FEAT-CRD-016-effort-based-pricing.md)) |
| C-4 | ¿Un mismo lector puede corregir varias veces el mismo capítulo y cobrar cada vez? | **Una corrección por lector y capítulo**; queda confirmar qué ocurre con una corrección rehecha |
| C-9 | ¿Se devuelven créditos si el autor oculta la corrección por abusiva? | Protección frente a feedback malicioso. Es una reversión, con sus motivos `CLAIM_REVERSAL_*` |
| **C-15** | ¿Qué se hace con un `FeedbackSubmitted` sin precio anotado? | `RN-7` propone recalcular dejando traza. Confirmar |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `TODO`. Depende de `FEAT-CRD-009`.
