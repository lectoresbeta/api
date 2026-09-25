---
id: FEAT-RDG-008
title: Proponer a un usuario ser writing buddy
context: Reading
concept: WritingBuddy
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/bounded-contexts/reading.md
  - conversation:2026-09-25
endpoints:
  - proposeWritingBuddy
events:
  - WritingBuddyProposed
depends_on: [FEAT-USR-011]
updated: 2026-09-25
---

# FEAT-RDG-008 — Proponer a un usuario ser writing buddy

## Resumen

Una persona le propone a otra un vínculo recíproco de *writing buddy*. La otra decide en
[`FEAT-RDG-009`](FEAT-RDG-009-resolve-writing-buddy-proposal.md).

## `R-3`, resuelta: el vínculo no habilita nada

La pregunta abierta era qué habilita exactamente el vínculo, con la advertencia de que
«podría conceder accesos automáticos». **No concede ninguno.**

No da acceso de lector beta a las obras del otro, no salta la modalidad de acceso que cada
autor eligió y no salta la clasificación por edad. Es un vínculo **declarado**: se ve en la
lista, se anuncia a quien lo recibe, y ahí acaba.

La alternativa —acceso mutuo automático al aceptar— era cómoda y abría una puerta que no pasa
por `AccessRequest` ni por `AccessInvitation`. Dos personas podrían concederse entre ellas lo
que el modelo de acceso entero está hecho para gobernar, y de paso saltarse `ADULTS_ONLY`, que
no es una preferencia sino una obligación. Quien quiera leer al otro **lo invita**
(`FEAT-RDG-004`): cuesta un clic y pasa por donde tiene que pasar.

Esto deja el vínculo como lo que el material de partida describe —una relación entre dos
personas que se acompañan escribiendo— sin convertirlo en un atajo de autorización.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| Usuario | Proponer | El destinatario existe, está activado y admite propuestas |

## Reglas de negocio

- `RN-1` **Nadie se propone a sí mismo**: no hay vínculo de una persona.
- `RN-2` El destinatario tiene que **existir y estar activado**. Que no cumpla una de las dos
  responde `404` sin distinguir cuál: distinguirlo confirmaría quién está en la plataforma.
- `RN-3` El destinatario tiene que **admitir propuestas** (`FEAT-USR-011`). El error **no dice
  si es por el ajuste o por un bloqueo**: distinguirlos permitiría averiguar los ajustes de
  otro probando a proponerle cosas.
- `RN-4` **Un vínculo vivo por par** —propuesto o aceptado—, en cualquiera de las dos
  direcciones. El par se guarda ordenado, así que `(A,B)` y `(B,A)` son la misma fila, y un
  índice único parcial sobre los estados vivos lo garantiza de verdad.
- `RN-5` Tras un rechazo **se puede volver a proponer**: el rechazado no es un estado vivo. No
  hay enfriamiento, igual que con las solicitudes de lectura (`R-9` sigue abierta para las
  dos).
- `RN-6` La propuesta **no concede ni siquiera el vínculo**: es una oferta.
- `RN-7` **No mueve créditos.**

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Proponer | `POST /api/v1/users/{userId}/writing-buddy-proposals` | `proposeWritingBuddy` |

Cuelga de la persona porque se propone **a una persona**, igual que un mensaje directo.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `WritingBuddyProposed` | Al proponer | `linkId`, `proposerId`, `partnerId`, `proposedAt` |

Lo consume `Notification`, que entrega `WRITING_BUDDY_PROPOSED`. Ese tipo llevaba en el
catálogo desde `FEAT-NOT-001` con su frase escrita y **sin nadie que lo disparara**; con esto
queda completa `FEAT-NOT-005`: solicitudes, invitaciones y propuestas.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

`reading_ctx.writing_buddy_link`, que ya existía con su índice único parcial sobre los estados
vivos. No hace falta migración.

## Criterios de aceptación

- [x] Se propone, y la propuesta aparece pendiente en las listas de los dos.
- [x] **Un vínculo aceptado no concede acceso a ninguna obra del otro.**
- [x] Solo hay uno vivo por par, en cualquiera de las dos direcciones.
- [x] Quien cerró las propuestas no las recibe, y un bloqueo da el mismo error.
- [x] Nadie se propone a sí mismo.
- [x] La propuesta avisa a quien la recibe y no a quien la hace.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| `R-9` | ¿Hace falta enfriamiento entre un rechazo y la siguiente propuesta? | Hoy se puede volver a proponer sin límite, igual que con las solicitudes |

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`. **Deshacer un vínculo aceptado** no tiene ficha: el estado `ENDED`
existe en el modelo y ninguna funcionalidad lo pide todavía.
