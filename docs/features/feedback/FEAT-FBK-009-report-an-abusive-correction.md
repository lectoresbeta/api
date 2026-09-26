---
id: FEAT-FBK-009
title: Denunciar una corrección abusiva
context: Feedback
concept: Correction
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/features/moderation/FEAT-MOD-001-submit-claim.md
  - conversation:2026-09-25
endpoints:
  - submitClaim
events: []
depends_on: [FEAT-MOD-001, FEAT-MOD-002, FEAT-CRD-018]
updated: 2026-09-25
---

# FEAT-FBK-009 — Denunciar una corrección abusiva

## Resumen

El botón de denunciar de una corrección recibida. Desemboca en
[`FEAT-MOD-001`](../moderation/FEAT-MOD-001-submit-claim.md) con `targetType: CORRECTION`.

## No hay una tubería nueva

No la hay y no debe haberla: las denuncias se presentan, se encolan y se resuelven por un solo
camino, y lo que cambia según desde dónde se denuncie es **qué se comprueba antes de
registrar**. Aquí las comprobaciones son las más estrictas de todas, y por una razón concreta.

**Denunciar una corrección devuelve créditos si se estima** (`FEAT-MOD-002`), así que el botón
es también una forma de no pagar: «la denuncio como que no aporta valor y recupero lo que me
costó». Si el sistema no distinguiera una crítica dura de una corrección fraudulenta, los
correctores aprenderían a escribir elogios — que es exactamente lo contrario del producto.

## Reglas de negocio

- `RN-1` El autor de la obra denuncia una corrección que ha recibido, con un motivo del
  catálogo y un texto libre que es donde está la información real.
- `RN-2` **Solo el autor de la obra corregida.** Una corrección ajena responde lo mismo que
  una inexistente: que exista ya es información sobre una obra que no es suya.
- `RN-3` **Solo una corrección que ya se ha podido leer.** Una retenida por descubierto
  (`FEAT-CRD-018`) no se puede denunciar: sin esta puerta, quien no puede pagar denunciaría lo
  que no ha visto para no pagarlo nunca.
- `RN-4` Cuenta para el **cupo mensual** y para el **bloqueo acumulativo** de `FEAT-MOD-001`.
  Es lo que hace que desestimar una denuncia tenga consecuencias.
- `RN-5` Una por persona y objeto: repetirla devuelve la que ya existe.
- `RN-6` **No produce ningún efecto.** No oculta la corrección, no congela los créditos y no
  avisa a quien corrigió. Registra que alguien pide que se mire algo.

## Contrato de API

Ninguno nuevo: es `POST /api/v1/claims` (`submitClaim`) con `targetType: CORRECTION`.

| Caso | Respuesta |
|---|---|
| No es tu obra, o no existe | `404 CLAIM_TARGET_NOT_CLAIMABLE` |
| Retenida por descubierto | `409 CLAIM_CORRECTION_NOT_READ` |
| Cupo mensual agotado | `429 CLAIM_MONTHLY_LIMIT_SPENT` |

## Eventos

Ninguno propio. Lo que publica hechos es resolverla (`FEAT-MOD-002`).

## Efectos en créditos

**Ninguno al denunciar.** La reversión ocurre al estimarla, y la hace `Credits` al recibir
`ClaimUpheld` (`FEAT-MOD-002`). Esa separación es lo que impide que denunciar sea cobrar.

## Modelo de datos afectado

Ninguno.

## Criterios de aceptación

- [x] El autor de la obra puede denunciar la corrección que ha recibido.
- [x] Ni quien la escribió ni un extraño pueden.
- [x] Una corrección retenida no se puede denunciar.
- [x] Repetirla devuelve la que ya existe.
- [x] Denunciar no produce ningún efecto sobre la corrección.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
