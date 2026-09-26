---
id: FEAT-COM-035
title: Denunciar a un usuario
context: Community
concept: Relationship
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/features/moderation/FEAT-MOD-001-submit-claim.md
  - conversation:2026-09-25
endpoints:
  - submitClaim
events: []
depends_on: [FEAT-MOD-001, FEAT-COM-034]
updated: 2026-09-25
---

# FEAT-COM-035 — Denunciar a un usuario

## Resumen

Denunciar la conducta de una persona, no un texto concreto. Desemboca en
[`FEAT-MOD-001`](../moderation/FEAT-MOD-001-submit-claim.md) con `targetType: USER`.

## Denunciar y bloquear son cosas distintas

Conviene leerlo junto a [`FEAT-COM-034`](FEAT-COM-034-block-user.md), porque la confusión es
fácil y las consecuencias no se parecen:

| | Bloquear | Denunciar |
|---|---|---|
| Quién decide | Tú, y es inmediato | Moderación, y tarda |
| Qué cambia | Dejáis de veros | Nada, hasta que alguien lo mire |
| Se lo dice al otro | No | No |
| Sirve para | No quiero tratar con esta persona | Esta persona está haciendo algo que no debería |

Son compatibles: bloquear a alguien no impide denunciarle, y denunciar a alguien no le
bloquea. Quien quiera las dos cosas hace las dos.

## Reglas de negocio

- `RN-1` Cualquier persona con cuenta activada puede denunciar a otra, con un motivo del
  catálogo.
- `RN-2` La denuncia queda **contra esa persona**, que es lo que permite a moderación ver de
  quién se quejan varias personas distintas (`FEAT-MOD-005`).
- `RN-3` **Nadie se denuncia a sí mismo.** Gastaría el cupo de quien la presenta y el tiempo
  de quien la lee, y no hay desenlace posible que signifique algo. Si alguien quiere irse, eso
  es dar de baja la cuenta.
- `RN-4` Denunciar a quien no existe **responde lo mismo que denunciar algo no reclamable**.
  Decir «esa cuenta no existe» convertiría el formulario de denunciar en un comprobador de
  quién está en la plataforma, que es justo lo que el alta y la recuperación de contraseña se
  cuidan de no decir.
- `RN-5` Una por persona y objeto: repetirla devuelve la que ya existe. Diez denuncias de
  alguien sobre la misma persona no son diez señales, son la misma repetida.
- `RN-6` **No produce ningún efecto**: no bloquea, no oculta nada y no avisa al señalado.
- `RN-7` Cuenta para el cupo mensual y el bloqueo acumulativo de `FEAT-MOD-001`.

## Contrato de API

Ninguno nuevo: es `POST /api/v1/claims` (`submitClaim`) con `targetType: USER`.

| Caso | Respuesta |
|---|---|
| Contra uno mismo | `422 CANNOT_CLAIM_AGAINST_YOURSELF` |
| Cuenta inexistente, o identificador mal formado | `404 CLAIM_TARGET_NOT_CLAIMABLE` |
| Cupo mensual agotado | `429 CLAIM_MONTHLY_LIMIT_SPENT` |

## Eventos

Ninguno propio.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Ninguno.

## Criterios de aceptación

- [x] Cualquiera puede denunciar a otra persona.
- [x] Nadie se denuncia a sí mismo.
- [x] Denunciar a quien no existe no confirma que no existe.
- [x] Repetirla devuelve la que ya existe.
- [x] Denunciar no produce ningún efecto ni avisa al señalado.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
