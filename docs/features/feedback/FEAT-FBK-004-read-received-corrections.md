---
id: FEAT-FBK-004
title: Ver las correcciones recibidas
context: Feedback
concept: Correction
actors: [Writer]
spec_status: APPROVED
impl_status: TODO
priority: P0
sources:
  - conversation:2026-09-25 (bloque «que el autor pueda leer lo que compró»)
  - docs/ui/read-chapter.md
endpoints:
  - GET /me/corrections/received
  - GET /corrections/{correctionId}
  - GET /corrections/{correctionId}/chapter-text
events: [CorrectionRead]
depends_on: [FEAT-FBK-003, FEAT-CRD-018, FEAT-WRK-005]
updated: 2026-09-25
---

# FEAT-FBK-004 — Ver las correcciones recibidas

## Resumen

El autor lee las correcciones que ha recibido. Una bandeja con todas las de todas sus obras,
y el detalle de cada una: quién la escribió, sobre qué capítulo, y sus respuestas al
cuestionario.

## Por qué esto es urgente y no una mejora

**Hoy el autor paga por algo que no puede leer.** El ciclo económico está entero menos el
final: se abre la obra a corrección, alguien empieza, entrega, se le cobra al autor y se le
abona al lector — y ahí se acaba. No hay ninguna operación en la API que devuelva el texto de
una corrección entregada.

Tiene una consecuencia que conviene decir en voz alta:
[`FEAT-CRD-018`](../credits/FEAT-CRD-018-negative-balance.md), que está implementada, **retiene
la lectura de lo entregado** cuando el saldo del autor va en negativo. Ese candado no cierra
hoy ninguna puerta, porque la puerta no existe.

## Qué ve el autor

| Dato | Aparece | Por qué |
|---|---|---|
| Quién la escribió | Sí | El autor paga a una persona concreta y puede querer volver a invitarla — o reclamar ([`FEAT-MOD-001`](../moderation/FEAT-MOD-001-submit-claim.md)) |
| Obra y capítulo | Sí | Llegan de varios capítulos a la vez |
| Respuestas al cuestionario | En el detalle | Es el contenido, y es lo que costó |
| Versión del cuestionario | Sí | Puede haberlo reescrito mientras el lector escribía |
| El texto del capítulo que esa persona leyó | Bajo petición | Con el versionado de [`FEAT-WRK-005`](../work/FEAT-WRK-005-edit-work-and-chapter.md), el capítulo puede haber cambiado desde entonces |
| Lo que costó | **No** | Está en el historial de créditos ([`FEAT-CRD-008`](../credits/FEAT-CRD-008-credit-history.md)), que es donde viven los importes |

Una corrección llegada por **enlace público** ([`FEAT-FBK-008`](FEAT-FBK-008-public-link-correction.md))
no tiene cuenta detrás: lleva el nombre que esa persona tecleó, que no es una identidad, y se
presenta como lo que es.

## Los tres estados de una corrección recibida

| Estado | Qué ve el autor | De dónde sale |
|---|---|---|
| `VISIBLE` | Todo | Lo normal |
| `LOCKED` | Que existe, de quién es y de qué capítulo. **No el contenido** | Saldo negativo (`FEAT-CRD-018`) |
| `HIDDEN_BY_AUTHOR` | Todo, marcada como oculta | Él mismo la ocultó (`FEAT-FBK-007`) |

**Quién decide qué se ve es `Feedback`, nunca `Credits`.** `Credits` publica el hecho
económico; ocultar o enseñar el texto es decisión de quien lo posee.

## Reglas de negocio

- `RN-1` Una corrección la lee **su destinatario —el autor de la obra— y quien la escribió**.
  Nadie más, ni siquiera otro lector beta de la misma obra.
- `RN-2` Solo se listan las **entregadas**. Un borrador ajeno es privado de quien lo escribe
  ([`FEAT-FBK-011`](FEAT-FBK-011-save-correction-draft.md)), y el autor no sabe que existe.
- `RN-3` Una corrección `LOCKED` aparece en la lista **sin su contenido**, con el motivo y qué
  hacer para desbloquearla. Ocultarla entera haría creer al autor que nadie le ha corregido.
- `RN-4` El contenido de una corrección **no se modifica nunca**: es inmutable desde que se
  entrega, porque el autor ya ha pagado por ella.
- `RN-5` La lista se ordena **de la más reciente a la más antigua** y se pagina. Una novela en
  corrección con veinte lectores produce mucho.
- `RN-6` El autor puede filtrar por **obra y capítulo**, y por **sin leer**.
- `RN-7` Abrir el detalle **marca la corrección como leída**. Una `LOCKED` no se marca: no se
  ha leído nada.
- `RN-8` El autor puede pedir **el texto del capítulo tal y como lo leyó quien corrigió**. Es
  la razón de ser del versionado: sin él, una corrección de hace un mes habla de un texto que
  ya no existe.
- `RN-9` Ninguna de estas operaciones **mueve créditos**. Leer es gratis; lo que se pagó, se
  pagó al entregarse.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Ni es el autor ni quien la escribió | Se rechaza sin revelar nada | `404` |
| La corrección no existe | Igual | `404` |
| Corrección `LOCKED`, se pide el detalle | Se devuelve la cabecera sin contenido | `200` con `content: null` y `visibility: LOCKED` |
| Se pide el texto del capítulo de una corrección `LOCKED` | Se rechaza | `409` `CORRECTION_LOCKED` |
| La versión del capítulo ya no está archivada | Se devuelve el texto vigente, marcado como tal | `200` con `isCurrentVersion: true` |

El último caso importa: una corrección anterior al versionado no tiene versión que recordar,
y decir «no hay texto» sería peor que decir «este es el de ahora, y puede no ser el que leyó».

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Bandeja de correcciones recibidas | `GET /me/corrections/received` | `listCorrectionsReceived` |
| Detalle de una corrección | `GET /corrections/{correctionId}` | `getCorrection` |
| El capítulo como lo leyó quien corrigió | `GET /corrections/{correctionId}/chapter-text` | `getCorrectedChapterText` |

El texto del capítulo va aparte porque son decenas de miles de palabras que casi nunca hacen
falta: quien abre una corrección quiere leer la corrección.

**La autorización de las tres vive en `Feedback`**, no en `Work`, y es deliberado: aquí se
sabe quién es el autor y quién corrigió, que son exactamente las dos personas que pueden ver
esa versión. `Work` tendría que preguntarlo para responder.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `CorrectionRead` | El autor abre una corrección por primera vez | `correctionId`, `readerId`, `readAt` |

Lo consume `Notification`, que retira el aviso pendiente. **No se le dice a quien corrigió que
su texto ha sido leído**: sería una confirmación de lectura entre dos personas que no han
elegido tener una conversación (`F-14`).

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `CreditBalanceWentNegative` | `Credits` | Las correcciones del autor pasan a `LOCKED` |
| `CreditDebtCleared` | `Credits` | Vuelven a `VISIBLE` |

Las dos ya están implementadas (`FEAT-CRD-018`).

## Contratos que consulta

| Contrato | Contexto | Qué responde |
|---|---|---|
| `ChapterVersions` | `Work` | El título y el texto de un capítulo **en una versión concreta**. Nuevo, y es lo único que `FEAT-WRK-005` necesita exponer hacia fuera |

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `feedback_ctx.correction` | `read_at TIMESTAMPTZ NULL`, `chapter_version INT NULL` (el segundo lo introduce `FEAT-WRK-005`) |
| | Índice `(owner_id, submitted_at DESC)` para la bandeja |

## Criterios de aceptación

- [ ] El autor lista las correcciones recibidas de todas sus obras, la más reciente primero.
- [ ] Filtra por obra, por capítulo y por no leídas.
- [ ] Abre una y lee las respuestas al cuestionario.
- [ ] Quien la escribió también puede abrirla.
- [ ] Un tercero recibe `404`, aunque sea lector beta de la misma obra.
- [ ] Un borrador ajeno no aparece en ninguna lista.
- [ ] Con saldo negativo, la corrección aparece sin contenido y con el motivo.
- [ ] Al reponer saldo, la misma corrección se abre entera.
- [ ] Abrir una corrección la marca como leída; abrir una bloqueada, no.
- [ ] El autor obtiene el texto del capítulo tal y como lo leyó quien corrigió.
- [ ] Una corrección sin versión registrada devuelve el texto vigente, marcado como tal.
- [ ] Leer no mueve ningún crédito.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| F-14 | ¿Sabe quien corrigió que su corrección fue leída? | Hoy no. Es información sobre una persona, y convierte la entrega en una conversación |
| F-15 | ¿Aparece el contador de correcciones sin leer en la cabecera, junto al de notificaciones? | `FEAT-USR-027` ya sirve ese contexto en una sola petición |
| F-16 | ¿Se agrupan las correcciones por capítulo en la bandeja? | Veinte lectores sobre un capítulo son veinte filas iguales |

## Estado

**Especificación:** `APPROVED` (2026-09-25). Resuelve la forma de acceso
(bandeja global con detalle) decidida el 2026-09-25.

**Implementación:** `TODO`.
