---
id: FEAT-RDG-009
title: Aceptar o rechazar una propuesta de writing buddy
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
  - resolveWritingBuddyProposal
  - listMyWritingBuddies
events:
  - WritingBuddyLinked
depends_on: [FEAT-RDG-008]
updated: 2026-09-25
---

# FEAT-RDG-009 — Aceptar o rechazar una propuesta de writing buddy

## Resumen

Quien recibe una propuesta la acepta o la rechaza, y ve sus vínculos vivos en una lista.

Aceptar **no abre ninguna puerta**: el vínculo queda declarado y nada más
([`FEAT-RDG-008`](FEAT-RDG-008-propose-writing-buddy.md), `R-3`).

## Reglas de negocio

- `RN-1` **Solo la resuelve quien la recibió.** Para quien la propuso responde `404`, igual
  que para alguien ajeno: quién le ha propuesto qué a quién no se le debe a nadie que no sea
  parte. Es lo que hace que `proposedBy` viva en su propia columna — el par se guarda
  ordenado, y eso es justo lo que el orden tira.
- `RN-2` **No existir, no ser tuya y estar ya resuelta responden igual.**
- `RN-3` **Aceptar anuncia; rechazar, no.** Es la misma decisión que con
  `AuthorUnsubscribed`: avisar a alguien de que le han dicho que no convertiría una respuesta
  discreta en un desaire con acuse de recibo. Quien propuso lo ve en su lista, que es donde
  fue a mirar.
- `RN-4` La lista trae **los vivos y solo los vivos**: propuestos y aceptados, en una sola
  lista porque son una sola pantalla. Los resueltos no salen — un vínculo rechazado hace dos
  meses no es una fila que nadie quiera volver a ver, y guardarlo a la vista sería un
  histórico de desaires.
- `RN-5` Cada fila dice **si la propuesta la hice yo** (`proposedByMe`), que es lo que decide
  si la pantalla enseña «Aceptar / Rechazar» o «Esperando respuesta». Sin él, el cliente
  tendría que comparar identificadores.
- `RN-6` La otra parte llega **resuelta** —nombre, `@usuario`, avatar— y sin filtrar por
  privacidad: quien tiene un vínculo con alguien ya sabe quién es, y filtrarlo haría
  desaparecer de la lista a quien cerrara su perfil, con él la posibilidad de responderle. Es
  el mismo caso que la lista de bloqueados (`FEAT-COM-034`).
- `RN-7` Una cuenta eliminada deja `other` a nulo y **la fila sigue**: el vínculo existió.
- `RN-8` **No mueve créditos.**

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Aceptar o rechazar | `PUT /api/v1/writing-buddy-proposals/{linkId}/resolution` | `resolveWritingBuddyProposal` |
| Mis vínculos vivos | `GET /api/v1/me/writing-buddies` | `listMyWritingBuddies` |

Una ruta y no dos para las dos decisiones, igual que al resolver una invitación de lector
beta: son la misma operación con distinto desenlace, y dos rutas harían creer que son dos
cosas.

La lista va bajo `/me/` porque no existe la versión de otra persona.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `WritingBuddyLinked` | Al **aceptar**, nunca al rechazar | `linkId`, `proposerId`, `partnerId`, `linkedAt` |

**Hoy no lo consume nadie.** `Community` lo tiene apuntado para cuando cuente vínculos, y se
publica igual: el hecho ocurrió, y tenerlo desde el principio es lo que permite que ese día
haya de qué tirar.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Ninguno nuevo.

## Criterios de aceptación

- [x] Quien la recibió acepta, y el vínculo queda vivo en las listas de los dos.
- [x] Quien la propuso no puede aceptarla: recibe `404`.
- [x] Resolverla dos veces tampoco.
- [x] Rechazar la cierra, no anuncia nada y deja las dos listas vacías.
- [x] Tras un rechazo se puede volver a proponer.
- [x] Cada fila dice si la propuesta fue mía.

## Preguntas abiertas

Ninguna. Deshacer un vínculo ya aceptado no tiene ficha todavía: el estado `ENDED` existe en
el modelo y ninguna funcionalidad lo pide.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
