---
id: FEAT-FBK-010
title: Mis correcciones — lo que he corregido
context: Feedback
concept: Correction
actors: [BetaReader]
spec_status: REVIEW
impl_status: TODO
priority: P2
sources:
  - conversation:2026-09-25 (bloque «que el autor pueda leer lo que compró»)
  - docs/ui/my-profile.md (pestaña «Mis correcciones»)
endpoints:
  - GET /me/corrections
events: []
depends_on: [FEAT-FBK-003, FEAT-FBK-004]
updated: 2026-09-25
---

# FEAT-FBK-010 — Mis correcciones

## Resumen

La lista de lo que uno ha corregido: qué obra, qué capítulo, cuándo, qué escribió, cuánto
ganó y qué hizo el autor con ello.

Es la simétrica de [«Mis reclamaciones»](../moderation/FEAT-MOD-010-my-claims.md) y existe por
lo mismo: **corregir no puede ser escribir en un buzón**.

## Qué ve quien corrigió

Todo lo suyo, resolviendo `F-9`:

| Dato | Por qué |
|---|---|
| Obra y capítulo | Para reconocer de qué habla |
| Su propio texto | Lo escribió él. Ocultárselo no protege a nadie |
| Fecha de entrega | Y si sigue en borrador |
| **Lo que ganó** | Es trabajo remunerado, y el saldo sin desglose no explica de dónde sale |
| Si el autor **valoró**, **contestó** o **propinó** | Es lo único que devuelve la plataforma a cambio del trabajo |

Y **no** ve si el autor la ha leído. Es información sobre otra persona, y convertiría una
entrega en una conversación que nadie ha aceptado tener (`F-14`).

## De dónde sale «lo que ganó»

De una **proyección propia de este contexto**, alimentada por `CreditsAdded`.

No hay otra vía: `Credits` **no publica ningún contrato**, ni siquiera de consulta
([`AGENTS.md`](../../../AGENTS.md), [`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md)),
y preguntarle en caliente sería justo la dependencia que esa decisión prohíbe. Es el mismo
camino que ya sigue `User` para pintar el saldo en el menú
([`FEAT-USR-027`](../user/FEAT-USR-027-session-context.md)).

Tiene su precio y conviene escribirlo: **la cifra puede ir un instante por detrás**. Una
corrección recién entregada puede aparecer sin importe durante unos segundos. Se muestra como
«pendiente», no como cero: un cero es una afirmación falsa.

## Reglas de negocio

- `RN-1` Cada uno ve **solo las suyas**. No hay forma de listar las correcciones de otra
  persona: el **contador** es público, la **lista** no
  ([`FEAT-USR-014`](../user/FEAT-USR-014-view-public-profile.md) `U-17`).
- `RN-2` Se listan las **entregadas y los borradores propios**, distinguidos. Un borrador es
  trabajo empezado, y quien lo dejó a medias necesita encontrarlo.
- `RN-3` Se ordena de la más reciente a la más antigua, y se pagina.
- `RN-4` Se puede filtrar por **obra** y por **estado** (borrador, entregada).
- `RN-5` La valoración del autor se muestra **tal cual**, incluida la negativa. Ocultarla
  dejaría la valoración sin función.
- `RN-6` Si la obra se archivó, se bloqueó o el capítulo se ocultó, **la corrección sigue
  apareciendo**, marcada. El trabajo se hizo y se cobró.
- `RN-7` Una corrección llegada por **enlace público** no aparece aquí: no hay cuenta a la que
  pertenezca.
- `RN-8` Esta lista **no mueve créditos** ni cambia nada.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Sin sesión | Se rechaza | `401` |
| Todavía no ha corregido nada | Lista vacía | `200` |
| El importe aún no ha llegado | `earnedCredits: null` | `200` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Mis correcciones | `GET /me/corrections` | `listMyCorrections` |

El detalle de cada una se lee con `getCorrection`
([`FEAT-FBK-004`](FEAT-FBK-004-read-received-corrections.md)), que ya autoriza a las dos
partes. No hace falta una segunda operación que devuelva lo mismo.

## Eventos

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `CreditsAdded` | `Credits` | Proyecta lo ganado por esa corrección, si el movimiento la cita |

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `feedback_ctx.correction_earning` | **Nueva.** `correction_id`, `credits`, `recorded_at`. Una fila por corrección pagada |
| `feedback_ctx.correction` | Índice `(reader_id, submitted_at DESC)` |

La proyección es una tabla aparte y no una columna en `correction` a propósito: la corrección
es de este contexto y el importe es un eco de otro. Mezclarlos invitaría a creer que `Feedback`
sabe de dinero.

## Criterios de aceptación

- [ ] Cada uno ve sus correcciones, la más reciente primero.
- [ ] Sus borradores aparecen distinguidos de lo entregado.
- [ ] Filtra por obra y por estado.
- [ ] Ve lo que ganó por cada corrección entregada.
- [ ] Mientras el importe no ha llegado, se muestra pendiente y no cero.
- [ ] Ve si el autor la valoró, la contestó o la propinó.
- [ ] No ve si el autor la ha leído.
- [ ] Una corrección de una obra archivada o bloqueada sigue apareciendo, marcada.
- [ ] No hay forma de listar las correcciones de otra persona.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| F-22 | ¿Se muestra el total ganado corrigiendo? | Es la cifra que da sentido a la lista, y también la que invita a corregir por cantidad |
| F-23 | ¿Caducan los borradores abandonados? (`R-8`) | Un borrador eterno ocupa el hueco de corrección de ese capítulo |

## Estado

**Especificación:** `REVIEW` — completa, pendiente de validación. Resuelve `F-9`.

**Implementación:** `TODO`.
