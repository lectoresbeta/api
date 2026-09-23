---
id: FEAT-MOD-010
title: Mis reclamaciones — sección del usuario
context: Moderation
concept: Claim
actors: [User]
spec_status: APPROVED
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-23 (sección de reclamaciones en la interfaz del usuario)
endpoints:
  - GET /me/claims
  - GET /me/claims/{claimId}
events: []
depends_on: [FEAT-MOD-001]
updated: 2026-09-24
---

# FEAT-MOD-010 — Mis reclamaciones

## Resumen

Sección en la interfaz del usuario donde ve **el estado de las reclamaciones que le afectan** y
donde habla con el moderador.

Sin ella, la moderación sería una caja negra: reclamas, no pasa nada visible, y algún día
llega un correo. Eso no genera confianza en un mecanismo cuya única baza **es** la confianza.

## Qué se ve

| Como reclamante | Como reclamado |
|---|---|
| Qué reclamé y cuándo | Que hay una reclamación **sobre algo mío** |
| En qué estado está | En qué estado está |
| La decisión y su efecto | La decisión y su efecto |
| Mi hilo con el moderador | Mi hilo con el moderador |

**No se ve**: quién reclamó, quién modera, ni el hilo de la otra parte.

## Cuándo se entera el reclamado

Aquí hay una decisión con consecuencia, y conviene verla:

[`FEAT-MOD-001`](FEAT-MOD-001-submit-claim.md) `RN-1` dice que presentar una reclamación **no
avisa al reclamado**. Si apareciese de inmediato en su sección, esa regla quedaría vacía.

**El reclamado ve la reclamación cuando el moderador abre conversación con él, o cuando se
resuelve.** Antes, no existe para él.

Es lo que mantiene el equilibrio: el denunciado no sufre por el mero hecho de ser denunciado,
pero tampoco recibe una decisión de la nada.

## Reglas de negocio

- `RN-1` El reclamante ve su reclamación **desde que la presenta**.
- `RN-2` El reclamado la ve **desde que el moderador le escribe o desde que se resuelve**.
- `RN-3` **Nunca se revela la identidad** del reclamante, del reclamado ni del moderador.
- `RN-4` Se muestra la **decisión y su efecto**, no la motivación interna del moderador.
- `RN-5` Si el usuario está bloqueado para reclamar, la sección dice **hasta cuándo**
  ([`FEAT-MOD-001`](FEAT-MOD-001-submit-claim.md) `RN-6b`).
- `RN-6` Un **invitado sin cuenta no tiene sección** ni puede seguir su reclamación (`MOD-10`).

`RN-4` es la línea fina: el usuario tiene derecho a saber **qué** se decidió y qué le supone,
no a leer las notas internas con las que el moderador razonó. Mezclarlas haría que los
moderadores escribieran para la galería en vez de para el expediente.

## Dónde vive

No está en Configuración: **es actividad, no ajustes.** Su sitio natural es junto a las
notificaciones o en el menú del avatar, y debe tener **indicador de pendiente** cuando el
moderador espera respuesta (`MOD-31`).

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Listar las mías | `GET /me/claims` | `listMyClaims` |
| Detalle | `GET /me/claims/{claimId}` | `getMyClaim` |

«Las mías» incluye **las que presenté y aquellas en las que soy parte reclamada**, estas
últimas solo desde que son visibles para mí (`RN-2`).

La respuesta **no contiene identificadores de las otras partes**. Ni siquiera opacos: un
identificador estable permite correlacionar entre expedientes.

## Criterios de aceptación

- [ ] El reclamante ve su reclamación desde el primer momento.
- [ ] El reclamado no la ve hasta que el moderador le escribe o se resuelve.
- [ ] No aparece ninguna identidad de las otras partes, ni siquiera un identificador.
- [ ] Se muestra la decisión y su efecto, nunca la motivación interna.
- [ ] Quien está bloqueado para reclamar ve hasta cuándo.
- [ ] Hay indicador visible cuando el moderador espera respuesta.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| MOD-31 | ¿Dónde vive exactamente la sección y cómo se señala lo pendiente? | Sin diseño |
| MOD-32 | ¿Se conservan las reclamaciones resueltas de forma indefinida en la sección? | Un historial largo de denuncias recibidas es incómodo de ver |
| MOD-33 | ¿Ve el autor qué obra suya está reclamada antes de resolverse? | Saberlo le permitiría retirarla y vaciar el expediente |

`MOD-33` tiene truco: si el autor ve la reclamación y retira la obra antes de la decisión, se
queda sin sanción y sin registro. Conviene decidir si retirar el contenido cierra el
expediente o no lo cierra.

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `TODO`.
