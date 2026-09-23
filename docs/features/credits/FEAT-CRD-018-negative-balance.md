---
id: FEAT-CRD-018
title: Saldo negativo — el corrector cobra siempre
context: Credits
concept: Balance
actors: []
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - conversation:2026-09-23 (rediseño del sistema de créditos)
  - docs/decisions/0006-credit-system.md
endpoints: []
events: [CreditBalanceWentNegative, CreditDebtCleared]
depends_on: [FEAT-CRD-009]
updated: 2026-09-23
---

# FEAT-CRD-018 — Saldo negativo

## Resumen

**El corrector cobra siempre.** Si el autor no llega, el autor queda en negativo; el lector
nunca se queda sin cobrar.

Es una red de seguridad, no una mecánica habitual: la retención
([`FEAT-CRD-009`](FEAT-CRD-009-hold-credits-on-correction-start.md)) existe precisamente para
que casi nunca haga falta.

## Por qué el lector cobra por encima de todo

Si un lector dedica una hora a escribir una crítica y descubre al entregarla que no se le
paga, **no vuelve**. Y sin correctores no hay producto.

Frente a eso, un autor en descubierto tiene arreglo: corrige y lo salda. Por eso la asimetría
es deliberada y el orden de prioridades está claro.

## Cuándo puede ocurrir

Con la retención al empezar, los casos son pocos:

| Caso | Cómo |
|---|---|
| Gasto simultáneo | El autor da una propina mientras alguien corrige |
| Carrera en el límite | Dos retenciones concedidas con saldo justo por una condición de carrera |
| Descubierto deliberado | Gancho de reactivación ([`FEAT-CRD-019`](FEAT-CRD-019-overdraft-correction.md)) |
| Corrección recuperada | Una retención caducada que se confirma tarde (`C-17`) |

Salvo el tercero, todos son excepciones. Si aparecen con frecuencia, algo falla en la
retención y hay que mirarlo ahí, no aquí.

## Reglas de negocio

- `RN-1` El abono al lector **se ejecuta siempre**, aunque el autor no tenga saldo.
- `RN-2` Con saldo negativo **no se reciben más correcciones**: no se concede ninguna
  retención nueva sobre las obras de ese autor.
- `RN-3` Con saldo negativo **sí se puede corregir**. Es como se sale del descubierto, y es lo
  que convierte la deuda en algo sano: devuelves a la comunidad exactamente lo que le debes.
- `RN-4` Lo que se gana corrigiendo **salda la deuda primero**, automáticamente. No hay un
  gesto de «pagar».
- `RN-5` El saldo negativo **no bloquea leer, publicar, comentar ni ninguna otra función**.
  Solo impide recibir correcciones.
- `RN-6` No hay intereses, recargos ni penalizaciones. La deuda es exactamente lo que costó la
  corrección.
- `RN-7` Al volver a cero o más, se **desbloquea todo automáticamente** y se avisa al autor.
- `RN-8` Un saldo negativo **no caduca ni se condona** por el paso del tiempo.

`RN-2` y `RN-3` juntas son el diseño entero: cierras la puerta de recibir y dejas abierta la
de dar. No hay forma de salir del descubierto que no sea participar.

`RN-5` evita convertir esto en un castigo. La deuda limita lo que costó generarla, nada más.

## Cuánto puede llegar a deber alguien

Acotado por construcción:

- `RN-2` impide acumular más de una corrección no cubierta en circunstancias normales;
- el descubierto deliberado está topado en **una corrección** por autor
  (`FEAT-CRD-019`);
- el precio máximo de una corrección es **20**
  ([`FEAT-CRD-016`](FEAT-CRD-016-effort-based-pricing.md)).

En la práctica, nadie debería bajar de **−20**, y lo normal sería entre −2 y −12. Si aparecen
deudas mayores, es señal de un fallo en la retención (`C-20`).

## Lo que esto le cuesta a la economía

**Una deuda que no se recupera es crédito emitido sin respaldo.** El lector tiene créditos
reales; la deuda no la paga nadie.

Mientras el autor vuelva y corrija, la masa cuadra. Si no vuelve nunca, el sistema ha emitido.
Por eso la **tasa de recuperación del descubierto** es una de las métricas de
[`FEAT-CRD-012`](FEAT-CRD-012-economy-health.md), y por eso la invariante contable del
sistema se enuncia así:

```text
suma de saldos (incluidos negativos) = grifos − descubierto no recuperado
```

Si esa igualdad se rompe, hay un movimiento que no es una transferencia.

## Eventos

**Publica**

| Evento | Cuándo | Consumidores |
|---|---|---|
| `CreditBalanceWentNegative` | El saldo cruza a negativo | `Notification` (avisa al autor y le explica cómo salir) |
| `CreditDebtCleared` | Vuelve a cero o más | `Notification`, `Feedback` (se desbloquea recibir) |

El aviso de `CreditBalanceWentNegative` tiene que **explicar la salida**, no solo informar del
problema. Un usuario que descubre que debe créditos y no sabe qué hacer, se va.

## Modelo de datos afectado

Ninguna tabla nueva. El saldo pasa a admitir valores negativos, lo que tiene una consecuencia
concreta: **cualquier restricción de base de datos que impida un saldo negativo hay que
quitarla**, y cualquier tipo sin signo también.

Es el tipo de detalle que se descubre tarde y en producción.

## Criterios de aceptación

- [ ] Una corrección entregada abona al lector aunque el autor no tenga saldo.
- [ ] El autor queda exactamente en `saldo − precio`, sin recargo.
- [ ] Con saldo negativo no se concede ninguna retención nueva sobre sus obras.
- [ ] Con saldo negativo se puede corregir con normalidad.
- [ ] Lo ganado corrigiendo salda la deuda automáticamente, sin acción del usuario.
- [ ] Al llegar a cero, recibir correcciones se desbloquea solo.
- [ ] El saldo negativo no impide leer, publicar ni comentar.
- [ ] La suma de todos los saldos cuadra con la invariante contable.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| C-20 | ¿Se alerta a operaciones si alguien baja de un umbral? | Una deuda grande es señal de fallo en la retención |
| C-21 | ¿Qué ocurre con la deuda si el usuario elimina su cuenta? | Anonimizar no cobra la deuda. Ligado a `S-34` |
| C-22 | ¿Puede un autor «cerrar» sus obras para no seguir generando deuda? | Con `RN-2` no hace falta, pero conviene confirmarlo |

`C-21` conecta con la eliminación de cuenta
([`FEAT-USR-013`](../user/FEAT-USR-013-delete-account.md)): una cuenta anonimizada con deuda
es, contablemente, emisión. Lo honesto es contabilizarla como tal y no fingir que se cobra.

## Estado

**Especificación:** `DRAFT`.

**Implementación:** `TODO`.
