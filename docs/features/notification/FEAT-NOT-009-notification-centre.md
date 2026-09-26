---
id: FEAT-NOT-009
title: Centro de notificaciones y contador de no leídas
context: Notification
concept: Delivery
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - docs/bounded-contexts/notification.md
  - conversation:2026-09-24
endpoints:
  - GET /me/notifications
  - GET /me/notifications/unread-count
  - PUT /me/notifications/{notificationId}/read
  - PUT /me/notifications/read
events: []
depends_on: [FEAT-NOT-001]
updated: 2026-09-24
---

# FEAT-NOT-009 — Centro de notificaciones y contador de no leídas

## Resumen

Leer los avisos y marcarlos como leídos. Es la otra mitad de
[`FEAT-NOT-001`](FEAT-NOT-001-in-app-notifications.md): sin esta, los avisos se guardan y
nadie los ve.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Leer **sus** avisos y marcarlos | Sesión iniciada |

Solo los propios, y no hay forma de pedir los de otra persona: el identificador del
destinatario no es un parámetro de ninguna operación, es quien tiene la sesión.

No hace falta la cuenta activada. Marcar un aviso como leído es escritura, sí, pero de un dato
que solo afecta a quien lo hace — y la bandeja es de los primeros sitios donde alguien sin
activar necesita entrar, porque ahí está el aviso de que active.

## Reglas de negocio

- `RN-1` La bandeja se pagina **por cursor**, de lo más reciente a lo más antiguo.
- `RN-2` Se puede pedir **solo lo no leído**, que es lo que la pantalla enseña por defecto.
- `RN-3` El contador de no leídas es **una operación aparte**. La campana de la cabecera la
  necesita sin cargar la lista, y cargarla entera para pintar un número sería pagar veinte
  filas por un `2`.
- `RN-4` Marcar como leído es **idempotente**: marcar lo ya leído no cambia la fecha ni falla.
- `RN-5` Existe **marcar todo como leído**. Un contador que no se puede vaciar es un contador
  que acaba ignorado.
- `RN-6` Marcar un aviso ajeno responde como si no existiera. Nunca `403`: confirmaría que ese
  aviso está ahí.
- `RN-7` No hay borrado de avisos. Cuánto se conservan es `N-5`, y mientras no se decida, se
  conservan.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Mi bandeja | `GET /me/notifications` | `listMyNotifications` |
| Cuántas sin leer | `GET /me/notifications/unread-count` | `getUnreadNotificationCount` |
| Marcar una | `PUT /me/notifications/{notificationId}/read` | `markNotificationRead` |
| Marcar todas | `PUT /me/notifications/read` | `markAllNotificationsRead` |

`PUT` y no `POST`: marcar como leído es **fijar un estado**, y repetirlo deja el mundo igual.

Cada aviso viaja con su `kind`, su `payload` y si está leído. **El texto no lo compone el
servidor**: la frase la construye el cliente a partir del tipo y del payload, que es lo que
permite traducirla sin desplegar el backend y cambiarla sin migrar nada.

## El contador cuenta todo lo no leído

Sin tope y sin «99+». El recorte es de presentación, y un backend que devuelva `99` cuando hay
1.480 obliga a la pantalla a mentir dos veces: una al enseñar el número y otra cuando alguien
abre la bandeja y cuenta.

## Criterios de aceptación

- [x] La bandeja devuelve los avisos propios, del más reciente al más antiguo.
- [x] Se puede pedir solo lo no leído.
- [x] La bandeja pagina por cursor sin repetir ni saltarse avisos.
- [x] El contador dice cuántos hay sin leer.
- [x] Marcar uno como leído lo saca del contador.
- [x] Marcar uno ya leído no falla ni cambia la fecha.
- [x] Marcar todos vacía el contador.
- [x] Nadie puede leer ni marcar los avisos de otra persona.
- [x] Sin sesión no hay bandeja.
- [x] Una cuenta sin activar puede leer su bandeja.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| N-5 | ¿Cuánto se conservan los avisos leídos? | Retención. Mientras no se decida, se conservan |
| N-7 | ¿Marcar como leído al abrir el aviso, o solo con un gesto explícito? | Es de interfaz: el backend ofrece las dos |
| N-8 | ¿Hace falta un «no leído» manual —volver a marcar sin leer—? | Nadie lo ha pedido |

## Estado

**Especificación:** `APPROVED` (2026-09-24).

**Implementación:** `DONE` (2026-09-24). Las cuatro operaciones.

Una consecuencia que la ficha pedía sin decir cómo: «no hace falta la cuenta activada» choca
con la política de [`FEAT-USR-025`](../user/FEAT-USR-025-block-writes-until-activation.md),
que niega **toda** escritura por defecto. Así que `markNotificationRead` y
`markAllNotificationsRead` se añaden a su lista de exentas, con su motivo: **el aviso que pide
activar la cuenta está en esa bandeja**, y lo que escriben es una fecha de lectura sobre una
fila de quien llama. Es una tercera familia en esa lista, y queda escrita como tal.
