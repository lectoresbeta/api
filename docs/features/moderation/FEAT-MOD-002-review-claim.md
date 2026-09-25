---
id: FEAT-MOD-002
title: Revisar y resolver una reclamación
context: Moderation
concept: Review
actors: [Moderator]
spec_status: APPROVED
impl_status: PARTIAL
priority: P1
sources:
  - conversation:2026-09-23 (backoffice de administración)
  - docs/bounded-contexts/moderation.md
endpoints:
  - GET /admin/claims
  - POST /admin/claims/{claimId}/review
events: [ClaimUpheld, ClaimRejected]
depends_on: [FEAT-MOD-001, FEAT-MOD-004]
updated: 2026-09-25
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
- `RN-9` El moderador puede **hablar con cada parte por separado** antes de decidir
  ([`FEAT-MOD-009`](FEAT-MOD-009-moderator-conversation.md)). Las partes no hablan entre sí ni
  ven lo que dice la otra.
- `RN-10` Una reclamación **sobre un moderador no la resuelve él mismo** (`RN-1`). Si no queda
  ningún moderador elegible, la resuelve el **administrador**.
- `RN-11` Estimar una reclamación de corrección fraudulenta **revierte también la reputación**
  que esa corrección dio a su autor (`MOD-12`). Si no, el fraude conservaría su premio social.
- `RN-12` Una reclamación por **contenido sensible sobre un texto correctamente etiquetado se
  desestima** ([`FEAT-WRK-017`](../work/FEAT-WRK-017-content-rating.md)). El autor avisó y el
  lector eligió verlo.

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

## Antes de decidir: hablar con las partes

El moderador puede abrir conversación con cada parte por separado
([`FEAT-MOD-009`](FEAT-MOD-009-moderator-conversation.md)). Es lo que convierte esto en un
procedimiento y no en un veredicto:

> Un sistema que le quita créditos a alguien que trabajó, sin que pueda decir nada, es difícil
> de defender.

**Las partes no hablan entre sí.** Cada una tiene un hilo privado con el moderador y no ve lo
que dice la otra: es una estrella, no una sala. Poner a denunciante y denunciado a discutir
sería crear el conflicto que la moderación existe para evitar.

## El criterio, que es lo difícil

Estimar una reclamación de corrección fraudulenta **le quita créditos a alguien que trabajó**.
Por eso el criterio tiene que separar dos cosas que se parecen desde fuera:

| No es fraude | Sí lo es |
|---|---|
| Una crítica dura, negativa o que el autor no comparte | Una respuesta que no dice nada sobre el texto |
| Una lectura equivocada del texto | Texto copiado, generado o repetido de otra corrección |
| Una corrección breve pero concreta | Relleno para llegar al mínimo de palabras |
| Un desacuerdo de gusto literario | Contenido ofensivo o dirigido a la persona |

Y un caso que se resuelve solo: **una reclamación por contenido sensible sobre un texto que
estaba correctamente etiquetado se desestima**. El autor avisó de lo que había y el lector
eligió entrar ([`FEAT-WRK-017`](../work/FEAT-WRK-017-content-rating.md)). Lo que sí es
reclamable es un texto **mal etiquetado**.

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
| No queda moderador elegible | Pasa al administrador | — |
| Contenido sensible correctamente etiquetado | Se desestima | — |
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

- [x] Un moderador no ve en su cola reclamaciones en las que es parte.
- [x] Dos moderadores no pueden resolver la misma reclamación.
- [x] Una decisión sin motivación se rechaza.
- [x] Estimar una reclamación de corrección produce **dos apuntes nuevos**, no edita ninguno.
- [x] El corrector puede quedar en saldo negativo tras una reversión.
- [x] La propina no se revierte.
- [ ] La reputación ganada por la corrección **sí** se revierte.
- [x] Una reclamación sobre un moderador no puede ser resuelta por él mismo.
- [x] Sin moderadores elegibles, la reclamación llega al administrador.
- [ ] Una reclamación por contenido sensible sobre texto etiquetado se desestima.
- [x] El importe revertido es el que se cobró, no el precio actual.
- [x] `ClaimUpheld` no contiene importes ni instrucciones.
- [x] El estado de una reclamación nunca retrocede.
- [x] Toda decisión queda en el registro de auditoría.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| MOD-11 | ¿Hay decisiones que exijan dos moderadores? | El bloqueo de una obra entera es grave |
| MOD-23 | ¿Cuánto espera el moderador una respuesta antes de decidir sin ella? | Una parte que no contesta no puede bloquear el expediente para siempre |
| MOD-24 | ¿Puede el moderador cambiar el tipo de una reclamación mal clasificada? | Un usuario puede denunciar «ofensivo» algo que es plagio |

Resueltas: `MOD-1` (catálogo de sanciones,
[`FEAT-MOD-006`](FEAT-MOD-006-sanctions.md)), `MOD-2` (3 al mes con bloqueo acumulativo),
`MOD-4` (**sí hay comunicación**, vía [`FEAT-MOD-009`](FEAT-MOD-009-moderator-conversation.md)),
`MOD-12` (**sí se revierte** la reputación) y `MOD-15` (si no queda moderador elegible,
decide el administrador).

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `PARTIAL` (2026-09-25). Lo que funciona de punta a punta: la cola, la
decisión con motivación, el registro de auditoría, la restricción de quien reclama en falso,
y los dos efectos que la ficha describe en detalle —`Work` bloquea lo reclamado y `Credits`
revierte lo que se cobró—, cada uno en su contexto, a partir de un `ClaimUpheld` que no lleva
ni importes ni instrucciones.

**Tomar y resolver son una sola operación, y `UNDER_REVIEW` no sobrevive a la llamada.** La
ficha describe el flujo en dos pasos y el contrato de API en uno solo (`POST
/admin/claims/{claimId}/review`), así que lo implementado sigue al contrato. Lo que `RN-8`
pide —que dos moderadores no resuelvan la misma— lo da el **bloqueo de la fila** mientras se
decide: el segundo encuentra un expediente cerrado y recibe `CLAIM_ALREADY_RESOLVED`. Una
asignación que se puede pedir y no usar dejaría la cola llena de expedientes retenidos por
quien ya se fue a otra cosa, y haría falta un plazo para soltarlos.

Sobre `MOD-15` y el administrador: no hace falta ningún mecanismo de escalado porque un
`ADMIN` **es** moderador a efectos de permisos, así que ve y resuelve cualquier expediente en
el que no sea parte. La regla se cumple sin código propio; el día que haya un flujo de
asignación, volverá a hacer falta decidirlo.

**Lo que no está, dicho sin rodeos:**

- **las sanciones** (`FEAT-MOD-006`). Estimar una reclamación de usuario abusivo o de
  contenido inapropiado registra la decisión y la publica, pero nadie aplica todavía una
  sanción: no existe el modelo que la impone;
- **la reversión de reputación** (`RN-11`). No hay modelo de reputación en el backend, así
  que no hay nada que revertir. Cuando lo haya, es un consumidor más de `ClaimUpheld`;
- **la conversación con las partes** (`RN-9`, [`FEAT-MOD-009`](FEAT-MOD-009-moderator-conversation.md));
- **el aviso por correo a las dos partes** de una reversión. Quien reclamó ve el estado en
  «Mis reclamaciones» ([`FEAT-MOD-010`](FEAT-MOD-010-my-claims.md)); quien corrigió se entera
  por su saldo, que es poco;
- **archivar** una reclamación cuyo objeto ya no existe. Hoy se puede desestimar, que no es
  lo mismo: desestimar cuenta como reclamación en falso para quien la presentó.

El criterio de la tabla «no es fraude / sí lo es» **no es código y no puede serlo**: es lo que
el moderador lee antes de decidir. Que una reclamación por contenido sensible sobre un texto
bien etiquetado se desestime (`RN-12`) es una instrucción para la persona, no una regla que el
backend pueda aplicar solo.

`MOD-11`, `MOD-23` y `MOD-24` siguen abiertas.
