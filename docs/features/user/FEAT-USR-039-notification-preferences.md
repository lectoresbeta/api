---
id: FEAT-USR-039
title: Preferencias de notificación por canal
context: User
concept: Preferences
actors: [User]
spec_status: APPROVED
impl_status: PARTIAL
priority: P2
sources:
  - conversation:2026-09-22 (pestaña «Notificaciones» de Configuración)
  - docs/ui/settings.md
endpoints:
  - GET /me/notification-preferences
  - PUT /me/notification-preferences
events: [NotificationPreferencesChanged]
depends_on: [FEAT-NOT-008]
updated: 2026-09-25
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
el correo ni en la plataforma»*.

**Producto lo confirma: el interruptor no afecta a las notificaciones operativas.** El texto
de la pantalla, por tanto, no describe lo que ocurre y conviene corregirlo (`S-38`).

| Correo | ¿Se puede silenciar? | Por qué |
|---|---|---|
| Activación de la cuenta | **No** | Sin él no hay cuenta usable |
| Restablecer contraseña | **No** | Lo acaba de pedir el usuario |
| Cambio de correo o de contraseña | **No** | Es la defensa contra el robo de cuenta (`FEAT-USR-040` `RN-3`) |
| Comunicaciones legales obligatorias | **No** | Cambios de condiciones, avisos formales |
| Comentarios, seguidores, mensajes | Sí | Son actividad |
| Actualizaciones, consejos de uso | Sí | Son divulgación |

La distinción es **transaccional frente a notificación**, y tiene que estar en el modelo, no
en una lista de excepciones repartida por el código. Si no, el interruptor general dejará a
alguien sin poder recuperar su cuenta, y el aviso de seguridad que más importa será justo el
que no se envíe.

La forma práctica: cada tipo de mensaje nace marcado como **operativo** o **notificación**, y
solo los segundos consultan preferencias. Un tipo nuevo tiene que declarar cuál es; ninguno
puede quedar sin clasificar.

## El catálogo de avisos

**Decidido** (`S-11`, `S-12`): la maqueta estaba incompleta. Estos son todos los tipos, con
los canales en los que existe cada uno.

### Actividad sobre lo que escribo

| Aviso | Correo | Plataforma |
|---|---|---|
| **Corrección recibida** | Sí | Sí |
| **Corrección bloqueada por saldo** | Sí | Sí |
| Comentario en un capítulo mío | Sí | Sí |
| Comentario en mi publicación | Sí | Sí |
| Respuesta a un comentario mío | No | Sí |
| Mención | Sí | Sí |

**Corrección recibida y comentario son avisos distintos** (`S-11`). Van en casillas separadas
porque no se parecen: una corrección es trabajo que el autor ha pagado; un comentario es una
reacción social. Meterlos juntos haría que silenciar lo segundo ocultase lo primero.

### Mi actividad como corrector

| Aviso | Correo | Plataforma |
|---|---|---|
| **Propina recibida** | Sí | Sí |
| Mi corrección ha sido valorada | No | Sí |
| Mi corrección ha sido desbloqueada por el autor | Sí | Sí |

### Relación con otros

| Aviso | Correo | Plataforma |
|---|---|---|
| Seguidor nuevo | Sí | Sí |
| Mensaje nuevo | No | Sí |
| Solicitud de lector beta | Sí | Sí |
| Respuesta a mi solicitud | Sí | Sí |
| Invitación de lector beta | Sí | Sí |
| Propuesta de *writing buddy* | Sí | Sí |

### Créditos

| Aviso | Correo | Plataforma |
|---|---|---|
| Saldo en negativo | Sí | Sí |
| Deuda saldada | No | Sí |

### Divulgación

| Aviso | Correo | Plataforma |
|---|---|---|
| Actualizaciones de la plataforma | Sí | No |
| Consejos de uso | Sí | No |

### Y los que no se configuran

Activación de la cuenta, restablecimiento de contraseña, cambio de correo o de contraseña,
sanción impuesta, bloqueo de una obra, aviso a moderadores y comunicaciones legales. Son
**operativos** y quedan fuera de estas preferencias, incluido el interruptor general.

**Ningún tipo puede quedar sin clasificar** como operativo o notificación. Es la regla que
impide que un tipo nuevo se cuele sin decidir si se puede silenciar.

## Reglas de negocio

- `RN-1` Un aviso se entrega por un canal solo si el usuario **no lo ha desactivado** en ese
  canal.
- `RN-2` El interruptor general **suspende** todas las notificaciones; **no borra ni
  sobrescribe** las preferencias individuales. Al desactivarlo, el usuario recupera su
  configuración tal como la dejó.
- `RN-3` Los **mensajes operativos** —activación, restablecimiento de contraseña, avisos de
  seguridad y comunicaciones legales— quedan fuera de estas preferencias, **incluido el
  interruptor general**. No son notificaciones.
- `RN-4` Una preferencia ausente toma su **valor por defecto**, que es explícito por tipo. Por
  defecto **todo está activado** salvo «actualizaciones de la plataforma».
- `RN-4b` **Corrección recibida y comentario son tipos distintos** (`S-11`).
- `RN-4c` Un tipo nuevo **debe declararse operativo o notificación** (`RN-3`). No puede quedar
  sin clasificar.
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

- [x] Un aviso desactivado en un canal no se entrega por ese canal. **La mitad del canal de
      correo no se puede comprobar todavía**: no hay quien mande correos de aviso
      (`FEAT-NOT-002`), así que esas preferencias se guardan y se sirven, y nadie las aplica.
- [x] Activar y desactivar el interruptor general **devuelve** las preferencias anteriores.
- [x] Con el interruptor general activo siguen llegando activación, restablecimiento de
      contraseña y avisos de seguridad.
- [x] Silenciar los mensajes no impide recibirlos.
- [x] Un tipo de aviso nuevo no requiere migración ni cambio en el cliente.
- [x] Las preferencias se comprueban al entregar, no al publicar el evento.
- [x] Corrección recibida y comentario de capítulo se configuran por separado.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| S-38 | ¿Se corrige el texto del interruptor? | Promete silenciar todo y no es lo que hace. **Es texto de pantalla**: el backend ya hace lo correcto |
| S-25 | ¿Hay resumen periódico por correo en vez de aviso por evento? | Cambiaría el modelo de entrega |
| S-26 | ¿Los avisos de créditos se pueden silenciar? | Afectan al saldo del usuario |
| S-19 | ¿Dónde se configura **no recibir** propuestas de LB (`FEAT-USR-011`)? | No es una preferencia de aviso |

Resueltas: `S-9` (**sí, la asimetría es deliberada**, y el modelo es una lista de pares y no
una matriz) y `S-10` (**suspende**, como proponía `RN-2`).

## Añadido después

`REACTIVATION_OFFER` entra en el catálogo con `FEAT-CRD-019`, y **solo por correo**. Es el
único tipo cuyo interruptor hace algo más que callar un aviso: apagarlo renuncia al mecanismo
entero, porque un descubierto sin aviso no es un gancho sino deuda a espaldas de alguien. La
decisión viaja a `Credits` como hecho propio (`ReactivationOfferChoiceChanged`), no como una
lectura de estas preferencias: `Credits` no puede preguntar por este contexto.

## Estado

**Especificación:** `APPROVED` (2026-09-24). `S-11` y `S-12` resueltas: el catálogo de avisos está
completo y corrección y comentario son tipos separados.

**Implementación:** `PARTIAL` (2026-09-25).

Tres cosas que la ficha no preveía y que la implementación obligó a decidir:

- **el catálogo configurable vive en `User` y la clasificación en `Notification`.** Son dos
  preguntas distintas —qué se puede configurar y qué se entrega— y cada contexto responde la
  suya. Comparten el vocabulario a propósito: los nombres coinciden, que es lo que permite
  cruzarlas sin una tabla de traducción que alguien tendría que mantener;
- **`RN-4c` se cumple por análisis estático.** La clasificación operativo/notificación es un
  `match` **sin `default`**: con un `default` a «notificación», un tipo nuevo se comportaría en
  silencio como silenciable, que es el valor equivocado para cualquier cosa que tenga que ver
  con la seguridad de una cuenta. Sin él, PHPStan se queja en cuanto alguien añade un caso;
- **un tipo desconocido se permite.** Si `Notification` entrega un aviso que no está en el
  catálogo configurable, llega. Es lo que hace que añadir un aviso no exija tocar dos
  contextos a la vez, y el riesgo es el correcto: un aviso nuevo se recibe hasta que alguien
  decida que se puede apagar, y no al revés.

**Falta**, y por eso es `PARTIAL`: **el canal de correo no lo aplica nadie**. Las preferencias
de `EMAIL` se guardan, se sirven y se validan desde ya —la pantalla las ofrece—, pero quien
manda correos de aviso es `FEAT-NOT-002`,
que no existe. El día que exista, lo único que tendrá que hacer es preguntar al mismo contrato
con `EMAIL` en vez de `PLATFORM`.

También falta el **resumen periódico** (`S-25`) y decidir si los avisos de créditos se pueden
silenciar (`S-26`): hoy se pueden, que es lo que dice el catálogo.
