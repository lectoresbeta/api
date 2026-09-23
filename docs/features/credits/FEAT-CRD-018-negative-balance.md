---
id: FEAT-CRD-018
title: Saldo negativo y correcciones bloqueadas
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

# FEAT-CRD-018 — Saldo negativo y correcciones bloqueadas

## Resumen

**El corrector cobra siempre.** Si el autor no llega, queda en negativo; el lector nunca se
queda sin cobrar.

Y la regla que lo cierra:

> **Una corrección que deja el saldo en negativo se entrega bloqueada.** El autor ve que
> existe —quién, cuándo, sobre qué capítulo, cuánto se ha escrito— pero no su contenido, hasta
> que reponga saldo.

Como **no se retiene nada** ([`FEAT-CRD-009`](FEAT-CRD-009-balance-check-on-correction-start.md)),
esto no es una rareza: es el desenlace normal cuando dos lectores coinciden.

## Por qué el lector cobra por encima de todo

Si un lector dedica una hora a escribir una crítica y descubre al entregarla que no se le
paga, **no vuelve**. Y sin correctores no hay producto.

Frente a eso, un autor en descubierto tiene arreglo: corrige y lo salda. Por eso la asimetría
es deliberada y el orden de prioridades está claro.

## Cuándo ocurre, y con qué frecuencia

| Caso | Cómo |
|---|---|
| **Carrera entre lectores** | Dos o más empiezan a corregir el mismo capítulo con saldo para uno |
| Gasto simultáneo | El autor da una propina mientras alguien corrige |
| Descubierto deliberado | Gancho de reactivación ([`FEAT-CRD-019`](FEAT-CRD-019-overdraft-correction.md)) |

**La carrera no es un caso raro y conviene no fingir que lo es.** Escribir una corrección
lleva horas o días, así que la ventana en la que dos lectores coinciden sobre un mismo
capítulo es larga. Un capítulo atractivo con saldo justo para una corrección puede recibir
tres en una semana y dejar al autor en −24.

De ahí se siguen dos cosas:

1. **La interfaz tiene que explicarlo bien.** Un autor que ve una corrección bloqueada sin
   entender por qué lo vivirá como un castigo arbitrario.
2. **Conviene medirlo por separado** del descubierto deliberado
   ([`FEAT-CRD-012`](FEAT-CRD-012-economy-health.md)): si es alto, el saldo típico es
   demasiado ajustado y la respuesta es subir el regalo de bienvenida, no cambiar la mecánica.

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
- `RN-9` Una corrección que deja el saldo en negativo **se entrega bloqueada**: el autor ve
  sus metadatos, no su contenido.
- `RN-10` Al volver a saldo ≥ 0, **todas** las correcciones bloqueadas se desbloquean a la
  vez. No hay desbloqueo parcial: es más simple y el resultado agregado es el mismo.
- `RN-11` Una corrección **ya visible nunca vuelve a bloquearse**, aunque el autor caiga en
  negativo más tarde. Lo que se ha leído, leído está.

`RN-2` y `RN-3` juntas son el diseño entero: cierras la puerta de recibir y dejas abierta la
de dar. No hay forma de salir del descubierto que no sea participar.

`RN-5` evita convertir esto en un castigo. La deuda limita lo que costó generarla, nada más.

## Cuánto puede llegar a deber alguien

Acotado por construcción:

- `RN-2` impide acumular más de una corrección no cubierta en circunstancias normales;
- el descubierto deliberado está topado en **una corrección** por autor y con **cupo
  periódico** (`FEAT-CRD-019`);
- el precio máximo de una corrección es **20**
  ([`FEAT-CRD-016`](FEAT-CRD-016-effort-based-pricing.md)).

En la práctica la deuda típica será de una corrección, entre −2 y −12. Puede ser mayor si
varios lectores coinciden: `N` lectores simultáneos sobre un capítulo de precio `p` dejan al
autor hasta en `−(N−1) × p`.

Si conviene acotarlo, la palanca no es reintroducir la retención sino **limitar cuántas
correcciones abiertas admite un capítulo a la vez** (`C-41`). Acota el problema sin apartar un
solo crédito.

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
- [ ] Una corrección que deja al autor en negativo llega bloqueada, con sus metadatos visibles.
- [ ] Al volver a cero, todas las bloqueadas se desbloquean a la vez.
- [ ] Una corrección ya leída no vuelve a bloquearse.

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
