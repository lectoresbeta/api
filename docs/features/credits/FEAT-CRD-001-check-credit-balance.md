---
id: FEAT-CRD-001
title: Consultar el saldo de créditos
context: Credits
concept: Account
actors: [User]
spec_status: REVIEW
impl_status: TODO
priority: P0
sources:
  - docs/decisions/0006-credit-system.md
  - conversation:2026-09-23 (rediseño del sistema de créditos)
endpoints: [GET /credits/balance]
events: []
depends_on: [FEAT-CRD-002]
updated: 2026-09-23
---

# FEAT-CRD-001 — Consultar el saldo de créditos

## Resumen

Un usuario autenticado consulta **su** saldo de créditos.

Es la operación más simple del contexto y la más usada: la cabecera de la aplicación la muestra
en todo momento, y de ella depende que el usuario entienda por qué puede o no puede pedir una
corrección.

## Un solo número

```text
saldo = suma de los movimientos
```

**No hay «saldo disponible» frente a «saldo total».** El sistema no retiene créditos
([`decision:0006`](../../decisions/0006-credit-system.md) §3), así que lo que el usuario ve es
lo que tiene.

Que sea un solo número no es un detalle de implementación: es lo que permite que ninguna
pantalla tenga que explicar por qué dos cifras difieren, y por eso el campo se llama `balance` a
secas.

**El saldo puede ser negativo** ([`FEAT-CRD-018`](FEAT-CRD-018-negative-balance.md)). Un entero
con signo, sin tipos sin signo y sin restricciones que lo impidan en ningún punto del camino.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Consultar **su propio** saldo | Autenticado |

No hay forma de consultar el saldo de otro usuario. **Ni siquiera para un moderador**: la vista
agregada de la economía es [`FEAT-CRD-012`](FEAT-CRD-012-economy-health.md), y trabaja con
totales, no con saldos individuales.

Por eso la ruta **no lleva identificador**: el usuario sale del token. Una ruta
`/credits/{userId}/balance` obligaría a comprobar en cada petición que el identificador es el
propio, y esa es la clase de comprobación que un día se olvida.

## Por qué `/credits/balance` y no `/me/credits`

La API usa `/me/*` para los recursos propios del usuario, así que `/me/credits` parecería lo
coherente. Se descarta por la convención de enrutado
([`decision:0011`](../../decisions/0011-route-files-live-inside-their-context.md)): **cada
bounded context declara sus rutas en su propio fichero**, y `/me/*` es el espacio de `User`.
Que `Credits` colgase una ruta de ahí repartiría la propiedad de un mismo espacio de URL entre
dos contextos, y la pregunta «¿qué expone `User`?» dejaría de responderse leyendo un fichero.

## Reglas de negocio

- `RN-1` El saldo devuelto es **la suma de los movimientos** del usuario, no un contador que se
  actualice por separado. Debe poder recalcularse desde cero en cualquier momento.
- `RN-2` Un usuario **sin cuenta de créditos** —registrado y todavía sin activar
  ([`FEAT-CRD-002`](FEAT-CRD-002-welcome-credit-grant.md) `RN-5`)— recibe `balance: 0`, **no un
  `404`**. No tener movimientos no es no existir.
- `RN-3` El saldo puede ser **negativo** y se devuelve tal cual, sin recortar a cero.
- `RN-4` La respuesta **no se cachea**: cambia por hechos ajenos a quien la consulta, como que
  alguien entregue una corrección sobre su obra.

`RN-2` es la regla que evita el error más probable de esta operación. Consultar el saldo es de
las primeras cosas que hace la interfaz tras iniciar sesión, **antes de que muchos usuarios
hayan activado la cuenta**; un `404` ahí rompería la cabecera de la aplicación para todos ellos.

## Contrato de API

| Operación | Método y ruta | `operationId` | Documento |
|---|---|---|---|
| Consultar el saldo propio | `GET /credits/balance` | `getCreditBalance` | [`credits.md`](../../api/endpoints/credits.md) |

Respuesta:

```json
{ "balance": 10 }
```

**Y nada más.** Ni movimientos, ni fecha del último, ni indicadores derivados:

| Lo que no va aquí | Dónde va |
|---|---|
| El historial de movimientos | `FEAT-CRD-008` |
| Si el usuario está bloqueado por deuda | [`FEAT-CRD-018`](FEAT-CRD-018-negative-balance.md), en el recurso afectado |
| El precio de un capítulo | [`FEAT-CRD-013`](FEAT-CRD-013-work-credit-badge.md) |
| Los totales de la economía | [`FEAT-CRD-012`](FEAT-CRD-012-economy-health.md) |

Un campo añadido aquí «porque la cabecera ya llama a este endpoint» es el principio de un
recurso que devuelve media aplicación.

## Errores específicos

Ninguno propio. Solo los transversales: `401` sin autenticar.

En particular **no existe un `404`** en esta operación (`RN-2`).

## Eventos

No publica ni consume ninguno. Es una lectura.

## Modelo de datos afectado

Ninguna tabla nueva. Lee `credits_ctx.credit_account` y `credits_ctx.credit_transaction`.

La suma de movimientos por usuario tiene que ser barata: es la consulta más frecuente del
contexto. Si el recuento de movimientos lo hace caro, la respuesta es un saldo materializado en
`credit_account` **actualizado en la misma transacción que el movimiento**, nunca un valor que
se pueda editar por su cuenta (`RN-1`). Se decide con datos, no por adelantado.

## Criterios de aceptación

- [ ] Un usuario activado recién llegado ve `balance: 10`.
- [ ] Un usuario registrado y sin activar ve `balance: 0` con `200`, no `404`.
- [ ] Un usuario en descubierto ve su saldo negativo tal cual.
- [ ] Sin autenticar, `401`.
- [ ] La respuesta corresponde siempre al usuario del token, sin identificador en la ruta.
- [ ] El saldo devuelto coincide con la suma de los movimientos del usuario.
- [ ] La respuesta no es cacheable.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `REVIEW` — completa. Lo que se somete a validación es la ruta
(`/credits/balance` frente a `/me/credits`, argumentado arriba) y `RN-2`, que fija que la
ausencia de cuenta es un saldo de cero y no un error.

**Implementación:** `TODO`. La operación no entra en `openapi/` hasta que la ficha esté
`APPROVED`.
