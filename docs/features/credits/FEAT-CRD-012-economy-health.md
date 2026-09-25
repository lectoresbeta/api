---
id: FEAT-CRD-012
title: Salud de la economía de créditos
context: Credits
concept: Monitoring
actors: [Admin]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - conversation:2026-09-23 (rediseño del sistema de créditos)
  - docs/decisions/0006-credit-system.md
endpoints:
  - GET /admin/credits/health
events: []
depends_on: [FEAT-CRD-008]
updated: 2026-09-25
---

# FEAT-CRD-012 — Salud de la economía de créditos

## Resumen

Cuatro métricas y una invariante. No es un panel bonito: es el instrumento que dice si el
sistema de créditos está funcionando y cuál de las palancas hay que mover.

## La invariante contable

```text
suma de todos los saldos (incluidos los negativos)
      = grifos − descubierto no recuperado
```

**Es un test, no una aspiración.** Si deja de cumplirse, hay un movimiento en el sistema que
no es una transferencia, y eso es un error grave: significa que alguien tiene créditos que
nadie pagó.

Conviene comprobarla de forma automática y periódica, no solo en los tests.

## Las cuatro métricas

| Métrica | Qué detecta | Qué hacer si va mal |
|---|---|---|
| **Capítulos corregibles por corrector activo** | Los lectores no tienen dónde ganar | Subir el regalo de bienvenida |
| **% de usuarios a cero o en negativo** | Concentración de saldo | Considerar el tope de saldo |
| **Tiempo hasta la primera corrección recibida** | Es lo que el usuario siente | Mirar las dos anteriores |
| **Tasa de recuperación del descubierto** | Si es baja, el gancho de reactivación es emisión pura | Bajar el cupo, o ponerlo a 0 |
| **Descubierto por carrera** | Si es alto, el saldo típico es demasiado ajustado | Subir el regalo de bienvenida, o bajar el tope de correcciones simultáneas |
| **Ajustes manuales** | Es la única vía de crédito que no es transferencia ni grifo | Revisar por qué hacen falta tantos |

La tercera es la única que un usuario notaría, y por eso es la que conviene vigilar a diario.
Las otras tres explican por qué se mueve.

## Las palancas, por orden

1. **El regalo de bienvenida.** Afecta solo a los usuarios nuevos, es reversible y es la única
   que mueve la masa total.
2. **Las dos constantes del precio** (1.000 y 100). **No cambian la masa**, porque el precio
   es una transferencia: cambian la velocidad de circulación y el precio relativo de lo largo
   frente a lo corto.
3. **Un tope de saldo**, como freno de emergencia contra la concentración. No forma parte del
   diseño de salida porque castiga precisamente a los mejores correctores.

Que la segunda palanca no toque la masa es lo que permite ajustar la justicia del sistema sin
arriesgar su estabilidad. Es la consecuencia práctica más útil de la transferencia pura.

## Por qué esto no es opcional

Un sistema de créditos sin medición es un sistema que se descubre roto por las quejas. Las
dos formas de morir —nadie tiene créditos, o los créditos no valen nada— tardan semanas en
manifestarse y son caras de revertir, porque para entonces los saldos ya están formados.

## Reglas de negocio

- `RN-1` Las métricas se calculan sobre el **registro de movimientos**, que es inmutable. No
  hay contadores paralelos que puedan desviarse.
- `RN-2` El panel **no expone datos de usuarios concretos** más allá de lo necesario: son
  cifras agregadas.
- `RN-3` La invariante se comprueba de forma **automática y periódica**, y su
  incumplimiento es una alerta, no una línea de log.

## Criterios de aceptación

- [x] La invariante contable se comprueba automáticamente y alerta si falla.
- [x] Las métricas son consultables por periodo. **Las de periodo**: la invariante y el
  reparto de saldos son una foto de ahora y no admiten acotación, porque un saldo no tiene
  historia acotable sin recorrer todo el registro.
- [x] Las métricas se derivan del registro de movimientos.
- [x] El regalo de bienvenida se cambia por configuración, sin desplegar código.
- [x] Las dos constantes del precio también.

Las dos últimas ya se cumplían antes de esta funcionalidad: `app.welcome_credits` y
`app.pricing.*` viven en `config/services.yaml` desde `FEAT-CRD-016`. Se dejan marcadas porque
son criterios de esta ficha y alguien tendría que comprobarlos.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| C-33 | ¿Con qué frecuencia se comprueba la invariante? | **Parcialmente resuelta**: hay comando; cada cuánto se ejecuta es decisión de operación y no se programa desde el código |

Resueltas: `MOD-1` (**−40**, el que propone `FEAT-CRD-018` `RN-12`) y `C-34` (cada métrica
tiene su umbral en `config/services.yaml`: `app.economy.debt_alert_threshold`,
`app.economy.zero_balance_alert_share` y `app.economy.overdraft_recovery_alert_rate`).

Los dos últimos son una primera propuesta a falta de datos reales, y están en configuración
precisamente para poder corregirlos cuando los haya.

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `DONE` (2026-09-25).

Tres cosas que la ficha no preveía y que la implementación obligó a decidir:

- **la invariante no lleva el término del descubierto, y la ficha se equivocaba.** Escrita como
  «saldos = grifos − descubierto no recuperado», ese término sobra: los saldos negativos ya
  están dentro de la suma, así que restarlos otra vez haría fallar la identidad justo cuando
  alguien está endeudado, que es un estado normal y previsto
  ([`FEAT-CRD-018`](FEAT-CRD-018-negative-balance.md)). La forma implementada es una igualdad
  exacta, **más fuerte y más fácil de comprobar**;
- **son tres cifras y no dos**, y la tercera es la que vale. `issued` frente a `moved` detecta
  un movimiento que cobra sin pagar; `moved` frente a `balances` detecta un saldo que se ha
  desviado de su propia historia. Comparar solo dos dejaría uno de los dos fallos invisible, y
  el segundo solo se ve porque las dos cifras llegan por caminos independientes;
- **la comprobación periódica es un comando de consola, no un chequeo de `/health`.** Aquel es
  público y lo consultan sondas sin credenciales; esto suma sobre todo el histórico. El
  comando registra a nivel `error` y sale con código distinto de cero, que son las dos formas
  de que un fallo se vea sin que nadie esté mirando.

Y una decisión de alcance: **`Credits` no publica «capítulos corregibles por corrector
activo»**, sino solo el numerador. Cuántos correctores están activos lo sabe `Feedback`, y
preguntárselo pondría en un panel de un contexto una cifra compuesta de dos.

**Falta** «tiempo hasta la primera corrección recibida», que es la única métrica de la ficha
que un usuario notaría y **no es calculable dentro de este contexto**: la fecha de la primera
corrección vive en `Feedback`. Encaja como métrica de allí, o como un panel compuesto que
todavía no existe.
