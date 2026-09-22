---
id: FEAT-CRD-009
title: Reservar créditos al conceder acceso a un lector beta
context: Credits
concept: Reservation
actors: []
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - decision:0004
  - _sources/credit-system.pdf#p1
  - figma:1800-14718 (modal «poner tus obras en corrección»)
endpoints: []
events: [BetaReaderAccessGranted, CreditsReserved, CreditReservationRejected, CreditReservationReleased]
depends_on: [FEAT-CRD-011, FEAT-RDG-001]
updated: 2026-09-22
---

# FEAT-CRD-009 — Reservar créditos al conceder acceso a un lector beta

> Decidido en [`decision:0004`](../../decisions/0004-credit-reservation-on-access-grant.md).
> Esta ficha estaba `DEFERRED` a la espera de esa decisión.

## Resumen

Cuando un lector beta obtiene acceso a una obra, `Credits` **retiene** del saldo del autor el
coste de un feedback para esa obra. La retención se confirma cuando llega el comentario
(`FEAT-CRD-006`) y se libera si el acceso termina sin él.

Si el autor no tiene saldo disponible, **la retención se rechaza y el acceso no se sostiene**.

Es el mecanismo que garantiza que ningún lector beta trabaje sobre una obra que su autor no
puede pagar.

## Conceptos nuevos

### Saldo disponible

```text
saldo            = suma de los movimientos
retenido         = suma de las retenciones en estado HELD
saldo disponible = saldo − retenido
```

**El saldo disponible es el que gobierna lo que el autor puede hacer** y el que se muestra en
la interfaz. El saldo total sigue existiendo para la auditoría.

Toda respuesta de la API que devuelva un saldo debe dejar claro cuál de los dos es. Un número
llamado `balance` a secas es una invitación a equivocarse.

### Retención (`CreditReservation`)

| Campo | Contenido |
|---|---|
| `reservationId` | Identidad |
| `userId` | El autor de la obra, que es quien paga |
| `workId` | Obra a la que se asocia |
| `betaReaderAccessId` | Acceso que la originó |
| `amount` | Importe retenido, calculado por `Credits` |
| `status` | `HELD`, `CONFIRMED`, `RELEASED` |
| `expiresAt` | Caducidad, ver `RN-8` |
| `sourceEventId` | Trazabilidad e idempotencia |

Transiciones: `HELD → CONFIRMED` o `HELD → RELEASED`. **Ningún otro camino.** Una retención
confirmada no vuelve atrás; corregirla es un movimiento nuevo.

## Reglas de negocio

- `RN-1` Una retención se crea al recibir `BetaReaderAccessGranted`.
- `RN-2` El importe lo calcula `Credits` con sus propias reglas, a partir del `textTier` y el
  `questionCount` que llegan en el evento. Ningún otro contexto lo propone.
- `RN-3` La retención solo se crea si el **saldo disponible** la cubre por completo. No hay
  retenciones parciales.
- `RN-4` Sin saldo disponible, `Credits` publica `CreditReservationRejected` y no retiene nada.
- `RN-5` Hay **una retención por acceso concedido**, no por obra ni por autor.
- `RN-6` La retención no es un movimiento: no aparece en el historial hasta que se confirma.
  Lo que se ve mientras tanto es saldo retenido, no saldo gastado.
- `RN-7` Se libera al recibir `BetaReaderAccessRevoked`, `WorkDeleted` o `UserDeleted`.
- `RN-8` Caduca si el lector no comenta en un plazo. **Plazo por definir** (`R-2`). Sin
  caducidad, un lector que pide acceso y desaparece inmoviliza créditos para siempre.
- `RN-9` Todos los handlers son idempotentes por `eventId` (`FEAT-CRD-011`). Procesar dos
  veces la misma concesión produce una sola retención.
- `RN-10` Crear la retención y registrar el `eventId` ocurren en la misma transacción.

## Flujo principal

1. `Reading` concede el acceso y publica `BetaReaderAccessGranted`.
2. `Credits` comprueba si el `eventId` ya se procesó; si sí, descarta.
3. Calcula el importe según sus reglas.
4. Comprueba el saldo disponible del autor.
5. **Con saldo:** crea la retención en `HELD` y publica `CreditsReserved`.
6. **Sin saldo:** publica `CreditReservationRejected`.
7. `Reading` reacciona al rechazo revocando el acceso (compensación).

## La compensación

`Reading` concede el acceso antes de saber si `Credits` puede pagarlo. Entre ambos momentos
hay una ventana en la que el lector tiene acceso a una obra impagable.

Se ataca por dos vías, y hacen falta las dos:

| Vía | Qué hace | Garantía |
|---|---|---|
| **Proyección del saldo** | `Reading` mantiene el saldo disponible de cada autor a partir de `CreditBalanceChanged` y no concede accesos cuando sabe que no hay | Evita el caso común. **No es una garantía**: es de consistencia eventual |
| **Compensación** | Ante `CreditReservationRejected`, `Reading` revoca el acceso y avisa a ambas partes | Corrige siempre, incluidas las carreras |

Confiar solo en la proyección sería la validación síncrona que
[`decision:0004`](../../decisions/0004-credit-reservation-on-access-grant.md) descartó,
disfrazada de read model.

## Eventos

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `BetaReaderAccessGranted` | `Reading` | Intenta retener |
| `BetaReaderAccessRevoked` | `Reading` | Libera la retención |
| `FeedbackSubmitted` | `Feedback` | Confirma la retención (`FEAT-CRD-006`) |
| `WorkDeleted`, `UserDeleted` | `Work`, `User` | Libera las retenciones asociadas |

**Publica**

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `CreditsReserved` | Retención creada | `Notification` | `reservationId`, `userId`, `workId`, `amount`, `availableBalance` |
| `CreditReservationRejected` | Sin saldo disponible | **`Reading`**, `Notification` | `userId`, `workId`, `betaReaderAccessId`, `required`, `available` |
| `CreditReservationReleased` | Retención liberada | `Notification` | `reservationId`, `userId`, `amount`, `reason` |
| `CreditBalanceChanged` | Cambia saldo o retenido | `Reading` (proyección), read models | `userId`, `balance`, `held`, `available` |

`CreditReservationRejected` es el único evento de `Credits` del que depende otro contexto para
deshacer algo. Perderlo deja un acceso concedido sin respaldo, así que su publicación exige
**Outbox Pattern**.

## Persistencia

| Tabla | Contenido |
|---|---|
| `credit_reservation` | Retenciones con su estado y caducidad |
| `credit_account` | Añade el retenido, o se deriva por consulta |

Índices: `credit_reservation(user_id, status)` para calcular el disponible, y
`credit_reservation(expires_at)` para el proceso de caducidad.

**El retenido no se guarda como contador editable** salvo que se demuestre necesario: como el
saldo, debe poder recalcularse desde las retenciones.

## Criterios de aceptación

- [ ] Conceder acceso a una obra retiene el importe correspondiente del autor.
- [ ] El saldo disponible baja; el saldo total no cambia.
- [ ] La retención no aparece en el historial de movimientos.
- [ ] Sin saldo disponible no se crea retención y se publica `CreditReservationRejected`.
- [ ] Ante ese rechazo, `Reading` revoca el acceso.
- [ ] Procesar dos veces el mismo `BetaReaderAccessGranted` crea una sola retención.
- [ ] Revocar el acceso libera la retención y devuelve el disponible.
- [ ] Una retención caducada libera el importe.
- [ ] `saldo disponible = saldo − retenciones HELD`, siempre.
- [ ] Una retención `CONFIRMED` no se puede liberar.
- [ ] `Credits` no consulta a `Work`, `Reading` ni `Feedback` en todo el proceso.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **R-1** | **¿Se reserva por lector o por obra?** | **Nueva evidencia:** el diseño de «Mis relatos» muestra que «En corrección» es un **estado** de la obra (`FEAT-WRK-016`), al que el autor la lleva de forma explícita. Ese es el momento natural de comprometer créditos, y **eliminaría la compensación** entre `Reading` y `Credits` |
| R-2 | ¿Cuánto dura una retención antes de caducar? | Sin caducidad, los créditos quedan inmovilizados indefinidamente |
| R-3 | ¿Qué ve el lector cuando su acceso se revoca por falta de saldo del autor? | Es una revocación que no ha provocado él |
| R-4 | ¿Puede el autor cancelar una retención para recuperar saldo? | Equivaldría a expulsar a un lector beta |
| R-5 | ¿Qué saldo muestra el menú lateral, el total o el disponible? | Propuesta: el disponible, que es el accionable |
| R-6 | ¿Se avisa al autor cuando su saldo disponible impide nuevos lectores? | Momento clave para pedirle que comente obras ajenas |

`R-1` conviene resolverlo **antes de implementar**: es barato ahora y caro después. Con la
reserva por obra, el disparador sería `WorkOpenedForCorrection` en lugar de
`BetaReaderAccessGranted`, y el autor recibiría un «no hay saldo» de inmediato en vez de que
se le revoque un acceso concedido minutos antes.

## Estado

**Especificación:** `DRAFT`. El mecanismo está decidido; falta cerrar `R-1` y `R-2`.

**Implementación:** `TODO`.
