---
id: FEAT-CRD-005
title: Abonar +5 al invitador cuando el invitado entrega su primera corrección
context: Credits
concept: Referral
actors: []
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/decisions/0006-credit-system.md
  - docs/features/README.md
  - conversation:2026-09-25
endpoints: []
events:
  - PlatformInvitationConsumed
  - FeedbackSubmitted
depends_on: [FEAT-CRD-006, FEAT-CRD-011, FEAT-USR-018]
updated: 2026-09-25
---

# FEAT-CRD-005 — Abonar +5 al invitador cuando el invitado entrega su primera corrección

## Resumen

Traer a alguien a la plataforma vale cinco créditos. Pero no se cobran al invitar, ni al
registrarse el invitado, ni al activar su cuenta: se cobran **cuando el invitado entrega su
primera corrección**.

## El momento es la ficha entera

El importe se puede discutir. Lo que no se toca sin rehacer el diseño es cuándo se paga
([`decision:0006`](../../decisions/0006-credit-system.md), regla 6):

| Momento | Qué cuesta falsificarlo |
|---|---|
| Al registrarse el invitado | 30 segundos |
| Al activar el correo | Un correo desechable |
| **Al entregar su primera corrección** | **Una corrección real** |

Con el tercero, el fraude sale **peor que el comportamiento honesto**: escribir esa corrección
desde una cuenta falsa da 5 créditos al invitador, mientras que escribirla desde la cuenta real
habría dado más y utilizables. No hace falta detectar nada: no compensa.

## Reglas de negocio

- `RN-1` `PlatformInvitationConsumed` **no abona nada**. Solo apunta el par invitador/invitado
  en una proyección de este contexto.
- `RN-2` La unidad es **la persona invitada, no la invitación**: a cada persona la trae alguien
  una sola vez y para siempre. El primer par apuntado gana; un segundo hecho sobre la misma
  persona no lo reescribe. Lo garantiza la clave primaria de la tabla, no el código.
- `RN-3` Al recibir `FeedbackSubmitted`, si quien corrigió tiene invitador y **ese par no se
  ha pagado**, se abonan 5 créditos al invitador y el par queda marcado. La marca es lo que
  hace que sea «una vez por persona, para siempre»: la segunda corrección del invitado llega
  con un `eventId` distinto y legítimo, así que deduplicar por evento no la cubre.
- `RN-4` El mismo hecho lo escuchan **dos reglas** de este contexto —el cobro de la corrección
  y esta— y cada una deduplica con **su propio nombre de consumidor**. Con el `eventId` a
  secas, la primera en verlo lo cerraría para la otra (`FEAT-CRD-011` `RN-4`).
- `RN-5` **Tope de 10 recompensas por invitador.** No por el gasto —cada alta genera la
  bonificación una sola vez, así que el total nunca supera `5 × usuarios`— sino para que nadie
  construya su saldo reclutando en lugar de corrigiendo.
- `RN-6` El movimiento es un **grifo** (`INVITATION_REWARD`): crea créditos, no los mueve de
  otra cuenta. Cuenta en la invariante de `RN-11` de `FEAT-CRD-006` como lo que es.
- `RN-7` El movimiento del invitador **no cita la corrección**. No la pagó ni la cobró, y
  citarla haría que `FEAT-FBK-010` le contase a quien corrigió unos créditos que no ha ganado.
  Cita al invitado, que es de lo que sí es.
- `RN-8` Una corrección cuyo autor y corrector son la misma persona no paga nada: es un hecho
  malformado, no una recompensa.
- `RN-9` Quien no fue invitado por nadie no tiene fila, y el hecho es entonces una consulta y
  nada más. Es el caso normal y tiene que ser barato.
- `RN-10` Cinco créditos pueden ser justo los que le faltaban al invitador para poder pagar una
  corrección, así que sus capítulos se reevalúan en ese momento (`FEAT-CRD-009`).

## Aislamiento

Nada de esto llega por una llamada. `Credits` **recibe hechos** de `User` y de `Feedback` y
decide por su cuenta qué significan ([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md)):

- ningún contexto sabe cuánto vale una invitación;
- ningún contexto le pide a `Credits` que abone nada;
- `credits_ctx.referral` es una proyección propia, **sin clave ajena** a
  `user_ctx.platform_invitation`: una clave ajena entre esquemas de contextos distintos
  convertiría en obligatorio el acoplamiento que la arquitectura prohíbe.

## Eventos

**Consume** — `PlatformInvitationConsumed`, `FeedbackSubmitted`.

**Publica** — los de cualquier movimiento: `CreditsAdded`, `CreditBalanceChanged`, y
`CreditDebtCleared` si el abono saca al invitador del descubierto.

## Efectos en créditos

**+5 al invitador**, una vez por persona invitada, hasta 10 veces por invitador. Es un grifo.

## Criterios de aceptación

- [x] Registrarse con una invitación no mueve un crédito.
- [x] La primera corrección del invitado abona 5 al invitador.
- [x] La segunda no abona nada.
- [x] El cobro de la corrección y la recompensa ocurren los dos sobre el mismo hecho.
- [x] Quien no fue invitado no dispara nada.
- [x] El movimiento del invitador no cita la corrección.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
