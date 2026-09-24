# Endpoints — `Notification`

> Convenciones transversales en [`../conventions/`](../conventions/). Esquemas en `openapi/`.
>
> Estado: **el centro de notificaciones implementado**. Todo lo demás de este contexto entra
> por eventos y no por HTTP.

## Resumen

| Método y ruta | `operationId` | Propósito | Funcionalidad | Estado |
|---|---|---|---|---|
| `GET /api/v1/me/notifications` | `listMyNotifications` | Mi bandeja | FEAT-NOT-009 | **Implementado** |
| `GET /api/v1/me/notifications/unread-count` | `getUnreadNotificationCount` | Cuántas sin leer | FEAT-NOT-009 | **Implementado** |
| `PUT /api/v1/me/notifications/read` | `markAllNotificationsRead` | Marcar todas | FEAT-NOT-009 | **Implementado** |
| `PUT /api/v1/me/notifications/{notificationId}/read` | `markNotificationRead` | Marcar una | FEAT-NOT-009 | **Implementado** |
| `GET /me/notification-preferences` | `getNotificationPreferences` | Qué recibo y por dónde | FEAT-NOT-003 | PENDING |
| `PUT /me/notification-preferences` | `updateNotificationPreferences` | Cambiarlo | FEAT-NOT-003 | PENDING |

**Este contexto es puramente reactivo.** Los avisos los crea al recibir hechos de otros
contextos ([`FEAT-NOT-001`](../../features/notification/FEAT-NOT-001-in-app-notifications.md)),
nunca por una llamada HTTP. No existe —ni debe existir— un endpoint para crear una
notificación: eso sería que otro contexto decidiera a quién avisar y de qué.

---

## Las cuatro cuelgan de `/me`, y eso es la autorización

Ninguna operación nombra a un destinatario. **El destinatario sale de la sesión**, así que no
hay parámetro que apunte a otra persona y no hay comprobación de pertenencia que se pueda
olvidar. Es la misma forma que `GET /credits/balance`, y por la misma razón.

La única que recibe un identificador es `markNotificationRead`, y ahí el identificador es del
**aviso**, no de su dueño: el handler comprueba que sea suyo y responde `404` si no lo es.

## `GET /api/v1/me/notifications`

**`operationId`:** `listMyNotifications` · **Funcionalidad:** [`FEAT-NOT-009`](../../features/notification/FEAT-NOT-009-notification-centre.md)

Paginación por cursor ([`../conventions/pagination.md`](../conventions/pagination.md)), de lo
más reciente a lo más antiguo. `unreadOnly=true` deja solo lo pendiente, que es lo que la
pantalla enseña por defecto; el endpoint, en cambio, devuelve la bandeja entera si no se le
pide otra cosa: el filtro es decisión de quien pinta.

**El texto no lo compone el servidor.** Cada aviso viaja con su `kind` y su `payload`, y la
frase la construye el cliente. Eso es lo que permite traducirla sin desplegar el backend y
cambiar la redacción sin migrar nada de lo ya guardado. Las claves de cada `payload` están en
`openapi/paths/notifications.yaml`.

El `payload` es una **instantánea** de cuando el hecho ocurrió (`FEAT-NOT-001` `RN-5`): lleva
el nombre y el título de entonces aunque hoy sean otros, porque un aviso describe algo que
pasó. Y **nunca lleva contenido** —ni texto de obra, ni de corrección, ni de mensaje— porque
esa regla es del aviso y no del canal.

Un `kind` desconocido debe ignorarse en el cliente, no romperlo: la lista crece.

## `GET /api/v1/me/notifications/unread-count`

**`operationId`:** `getUnreadNotificationCount`

Existe aparte de la bandeja porque la campana de la cabecera se pinta en **todas** las
pantallas: cargar veinte filas para enseñar un `2` sería pagar la lista entera en cada
navegación.

Cuenta todo lo no leído, **sin tope y sin «99+»**. El recorte es de presentación, y un backend
que devuelva `99` cuando hay 1.480 obliga a la pantalla a mentir dos veces.

## `PUT /api/v1/me/notifications/{notificationId}/read` y `PUT /api/v1/me/notifications/read`

**`operationId`:** `markNotificationRead` y `markAllNotificationsRead`

`PUT` y no `POST` porque marcar como leído **fija un estado**: repetirlo deja el mundo igual.
La pantalla lo repite —marca al abrir y además ofrece el gesto—, así que la segunda llamada
llega sola: no falla y no mueve la fecha.

Las dos responden `204`, también cuando no había nada que marcar. Fallar con el contador ya a
cero sería poner una pega a lo único que lo vacía, y un contador que no se puede vaciar acaba
ignorado.

Un aviso ajeno responde `404` con `NOTIFICATION_NOT_FOUND`, **nunca `403`**: distinguirlos
permitiría, probando identificadores, ir descubriendo lo que le ocurre a otra persona.

**Son las dos únicas escrituras exentas de exigir cuenta activada**
([`FEAT-USR-025`](../../features/user/FEAT-USR-025-block-writes-until-activation.md)), y la
razón es la misma que exime al reenvío del correo de activación: **el aviso que pide activar
la cuenta está en esa bandeja**. Lo que escriben es una fecha de lectura sobre una fila de
quien llama, así que nada sale de la cuenta.
