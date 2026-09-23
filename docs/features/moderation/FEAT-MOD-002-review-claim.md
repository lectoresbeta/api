---
id: FEAT-MOD-002
title: Revisar y resolver una reclamación
context: Moderation
concept: Review
actors: [Moderator]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-23 (backoffice de administración)
  - docs/bounded-contexts/moderation.md
endpoints:
  - GET /admin/claims
  - POST /admin/claims/{claimId}/review
events: [ClaimUpheld, ClaimRejected]
depends_on: [FEAT-MOD-001, FEAT-MOD-004]
updated: 2026-09-23
---

# FEAT-MOD-002 — Revisar y resolver una reclamación

## Resumen

Un moderador revisa una reclamación pendiente y decide: **estimarla o desestimarla**. Su
decisión es lo que desencadena todos los efectos.

## Efectos de estimar una reclamación

| Tipo | Efecto |
|---|---|
| **Corrección fraudulenta** | Se **revierten los créditos**: se devuelven al autor y se retiran al corrector. Se notifica por correo **a ambos** |
| **Corrección con contenido inapropiado** | Lo anterior **más sanción** a su autor |
| **Obra inapropiada** | La obra queda **deshabilitada permanentemente**, visible solo para su autor y marcada como bloqueada |
| **Usuario abusivo** | Sanción según gravedad |

Desestimarla no produce ningún efecto sobre el contenido, pero **sí se registra**: reclamar en
falso de forma reiterada tiene consecuencias (`MOD-2`).

## Reglas de negocio

- `RN-1` **Nadie modera un asunto en el que es parte**: ni su obra, ni su corrección, ni una
  reclamación que presentó o que le señala.
- `RN-2` Toda decisión lleva **motivación escrita**, también las que desestiman.
- `RN-3` El estado **no retrocede**. Un error se corrige con una acción administrativa nueva y
  motivada, no reabriendo el expediente.
- `RN-4` `Moderation` **no aplica los efectos**: publica la decisión y cada contexto la
  interpreta en su modelo.
- `RN-5` La decisión queda en el **registro de auditoría** con quién, cuándo y por qué.
- `RN-6` El reclamado **no conoce la identidad del moderador** ni la del reclamante.
- `RN-7` Solo un moderador puede revisar, y solo reclamaciones **no asignadas a otro**.
- `RN-8` Dos moderadores no resuelven la misma reclamación: la revisión la bloquea.

`RN-1` parece obvio y es justo el que se salta cuando hay pocos moderadores y mucha cola. Es
también el camino más corto para perder la confianza en el sistema entero.

`RN-4` es lo que mantiene el contexto aislado. La tentación es que el backoffice escriba
directamente en las tablas de `Credits` y `Work` —es más fácil y más rápido— y el resultado es
un contexto que lo sabe todo sobre todos.

## La reversión de créditos

No se edita ni se borra el movimiento original: los movimientos son **inmutables**
([`decision:0006`](../../decisions/0006-credit-system.md)). Una reversión son **dos apuntes
nuevos**:

| Apunte | Quién | Motivo |
|---|---|---|
| Abono | El autor de la obra | `CLAIM_UPHELD` |
| Cargo | El corrector | `CLAIM_UPHELD` |

Mismo importe, así que la masa de créditos no cambia.

**El corrector puede quedar en negativo**, porque quizá ya gastó esos créditos. No hace falta
ninguna regla nueva: es exactamente lo que
[`FEAT-CRD-018`](../credits/FEAT-CRD-018-negative-balance.md) ya describe, y la salida es la
de siempre —corregir para saldarlo—.

Dos detalles que conviene no olvidar:

- **La propina, si la hubo, no se revierte.** El autor la dio voluntariamente después de leer.
  Reclamar una corrección que se propinó es contradictorio y debería levantar una ceja al
  moderador.
- **El importe revertido es el que se cobró**, no el precio vigente del capítulo, que puede
  haber cambiado.

## El criterio, que es lo difícil

Estimar una reclamación de corrección fraudulenta **le quita créditos a alguien que trabajó**.
Por eso el criterio tiene que separar dos cosas que se parecen desde fuera:

| No es fraude | Sí lo es |
|---|---|
| Una crítica dura, negativa o que el autor no comparte | Una respuesta que no dice nada sobre el texto |
| Una lectura equivocada del texto | Texto copiado, generado o repetido de otra corrección |
| Una corrección breve pero concreta | Relleno para llegar al mínimo de palabras |
| Un desacuerdo de gusto literario | Contenido ofensivo o dirigido a la persona |

**Si el sistema no distingue estas columnas, los correctores aprenderán a escribir elogios**, y
eso vacía el producto de su razón de ser. El criterio escrito no es burocracia: es lo que
protege al corrector honesto.

## Flujo principal

1. El moderador abre la cola de reclamaciones pendientes.
2. Toma una: pasa a `UNDER_REVIEW` y queda asignada a él.
3. Ve el contenido reclamado, el motivo y el texto del reclamante.
4. Decide, con motivación escrita.
5. Se publica `ClaimUpheld` o `ClaimRejected`.
6. Cada contexto aplica lo suyo; `Notification` avisa a las partes.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| El moderador es parte implicada | Se rechaza y **no se le muestra** en la cola | `403` |
| Otro moderador la tiene en revisión | Se rechaza | `409` |
| Decisión sin motivación | Se rechaza | `422` |
| La reclamación ya está resuelta | Se rechaza | `409` |
| El objeto reclamado ya no existe | Se puede archivar, no estimar | `409` |
| No es moderador | Se rechaza | `403` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Cola de reclamaciones | `GET /admin/claims` | `listClaims` |
| Tomar y resolver | `POST /admin/claims/{claimId}/review` | `reviewClaim` |

Bajo `/admin`, que exige rol de moderador. La cola **excluye automáticamente** las
reclamaciones en las que el moderador es parte: no basta con rechazar la acción, no debe
verlas.

## Eventos

**Publica**

| Evento | Cuándo | Payload | Consumidores |
|---|---|---|---|
| `ClaimUpheld` | Se estima | `claimId`, `type`, `targetType`, `targetId`, `subjectId` | **`Credits`**, **`Work`**, **`User`**, `Notification` |
| `ClaimRejected` | Se desestima | `claimId`, `reporterId` | `Notification` |

`ClaimUpheld` dice **qué se ha estimado y sobre qué**, nunca los efectos: no lleva «devuelve 6
créditos» ni «bloquea la obra». Cada contexto decide qué significa en su modelo.

**Tampoco lleva la motivación del moderador.** Es material interno que puede ser duro y va
dirigido al expediente, no a las partes.

## Criterios de aceptación

- [ ] Un moderador no ve en su cola reclamaciones en las que es parte.
- [ ] Dos moderadores no pueden resolver la misma reclamación.
- [ ] Una decisión sin motivación se rechaza.
- [ ] Estimar una reclamación de corrección produce **dos apuntes nuevos**, no edita ninguno.
- [ ] El corrector puede quedar en saldo negativo tras una reversión.
- [ ] La propina no se revierte.
- [ ] El importe revertido es el que se cobró, no el precio actual.
- [ ] `ClaimUpheld` no contiene importes ni instrucciones.
- [ ] El estado de una reclamación nunca retrocede.
- [ ] Toda decisión queda en el registro de auditoría.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **MOD-1** | ¿Qué catálogo de sanciones existe? | Sin él no hay sanción que imponer |
| **MOD-2** | ¿Qué consecuencia tiene acumular reclamaciones desestimadas? | Es la defensa contra reclamar para no pagar |
| MOD-4 | ¿Puede el reclamado alegar antes de la decisión? | Cambia el ciclo de vida entero y lo hace mucho más justo |
| MOD-11 | ¿Hay decisiones que exijan dos moderadores? | El bloqueo permanente de una obra es grave |
| MOD-12 | ¿Se revierte también la reputación ganada por esa corrección? | Si no, el fraude conserva su premio social |

`MOD-4` merece pensarse: un sistema que quita créditos sin que el afectado pueda decir nada es
difícil de defender. Y el coste de permitir alegación es un estado más y algo de espera.

## Estado

**Especificación:** `DRAFT`. `MOD-1` y `MOD-2` bloquean `APPROVED`.

**Implementación:** `TODO`.
