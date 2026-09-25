---
id: FEAT-FBK-005
title: Contestar a una corrección recibida
context: Feedback
concept: Reply
actors: [Writer]
spec_status: APPROVED
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-25 (bloque «que el autor pueda leer lo que compró»)
endpoints:
  - PUT /corrections/{correctionId}/reply
  - DELETE /corrections/{correctionId}/reply
events: [FeedbackReplied]
depends_on: [FEAT-FBK-004]
updated: 2026-09-25
---

# FEAT-FBK-005 — Contestar a una corrección recibida

## Resumen

El autor responde por escrito a una corrección. Una respuesta por corrección, visible para
quien la escribió.

## Por qué existe

Una corrección es trabajo que alguien hizo por encargo y por el que cobró. Sin respuesta, la
plataforma es un buzón: se escribe, se cobra y no se sabe si sirvió de algo.

Y hay algo más concreto: **es lo que evita que la valoración sea el único canal**. Sin poder
decir «esto me ha servido, esto otro creo que has leído otra cosa», al autor solo le queda el
pulgar, y un pulgar hacia abajo sin explicación es una penalización sin juicio.

## Lo que no es

**No es una conversación.** Una respuesta, y se acabó: quien corrigió no replica.

Poner a autor y corrector a discutir sobre un texto es crear el conflicto que la moderación
existe para resolver, y quien discrepe de una corrección tiene dos salidas mejores: no
valorarla, o reclamarla si es fraudulenta
([`FEAT-MOD-001`](../moderation/FEAT-MOD-001-submit-claim.md)).

El único hilo de ida y vuelta de la plataforma es el del moderador con cada parte, por
separado ([`FEAT-MOD-009`](../moderation/FEAT-MOD-009-moderator-conversation.md)).

## Reglas de negocio

- `RN-1` Solo **el autor de la obra** contesta, y solo a correcciones recibidas por él.
- `RN-2` **Una respuesta por corrección.** Volver a enviar la sustituye, y queda constancia de
  que se editó.
- `RN-3` La respuesta la ve **quien escribió la corrección**, y nadie más.
- `RN-4` No se puede contestar a una corrección **`LOCKED`**: no se ha leído.
- `RN-5` No se puede contestar a una corrección llegada por **enlace público**: no hay cuenta
  detrás a la que dirigirse, ni forma de avisarla.
- `RN-6` La respuesta **se puede borrar**. Lo que se dijo deja de estar; no se conserva un
  historial visible de lo dicho y retirado.
- `RN-7` Contestar **no mueve créditos ni obliga a nada más**. No es requisito para valorar,
  ni para propinar, ni al revés.
- `RN-8` Longitud máxima razonable, y **texto plano**. Una respuesta no es un capítulo.
- `RN-9` Contestar **no cambia la corrección**, que sigue siendo inmutable.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| No es el autor de la obra | Se rechaza sin revelar nada | `404` |
| La corrección está `LOCKED` | Se rechaza | `409` `CORRECTION_LOCKED` |
| La corrección vino por enlace público | Se rechaza | `409` `CORRECTION_HAS_NO_AUTHOR` |
| Texto vacío | Se rechaza | `422` |
| Borrar una respuesta que no existe | Se acepta y no ocurre nada | `204` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Escribir o sustituir la respuesta | `PUT /corrections/{correctionId}/reply` | `replyToCorrection` |
| Retirarla | `DELETE /corrections/{correctionId}/reply` | `deleteCorrectionReply` |

`PUT` porque es **una** respuesta y no una lista: enviarla dos veces deja una, que es lo que
el autor espera al corregir una errata en lo que acaba de escribir.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `FeedbackReplied` | Se escribe por primera vez | `correctionId`, `chapterId`, `workId`, `readerId`, `repliedAt`. **Sin el texto** |

Lo consume `Notification`. Sustituirla **no vuelve a avisar**: recibir tres avisos porque
alguien corrige sus erratas es ruido.

El texto no viaja, como ningún contenido en esta plataforma: una cola que persiste, reintenta
y aparca mensajes no es sitio para lo que dos personas se dicen.

## Modelo de datos afectado

`feedback_ctx.correction_reply` **ya existe** con `correctionId`, `authorId`, `body`,
`createdAt` y `editedAt`. Hace falta su índice único por `correction_id` —una por corrección,
garantizado por la base de datos y no por una comprobación en código— y el caso de uso.

## Criterios de aceptación

- [ ] El autor contesta a una corrección y quien la escribió la ve.
- [ ] Contestar dos veces deja una sola respuesta, marcada como editada.
- [ ] La sustitución no genera un segundo aviso.
- [ ] Un tercero no ve la respuesta.
- [ ] No se puede contestar a una corrección bloqueada por descubierto.
- [ ] No se puede contestar a una corrección llegada por enlace público.
- [ ] El autor puede retirar su respuesta.
- [ ] Contestar no mueve créditos.
- [ ] El texto de la respuesta no aparece en ningún evento.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| F-17 | ¿Puede quien corrigió responder a la respuesta? | Hoy no, a propósito. Abrirlo convierte esto en un hilo y exige moderarlo |
| F-18 | ¿Se avisa a quien corrigió si el autor retira su respuesta? | Hoy no. Recibir «te han quitado la respuesta» es peor que no saberlo |

## Estado

**Especificación:** `APPROVED` (2026-09-25).

**Implementación:** `TODO`. La entidad `CorrectionReply` ya está en el modelo desde
`FEAT-FBK-003`; falta todo lo demás.
