---
id: FEAT-USR-039
title: Preferencias de notificación por canal
context: User
concept: Preferences
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P2
sources:
  - conversation:2026-09-22 (pestaña «Notificaciones» de Configuración)
  - docs/ui/settings.md
endpoints:
  - GET /me/notification-preferences
  - PUT /me/notification-preferences
events: [NotificationPreferencesChanged]
depends_on: [FEAT-NOT-008]
updated: 2026-09-22
---

# FEAT-USR-039 — Preferencias de notificación por canal

## Resumen

El usuario decide qué avisos recibe y por dónde. Hay **dos canales** —correo y plataforma— y
un **interruptor general** que los apaga todos.

Sustituye y amplía a `FEAT-USR-012`, que solo contemplaba el correo.

## Los dos canales no ofrecen lo mismo

| Preferencia | Correo | En la plataforma |
|---|---|---|
| Actualizaciones de la plataforma | Sí | **No** |
| Consejos de uso | Sí | **No** |
| Comentarios en mis textos | Sí | Sí |
| Seguidores nuevos | Sí | Sí |
| Mensajes nuevos | **No** | Sí |

La asimetría es razonable: nadie quiere un correo por cada mensaje directo, y un consejo de
uso no es un aviso de actividad.

Lo importante para el modelo es que **no hay una matriz completa**. Una preferencia puede
existir en un canal y no en el otro, así que el modelo es una lista de pares
`(tipo, canal)` admitidos, no el producto cartesiano de tipos por canales.

## Hay correos que no son notificaciones

«Desactivar todas las notificaciones» dice *«No recibirás ningún tipo de notificación, ni en
el correo ni en la plataforma»*. **No puede aplicarse literalmente.**

| Correo | ¿Se puede silenciar? | Por qué |
|---|---|---|
| Activación de la cuenta | **No** | Sin él no hay cuenta usable |
| Restablecer contraseña | **No** | Lo acaba de pedir el usuario |
| Cambio de correo o de contraseña | **No** | Es la defensa contra el robo de cuenta (`FEAT-USR-040` `RN-3`) |
| Comunicaciones legales obligatorias | **No** | Cambios de condiciones, avisos formales |
| Comentarios, seguidores, mensajes | Sí | Son actividad |
| Actualizaciones, consejos de uso | Sí | Son divulgación |

La distinción es **transaccional frente a notificación**, y tiene que estar en el modelo. Si
no, el interruptor general dejará a alguien sin poder recuperar su cuenta, y el aviso de
seguridad que más importa será justo el que no se envíe.

## Reglas de negocio

- `RN-1` Un aviso se entrega por un canal solo si el usuario **no lo ha desactivado** en ese
  canal.
- `RN-2` El interruptor general **suspende** todas las notificaciones; **no borra ni
  sobrescribe** las preferencias individuales. Al desactivarlo, el usuario recupera su
  configuración tal como la dejó.
- `RN-3` Los **correos transaccionales** quedan fuera de estas preferencias, incluido el
  interruptor general.
- `RN-4` Una preferencia ausente toma su **valor por defecto**, que es explícito por tipo.
- `RN-5` Las preferencias **no afectan a la autorización**: silenciar un aviso no impide que
  el hecho ocurra. Quien silencia los mensajes sigue recibiéndolos, solo no se le avisa. No
  querer enterarse y no querer recibir son cosas distintas (`S-19`).
- `RN-6` `Notification` **comprueba las preferencias en el momento de entregar**, no al
  publicar el evento. El emisor no las conoce.

`RN-2` parece un detalle de interfaz y no lo es: si el interruptor sobrescribe, quien lo
active y lo desactive volverá con todo apagado y no sabrá por qué dejó de recibir avisos.

`RN-6` mantiene la separación de contextos. Que `Feedback` tenga que saber si el autor quiere
correos sería acoplar el emisor a una preferencia que no es suya.

## «Comentarios en mis textos» ya no es una sola cosa

Desde que **comentar un capítulo no es corregirlo** (`R-2`), esa etiqueta cubre dos hechos
que no se parecen:

| | Comentario de capítulo | Corrección recibida |
|---|---|---|
| Qué es | Reacción social | El cuestionario respondido |
| Créditos | Ninguno | **El autor la ha pagado** |
| Importancia | Baja | Es el producto |

Meterlos en la misma casilla significa que **quien silencie los comentarios dejará de
enterarse de las correcciones que ha comprado**. Deben ser dos tipos distintos (`S-11`).

## Faltan avisos que el catálogo de eventos ya prevé

Solicitudes e invitaciones de lector beta, propuestas de *writing buddy*, menciones,
respuestas a un comentario, valoración de una corrección y **movimientos de créditos**: nada
de eso es configurable en la pantalla.

Caben dos lecturas: la lista de la maqueta es parcial, o esos avisos son obligatorios. Lo
segundo es defendible para los créditos —afectan al saldo— y difícil de sostener para el
resto (`S-12`).

Conviene que el modelo admita tipos nuevos **sin migración**: una fila por preferencia
explícita y un valor por defecto para lo no configurado envejece mejor que una tabla con una
columna por tipo.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Consultar | `GET /me/notification-preferences` | `getMyNotificationPreferences` |
| Modificar | `PUT /me/notification-preferences` | `updateMyNotificationPreferences` |

La respuesta incluye **los tipos disponibles por canal**, no solo los valores: el cliente no
debe llevar la lista codificada, o cada tipo nuevo exigirá desplegar el frontal.

## Eventos

**Publica**

| Evento | Cuándo | Payload |
|---|---|---|
| `NotificationPreferencesChanged` | Al guardar | `userId`, preferencias modificadas |

**Consume**

Ninguno. Es `Notification` quien lee estas preferencias al entregar (`RN-6`).

## Modelo de datos afectado

`notification_preference`: `user_id`, `type`, `channel`, `enabled`.
`user_notification_settings`: `user_id`, `all_muted`.

`all_muted` es **un campo aparte** y no un valor de las filas anteriores. Es lo que hace
posible `RN-2`.

## Criterios de aceptación

- [ ] Un aviso desactivado en un canal no se entrega por ese canal y sí por el otro, si está
      activo ahí.
- [ ] Activar y desactivar el interruptor general **devuelve** las preferencias anteriores.
- [ ] Con el interruptor general activo siguen llegando activación, restablecimiento de
      contraseña y avisos de seguridad.
- [ ] Silenciar los mensajes no impide recibirlos.
- [ ] Un tipo de aviso nuevo no requiere migración ni cambio en el cliente.
- [ ] Las preferencias se comprueban al entregar, no al publicar el evento.
- [ ] Corrección recibida y comentario de capítulo se configuran por separado.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **S-11** | ¿Se separan corrección y comentario? | Silenciar comentarios ocultaría lo que el autor ha pagado |
| **S-12** | ¿Por qué faltan los demás avisos del catálogo? | ¿Lista parcial o avisos obligatorios? |
| S-9 | ¿La asimetría entre canales es deliberada? | El modelo no debe asumir matriz completa |
| S-10 | El interruptor general, ¿suspende o sobrescribe? | `RN-2` propone suspender |
| S-25 | ¿Hay resumen periódico por correo en vez de aviso por evento? | Cambiaría el modelo de entrega |
| S-26 | ¿Los avisos de créditos se pueden silenciar? | Afectan al saldo del usuario |
| S-19 | ¿Dónde se configura **no recibir** propuestas de LB (`FEAT-USR-011`)? | No es una preferencia de aviso |

## Estado

**Especificación:** `DRAFT`. `S-11` y `S-12` deben resolverse antes de `APPROVED`: definen
qué tipos existen.

**Implementación:** `TODO`.
