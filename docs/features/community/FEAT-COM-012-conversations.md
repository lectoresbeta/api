---
id: FEAT-COM-012
title: Ver y gestionar conversaciones de mensajes directos
context: Community
concept: Messaging
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/ui/app-layout-and-navigation.md
  - conversation:2026-09-25
endpoints: [GET /me/conversations, GET /conversations/{conversationId}/messages, PUT /conversations/{conversationId}/read]
events: []
depends_on: [FEAT-COM-011]
updated: 2026-09-25
---

# FEAT-COM-012 — Conversaciones

## Resumen

La sección «Mensajes» del menú lateral: la lista de conversaciones, los mensajes de una, y
marcarlos leídos.

## Reglas de negocio

- `RN-1` Cada quien ve **solo sus** conversaciones. Pedir una ajena responde `404`, no `403`:
  quién habla con quién no es información que se le deba a nadie.
- `RN-2` La lista va **por el último mensaje**, más reciente primero. Es lo que hace que la
  pantalla sirva sin que nadie ordene nada.
- `RN-3` Cada fila lleva **con quién**, el último mensaje y cuántos sin leer.
- `RN-4` Los mensajes de una conversación van **del más reciente hacia atrás**, paginados por
  cursor como el resto del muro.
- `RN-5` Marcar leído afecta a **lo recibido**, nunca a lo enviado: marcar como leído lo
  propio no significa nada.
- `RN-6` Marcar leído es **idempotente** y no mueve la fecha de lo ya leído. La pantalla marca
  al abrir y también con un gesto, así que la segunda vez llega sola.
- `RN-7` Una conversación con alguien **bloqueado no aparece** en la lista, en ninguno de los
  dos sentidos. No se borra: el bloqueo se puede deshacer y el hilo vuelve.
- `RN-8` Una conversación con una cuenta **eliminada** sigue existiendo, y la otra parte se
  enseña como la persona anonimizada que es. Borrarla sería reescribir la mitad de una
  conversación que su dueño sí tuvo.

`RN-1` merece leerse dos veces: responder `403` a una conversación ajena confirmaría que
existe, y con ella que esas dos personas hablan.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Mis conversaciones | `GET /api/v1/me/conversations` | `listMyConversations` |
| Los mensajes de una | `GET /api/v1/conversations/{conversationId}/messages` | `listConversationMessages` |
| Marcarla leída | `PUT /api/v1/conversations/{conversationId}/read` | `markConversationRead` |

`PUT` para marcar leída porque **fija un estado**, y repetirlo no cambia nada.

## Criterios de aceptación

- [x] La lista enseña solo las propias.
- [x] Ordenadas por el último mensaje.
- [x] Cada fila dice con quién, el último mensaje y cuántos sin leer.
- [x] Los mensajes van del más reciente hacia atrás y se paginan.
- [x] Pedir una conversación ajena responde `404`.
- [x] Marcar leída pone a cero lo recibido y no toca lo enviado.
- [x] Marcarla dos veces no cambia nada.
- [x] Una conversación con alguien bloqueado no aparece, y vuelve al desbloquear.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| CM-12 | ¿Se puede archivar o silenciar una conversación sin bloquear? | Es el hueco entre «me molesta este hilo» y «no quiero saber nada de ti» (`S-20`) |
| CM-13 | ¿Hay confirmación de lectura para quien envía? | Hoy `read_at` existe y solo lo usa el contador de quien recibe |

## Estado

**Especificación:** `APPROVED` (2026-09-25).

**Implementación:** `DONE` (2026-09-25).
