---
id: FEAT-CRD-002
title: Abonar los créditos de bienvenida al activar la cuenta
context: Credits
concept: Account
actors: []
spec_status: APPROVED
impl_status: TODO
priority: P0
sources:
  - docs/decisions/0006-credit-system.md
  - conversation:2026-09-23 (rediseño del sistema de créditos)
endpoints: []
events: [AccountActivated, CreditsAdded]
depends_on: [FEAT-USR-020, FEAT-CRD-011]
updated: 2026-09-23
---

# FEAT-CRD-002 — Abonar los créditos de bienvenida al activar la cuenta

## Resumen

Cuando un usuario activa su cuenta, `Credits` le abona **10 créditos**, una sola vez.

Es **el único grifo ordinario del sistema** junto a la bonificación por invitación
([`decision:0006`](../../decisions/0006-credit-system.md) §5 y §6): todo lo demás son
transferencias entre usuarios, que mueven créditos sin crearlos.

De ahí sale la propiedad que gobierna la economía entera:

> **El promedio de créditos por usuario es siempre el tamaño del regalo de bienvenida.**

No es una consecuencia menor. Significa que este número **no es un parámetro de producto que
se pueda ajustar a ojo**: es la masa monetaria del sistema dividida entre sus usuarios. Subirlo
a 20 duplica el saldo medio; bajarlo a 5 lo reduce a la mitad, y con él la probabilidad de que
alguien pueda recibir una corrección sin haber dado ninguna.

## Por qué al activar y no al registrarse

El registro es gratis: crear una cuenta cuesta treinta segundos. Activarla exige **un correo
que el usuario controla**, que es poco, pero es la primera barrera real del embudo.

Conviene ser honesto sobre su alcance: **un correo desechable la salva**. Esta funcionalidad no
es, y no debe pretender ser, el control antifraude del multicuenta. Lo que hace es no regalar
créditos a direcciones que ni siquiera existen, que es lo que ocurriría abonando al registrarse.

El control fuerte vive en otro sitio: la bonificación por invitación se abona **al entregar la
primera corrección** precisamente porque falsificar eso cuesta una corrección real
([`decision:0006`](../../decisions/0006-credit-system.md) §6).

## Qué sabe `Credits` del usuario

**Nada, y es deliberado.** `Credits` no conoce el registro, ni el correo, ni el estado de la
cuenta. Solo recibe el hecho `AccountActivated` y decide por su cuenta qué significa.

El importe **no viaja en el evento**. `User` publica «esta cuenta se ha activado»; que eso
valga 10 créditos es una regla de `Credits` y de nadie más
([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md)).

## Reglas de negocio

- `RN-1` Al recibir `AccountActivated` se registra un movimiento de **+10** con motivo
  `WELCOME_GRANT`.
- `RN-2` El abono es **un grifo**: crea créditos (`CreditTransactionReason::isTap()`). No tiene
  contrapartida en ninguna otra cuenta.
- `RN-3` **Como máximo un `WELCOME_GRANT` por usuario, para siempre.** Es una invariante del
  agregado `CreditAccount`, comprobada sobre sus movimientos.
- `RN-4` `RN-3` es **independiente de la deduplicación de eventos**
  ([`FEAT-CRD-011`](FEAT-CRD-011-deduplicate-integration-events.md)) y no la sustituye. La
  deduplicación protege de recibir **el mismo `eventId` dos veces**; `RN-3` protege de recibir
  **dos activaciones legítimas distintas** del mismo usuario, que traerían dos `eventId`
  diferentes y pasarían el filtro.
- `RN-5` La `CreditAccount` se crea **perezosamente**, en el primer evento que tenga efecto
  sobre ese usuario. `Credits` **no se suscribe a `UserRegistered`**.
- `RN-6` El movimiento y su fila de deduplicación se escriben en **la misma transacción**
  (`FEAT-CRD-011` `RN-2`).
- `RN-7` Tras abonar se publica `CreditsAdded`. `Credits` no envía notificaciones: publica el
  hecho y `Notification` decide.
- `RN-8` El importe es una **constante del contexto**. Cambiarlo afecta solo a las
  activaciones posteriores: **no se recalcula hacia atrás**, porque un movimiento es inmutable.
- `RN-9` Si el evento llega para un usuario que ya tiene movimientos —reproceso manual del
  transporte de fallos, por ejemplo— `RN-3` lo detiene sin error.

`RN-5` es la regla que mantiene el aislamiento. La alternativa —suscribirse también a
`UserRegistered` para crear una cuenta a cero— obligaría a `Credits` a conocer un hecho que no
le afecta, a cambio de nada: una cuenta sin movimientos y un saldo de cero son indistinguibles.

`RN-3` merece explicarse porque hoy parece innecesaria: **no existe flujo de reactivación**
([`FEAT-USR-013`](../user/FEAT-USR-013-delete-account.md) anonimiza la cuenta, no la revive).
Se especifica igualmente porque el coste de tenerla ahora es una consulta sobre movimientos que
ya hay que hacer, y el coste de añadirla después es auditar cuántos usuarios cobraron dos veces.

## Flujo

```text
User                        RabbitMQ                    Credits
  │                             │                          │
  ├─ cuenta ACTIVE              │                          │
  ├─ AccountActivated ─────────▶│                          │
  │   {eventId, userId,         ├─────────────────────────▶│
  │    activatedAt}             │                          ├─ ¿eventId procesado? → no
  │                             │                          ├─ ¿ya tiene WELCOME_GRANT? → no
  │                             │                          ├─┐ una transacción
  │                             │                          │ ├─ CreditAccount (si no existe)
  │                             │                          │ ├─ CreditTransaction +10
  │                             │                          │ └─ ProcessedEvent
  │                             │◀─ CreditsAdded ──────────┤
  │                             │                          │
```

## Eventos

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `AccountActivated` | `User` ([`FEAT-USR-020`](../user/FEAT-USR-020-activate-account.md)) | +10 con motivo `WELCOME_GRANT`, una vez por usuario |

Payload relevante: `userId`, `activatedAt`. **No lleva importe**, por `decision:0002`.

**Publica**

| Evento | Cuándo | Consumidores |
|---|---|---|
| `CreditsAdded` | Tras registrar el abono | `Notification` |

Payload: `userId`, `amount`, `reason`, `balance`
([catálogo de eventos](../../events/README.md)).

## Modelo de datos afectado

**Ninguna tabla nueva.** Ya existen:

| Tabla | Uso aquí |
|---|---|
| `credits_ctx.credit_account` | Se crea la fila del usuario si no existía (`RN-5`) |
| `credits_ctx.credit_transaction` | Un movimiento `+10` con motivo `WELCOME_GRANT` |
| `credits_ctx.processed_event` | La fila de deduplicación, en la misma transacción |

Para `RN-3` hace falta poder responder «¿este usuario ya tiene un `WELCOME_GRANT`?» sin leer
todos sus movimientos. El índice por `(user_id, reason)` no existe todavía y **entra con la
implementación**, no antes.

## Criterios de aceptación

- [ ] Un `AccountActivated` de un usuario sin cuenta de créditos la crea y deja saldo `10`.
- [ ] El movimiento queda con motivo `WELCOME_GRANT` y con el `eventId` de origen.
- [ ] Reentregar **el mismo** `AccountActivated` no produce un segundo movimiento.
- [ ] Un **segundo `AccountActivated` distinto** del mismo usuario tampoco lo produce.
- [ ] Si falla la escritura del movimiento, no queda fila en `processed_event` (y al revés).
- [ ] Se publica exactamente un `CreditsAdded` por abono efectivo, y ninguno cuando no se abona.
- [ ] `Credits` no referencia ninguna clase de `User` en ninguna capa.
- [ ] Un usuario registrado y **no** activado no tiene cuenta de créditos ni saldo.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| `C-42` | ¿Se conserva el `WELCOME_GRANT` si algún día se permite reactivar una cuenta anonimizada? | Ninguno hoy: `RN-3` ya decide que no se repite. Solo cambiaría si el producto quisiera lo contrario |

## Estado

**Especificación:** `APPROVED` (2026-09-23). El importe, el momento y el mecanismo vienen de
[`decision:0006`](../../decisions/0006-credit-system.md); `RN-3` (una vez por usuario, no por
evento) y `RN-5` (la cuenta nace perezosa) quedan validadas.

**Implementación:** `TODO`.
