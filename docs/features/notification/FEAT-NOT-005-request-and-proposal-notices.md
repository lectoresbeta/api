---
id: FEAT-NOT-005
title: Avisar de solicitudes, invitaciones y propuestas
context: Notification
concept: Delivery
actors: []
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - docs/features/README.md
  - conversation:2026-09-25
endpoints: []
events: []
depends_on: [FEAT-NOT-001, FEAT-RDG-002, FEAT-RDG-004, FEAT-RDG-008]
updated: 2026-09-25
---

# FEAT-NOT-005 — Avisar de solicitudes, invitaciones y propuestas

## Resumen

Los avisos de las tres cosas que **una persona le pide a otra** y que quedan esperando una
respuesta.

Es la familia que más daño hace si falta, porque no se trata de enterarse tarde: una petición
sin aviso se queda pendiente para siempre. Quien la mandó espera algo que nunca llega, y quien
la recibió tiene una decisión que no sabe que tiene.

## Reglas de negocio

- `RN-1` Cinco hechos, cinco avisos:

  | Hecho | Aviso | Va a |
  |---|---|---|
  | `AccessRequested` | `ACCESS_REQUESTED` | El autor de la obra |
  | `AccessRequestRejected` | `ACCESS_REQUEST_RESOLVED` | Quien solicitó |
  | `BetaReaderAccessGranted` | `ACCESS_REQUEST_RESOLVED` | Quien solicitó |
  | `BetaReaderInvited` | `BETA_READER_INVITATION` | El invitado |
  | `WritingBuddyProposed` | `WRITING_BUDDY_PROPOSED` | Quien recibe la propuesta |

- `RN-2` **Conceder y rechazar son el mismo aviso con distinto desenlace.** Quien solicitó
  quiere saber que hay respuesta; qué respuesta es, lo lee dentro. Dos tipos distintos
  obligarían a la pantalla a tratarlos por separado para decir lo mismo.
- `RN-3` **Nadie se avisa a sí mismo**: aceptar una invitación no avisa a quien la aceptó, y
  un acceso que nace de empezar a corregir no avisa a quien empezó.
- `RN-4` **Ningún aviso lleva el mensaje** que acompaña a una solicitud o a una invitación. Es
  prosa, puede ser larga, y el aviso solo tiene que decir que hay algo esperando.
- `RN-5` **Del rechazo de una propuesta de writing buddy no se avisa** (`FEAT-RDG-009`
  `RN-3`). Avisar de un «no» convertiría una respuesta discreta en un desaire con acuse de
  recibo; quien propuso lo ve en su lista.
- `RN-6` Estos avisos **no son operativos**: se pueden silenciar. Quien lo haga renuncia a
  enterarse, no a recibirlos — eso es `FEAT-USR-011`, que es otra cosa.

## Recepción y aviso son dos cosas

Conviene leerlo junto a [`FEAT-USR-011`](../user/FEAT-USR-011-proposal-reception.md), que es
lo contrario de esta ficha: silenciar el aviso deja la invitación creada, esperando respuesta
en una lista que su destinatario ha decidido no mirar; cerrar la recepción impide que exista.

## Eventos

**Consume**

`AccessRequested`, `AccessRequestRejected`, `BetaReaderAccessGranted`, `BetaReaderInvited` y
`WritingBuddyProposed`.

**Publica** — ninguno.

## Efectos en créditos

Ninguno.

## Criterios de aceptación

- [x] Solicitar avisa al autor y no a quien solicita.
- [x] Resolver una solicitud avisa a quien solicitó, conceda o rechace.
- [x] Invitar avisa al invitado; aceptar la invitación no avisa a quien aceptó.
- [x] Proponer writing buddy avisa a quien lo recibe y no a quien propone.
- [x] Rechazar una propuesta no avisa a nadie.
- [x] Ningún aviso contiene el mensaje que acompañaba a la petición.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`. Los cuatro primeros consumidores existen desde `FEAT-NOT-001`; el
de `WritingBuddyProposed` llegó con `FEAT-RDG-008`, que es lo que faltaba para cerrarla.
