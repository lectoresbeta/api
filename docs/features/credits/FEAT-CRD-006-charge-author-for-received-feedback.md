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
events: [FeedbackSubmitted, CreditsSpent]
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
- `RN-2` Se registran **dos movimientos**: cargo al autor con motivo `FEEDBACK_RECEIVED` y
  abono al lector con motivo `FEEDBACK_GIVEN`, ambos con el `eventId` de origen.
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

El cálculo ocurre en `FEAT-CRD-009`, al retener. Se documenta aquí porque es la regla de
negocio central del sistema.

```text
coste = créditos(textTier) + max(0, questionCount − 3)
```

- Los créditos por nivel de texto son los de la tabla de
  [`credits.md`](../../bounded-contexts/credits.md#clasificación-por-extensión-texttier).
- Las tres primeras preguntas del cuestionario no tienen coste; cada pregunta adicional suma
  1 crédito.
- El importe es **el que se retuvo** al empezar la corrección
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
3. Localiza la retención `HELD` del acceso correspondiente.
4. La marca `CONFIRMED` y registra el `CreditTransaction` en la misma transacción.
5. Publica `CreditsSpent` y `CreditBalanceChanged`.

## Flujos alternativos

| Caso | Comportamiento |
|---|---|
| `eventId` repetido | Se descarta sin efecto |
| Retención ya confirmada | Idempotente: no se cobra dos veces |
| Retención caducada o liberada | No se cobra. Se publica `InsufficientCredits` y se registra la incidencia (`RN-7`) |
| No existe retención | Igual que el caso anterior. Indica un fallo de integración que hay que investigar |

## Eventos

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `FeedbackSubmitted` | `Feedback` | Confirma la retención asociada |

**Publica**

| Evento | Cuándo | Consumidores |
|---|---|---|
| `CreditsSpent` | Cargo confirmado | `Notification` |
| `CreditBalanceChanged` | Cambia el saldo | `Reading` (proyección), read models, `Notification` |
| `InsufficientCredits` | Llega feedback sin retención válida | `Feedback`, `Notification` |

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `correction_price` | Se consume la cotización de esa corrección. **No es una retención**: no hay estado que confirmar (`decision:0006`) |
| `credit_transaction` | Nuevo movimiento negativo con `reason = FEEDBACK_RECEIVED` |
| `credit_account` | Actualización del saldo |
| `processed_event` | Registro del `eventId` |

Los cuatro cambios ocurren en **una única transacción**. Si no, una entrega duplicada puede
cobrar dos veces.

## Criterios de aceptación

- [ ] Recibir `FeedbackSubmitted` confirma la retención y genera el movimiento.
- [ ] El importe del movimiento coincide exactamente con el de la retención.
- [ ] El saldo total baja; el saldo disponible no cambia.
- [ ] Procesar dos veces el mismo `eventId` produce un único movimiento.
- [ ] Una retención ya confirmada no se cobra por segunda vez.
- [ ] Un `FeedbackSubmitted` sin retención no genera cargo ni deja el saldo negativo.
- [ ] Confirmación y registro del `eventId` son atómicos.
- [ ] El saldo resultante coincide con la suma de todos los movimientos.
- [ ] `Credits` no llama a `Work` ni a `Feedback` en todo el proceso.
- [ ] Una retención de 15 créditos sigue cobrando 15 aunque la obra haya crecido entretanto.

El último criterio es el que da sentido a la reserva: el precio se fija cuando se adquiere el
compromiso, no cuando se cumple.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~C-1~~ | ¿Qué ocurre si el autor no tiene saldo? | **Resuelto** por [`decision:0004`](../../decisions/0004-credit-reservation-on-access-grant.md): sin saldo no hay acceso, así que no llega a haber comentario |
| ~~C-2~~ | ¿Se reservan créditos al conceder acceso? | **Resuelto:** sí (`FEAT-CRD-009`) |
| ~~M-1~~ | ¿Se paga por adelantado? | **Resuelto:** sí, con retención |
| R-1 | ¿La reserva es por lector o por obra? | Variante pendiente en `FEAT-CRD-009` |
| C-4 | ¿Un mismo lector beta puede comentar varias veces y cobrar cada vez? | Con una retención por acceso, el segundo comentario no tendría respaldo |
| C-6 | ¿Cuánto cuesta un texto de más de 75.000 palabras? | Tramo no cubierto por la tabla |
| C-9 | ¿Se devuelven créditos si el autor oculta el comentario por abusivo? | Protección frente a feedback malicioso |
| C-10 | ¿El nivel se calcula sobre la obra completa o sobre el fragmento comentado? | Con novelas cambia radicalmente el coste |
| **C-15** | ¿Qué se hace con un `FeedbackSubmitted` sin retención asociada? | `RN-7` propone no cobrar y registrar la incidencia. Confirmar |

`C-4` gana importancia con este modelo: si una retención cubre un comentario, un segundo
comentario del mismo lector quedaría sin respaldo y caería en `RN-7`.

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `TODO`. Depende de `FEAT-CRD-009`.
