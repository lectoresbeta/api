---
id: FEAT-CRD-008
title: Consultar el historial de movimientos de créditos
context: Credits
concept: Account
actors: [User]
spec_status: APPROVED
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-25 (bloque de deudas y transparencia)
  - docs/decisions/0006-credit-system.md
endpoints:
  - GET /credits/movements
events: []
depends_on: [FEAT-CRD-001, FEAT-CRD-006]
updated: 2026-09-25
---

# FEAT-CRD-008 — Historial de movimientos de créditos

## Resumen

La lista de todo lo que ha movido el saldo de una persona: qué, cuánto, cuándo y **por qué
texto**.

## Por qué hace falta ahora

Hasta ahora el saldo solo subía al activarse la cuenta y se movía al entregarse una
corrección. Desde [`FEAT-MOD-002`](../moderation/FEAT-MOD-002-review-claim.md) hay algo nuevo:
**una reclamación estimada puede retirarte créditos que ya habías cobrado**, días después y
sin que tú hayas hecho nada.

Un saldo que baja once créditos sin explicación, con la propina y el descubierto también en
juego, es la clase de opacidad que hace que la gente deje de confiar en la moneda. El
historial es lo que convierte el saldo en algo comprobable:

> La invariante contable de este contexto es que **el saldo es la suma de sus movimientos**
> ([`decision:0006`](../../decisions/0006-credit-system.md) `RN-1`). Esta pantalla es esa
> invariante, enseñada.

## Qué muestra cada apunte

| Dato | Ejemplo |
|---|---|
| Importe con signo | `−11` |
| Motivo | `CORRECTION_CHARGED` |
| Fecha | Cuándo ocurrió |
| **El objeto** | «capítulo 3 de *101 días en Japón*» |
| Saldo resultante | El que quedó después |

**No lleva la contraparte con nombre.** El objeto ya dice de qué se trata a quien quiera
saberlo —la corrección de ese capítulo tiene autor y corrector, y las dos partes la ven en su
sitio— y una lista con nombres convertiría el historial en un registro de relaciones
económicas entre personas, que es otra cosa y más delicada.

## Reglas de negocio

- `RN-1` Cada uno ve **solo su historial**. No hay forma de consultar el de otra persona, ni
  siquiera parcialmente.
- `RN-2` Los movimientos son **inmutables**: esta pantalla es de solo lectura y no existe
  ninguna operación que edite o borre uno. Corregir un error es **añadir otro movimiento**.
- `RN-3` Se ordena **del más reciente al más antiguo** y se pagina.
- `RN-4` Cada apunte lleva **el saldo resultante** en ese momento, no solo el importe. Es lo
  que permite a una persona seguir la cuenta hacia atrás sin sumar a mano.
- `RN-5` Una **reversión** se presenta como lo que es —dos apuntes nuevos, no una corrección
  del original— y cita la reclamación que la causó
  ([`FEAT-MOD-002`](../moderation/FEAT-MOD-002-review-claim.md)).
- `RN-6` El objeto citado se identifica **sin filtrar nada que la persona no pueda ver ya**:
  si corrigió ese capítulo o es su autor, conoce la obra.
- `RN-7` Un movimiento con **precio reconstruido** (`FEAT-CRD-006` `RN-7`) se marca como tal.
  Una reparación que no deja rastro es indistinguible de lo real cuando alguien audita meses
  después.
- `RN-8` Se puede filtrar por **motivo** y por **rango de fechas**.
- `RN-9` Esta consulta **no crea ni mueve nada**, y en particular no crea la cuenta de
  créditos de quien todavía no tiene ninguna: responde una lista vacía y saldo cero.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Sin sesión | Se rechaza | `401` |
| Sin movimientos | Lista vacía y saldo `0` | `200` |
| El objeto citado ya no existe (obra borrada) | El apunte se mantiene, sin enlace | `200` |

El último es la consecuencia de que un movimiento no tenga clave foránea hacia otro contexto,
y es deliberado: el historial de una persona no puede depender de que nadie borre nada.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Mis movimientos | `GET /credits/movements` | `listCreditMovements` |

Cuelga de `/credits` como `getCreditBalance`, y no de `/me`, porque el saldo ya vive ahí.

## Eventos

Ninguno. Consultar no es un hecho.

## Modelo de datos afectado

`credits_ctx.credit_transaction` ya tiene todo lo necesario: importe con signo, motivo,
`event_id`, `metadata` y `occurred_at`, con índice `(user_id, occurred_at)`.

El **saldo resultante** no está almacenado y se calcula al servir la página, acumulando hacia
atrás desde el saldo actual. Guardarlo en cada fila sería un dato derivado que puede
contradecir a la suma, que es justo lo que `RN-11` de
[`decision:0006`](../../decisions/0006-credit-system.md) existe para detectar.

## Criterios de aceptación

- [ ] Cada uno ve su historial, el movimiento más reciente primero.
- [ ] Cada apunte lleva importe con signo, motivo, fecha, objeto y saldo resultante.
- [ ] La suma de los movimientos coincide con el saldo que devuelve `getCreditBalance`.
- [ ] Una reversión aparece como dos apuntes nuevos y cita su reclamación.
- [ ] Un movimiento con precio reconstruido se distingue.
- [ ] Se filtra por motivo y por fechas.
- [ ] No hay forma de consultar el historial de otra persona.
- [ ] Una cuenta sin movimientos responde lista vacía, y la consulta no la crea.
- [ ] Un apunte cuyo objeto ya no existe se sigue mostrando.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| C-47 | ¿Se puede exportar el historial? | Es el registro económico de una persona; pedirlo es razonable |
| C-48 | ¿Se agrupan los dos apuntes de una transferencia en una sola línea? | El corrector ve «+11» y el autor «−11»; cada uno ve solo el suyo, así que probablemente no haga falta |
| C-49 | ¿Cuánta historia se conserva? | Un movimiento por corrección crece despacio, pero crece siempre |

## Estado

**Especificación:** `APPROVED` (2026-09-25).

**Implementación:** `TODO`. El repositorio ya expone `historyOf()` y `balanceOf()`; falta el
caso de uso, la resolución del objeto citado y el endpoint.
