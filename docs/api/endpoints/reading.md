# Endpoints — `Reading`

Quién puede leer qué, y cómo llegó a poder hacerlo. Ficha del contexto:
[`reading.md`](../../bounded-contexts/reading.md).

**Los tres caminos de acceso funcionan.** Las dos mitades de cada uno se implementaron juntas:
una solicitud que nadie puede contestar es peor que no poder pedirla.

## Operaciones

| Método y ruta | `operationId` | Propósito | Ficha | Estado |
|---|---|---|---|---|
| `POST /api/v1/works/{workId}/access-requests` | `requestBetaReaderAccess` | Pedir ser lector beta | FEAT-RDG-002 | **Implementado** |
| `GET /api/v1/me/access-requests` | `listMyBetaReaderAccessRequests` | Mis solicitudes | FEAT-RDG-002 | **Implementado** |
| `DELETE /api/v1/access-requests/{requestId}` | `cancelBetaReaderAccessRequest` | Retirar la mía | FEAT-RDG-002 | **Implementado** |
| `GET /api/v1/works/{workId}/access-requests` | `listWorkAccessRequests` | Quién quiere leer mi obra | FEAT-RDG-003 | **Implementado** |
| `PUT /api/v1/access-requests/{requestId}/resolution` | `resolveBetaReaderAccessRequest` | Aceptar o rechazar | FEAT-RDG-003 | **Implementado** |
| `POST /api/v1/works/{workId}/beta-reader-invitations` | `inviteBetaReader` | Invitar a alguien | FEAT-RDG-004 | **Implementado** |
| `GET /api/v1/works/{workId}/beta-reader-invitations` | `listWorkBetaReaderInvitations` | Invitaciones de mi obra | FEAT-RDG-004 | **Implementado** |
| `DELETE /api/v1/beta-reader-invitations/{invitationId}` | `cancelBetaReaderInvitation` | Retirar una invitación | FEAT-RDG-004 | **Implementado** |
| `GET /api/v1/me/beta-reader-invitations` | `listMyBetaReaderInvitations` | Lo que me han ofrecido | FEAT-RDG-005 | **Implementado** |
| `PUT /api/v1/beta-reader-invitations/{invitationId}/resolution` | `resolveBetaReaderInvitation` | Aceptar o rechazar | FEAT-RDG-005 | **Implementado** |
| `GET /api/v1/works/{workId}/invitable-readers` | `searchInvitableBetaReaders` | Buscar a quién invitar | FEAT-RDG-006 | **Implementado** |

Convertirse en lector beta de una obra `PUBLIC` **no tiene endpoint**: es el efecto de empezar
una corrección ([`FEAT-RDG-001`](../../features/reading/FEAT-RDG-001-become-beta-reader-by-correcting.md)).
Los tres caminos producen el mismo acceso, pero solo dos de ellos son pantallas.

## Dos recursos, cuatro fichas

`access-request` y `beta-reader-invitation` son la misma forma con los papeles cambiados: uno
lo crea el lector y lo resuelve el autor, el otro al revés. De ahí que las rutas rimen y que
cada recurso aparezca en dos fichas — quien lo crea y quien lo resuelve.

`beta-reader-invitations` y no `invitations` a secas porque `User` ya tiene invitaciones, a la
plataforma. Dos cosas distintas con el mismo nombre en la misma API se confunden una vez y ya
no se dejan de confundir.

## Resolver es un `PUT` con la decisión

No hay `POST .../accept` ni `POST .../reject`. Es **una** transición de estado con dos valores
posibles, igual que `PUT /works/{workId}/status`:

```json
{ "decision": "ACCEPTED" }
```

Dos rutas para el mismo cambio obligan a mantener dos veces las mismas comprobaciones, y son
dos sitios donde olvidarse de una.

Una decisión desconocida devuelve `422 UNKNOWN_DECISION`. **No se interpreta como un
rechazo**: adivinar en una operación que concede acceso a obra inédita es exactamente el sitio
donde no conviene adivinar.

## Autorización

Todas exigen sesión, y las de escritura exigen además **cuenta activada**
([`decision:0003`](../../decisions/0003-write-operations-require-activated-account.md)).

Por encima de eso, la regla es la misma en las diez: **quien no es parte recibe `404`**. Ni un
`403` ni un mensaje que distinga «no es tuya» de «no existe» — confirmar que una solicitud o
una obra existen ya es decir algo de ellas, y este producto custodia obra sin publicar.

La única excepción es deliberada: el autor que intenta solicitar acceso a su propia obra
recibe `422 AUTHOR_CANNOT_BE_BETA_READER` y no `404`. La está viendo; fingir que no existe le
confundiría sobre algo suyo.

## Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `WORK_DOES_NOT_TAKE_REQUESTS` | 422 | La obra es `PUBLIC` o `PRIVATE`: no se solicita |
| `AUTHOR_CANNOT_BE_BETA_READER` | 422 | El autor sobre su propia obra |
| `USER_NOT_FOUND` | 422 | Se invita a alguien que no existe |
| `UNKNOWN_DECISION` | 422 | Ni `ACCEPTED` ni el rechazo correspondiente |
| `ALREADY_A_BETA_READER` | 409 | Ya tiene acceso vivo |
| `REQUEST_ALREADY_PENDING` | 409 | Ya hay una solicitud abierta de esa persona |
| `REQUEST_ALREADY_RESOLVED` | 409 | La solicitud ya estaba cerrada |
| `INVITATION_ALREADY_PENDING` | 409 | Ya hay una invitación abierta para esa persona |
| `INVITATION_ALREADY_RESOLVED` | 409 | La invitación ya estaba cerrada, incluso si fue el autor quien la retiró |
| `WORK_GONE` | 410 | La obra se borró mientras la solicitud o la invitación esperaban |

`410` y no `404` en el último: quien resuelve conoce esa obra —es suya o le invitaron a
ella—, y fingir que nunca existió sería mentirle sobre algo que vio.

Los `409` son la respuesta a una **persona**. Por debajo, los agregados son tolerantes con un
mensaje reentregado: resolver lo ya resuelto no hace nada. Las dos cosas conviven a propósito
— una acción humana repetida merece un aviso, un reintento de la cola merece silencio.

## Paginación

El buscador de a quién invitar **no se pagina, y es la excepción**: no es una colección que
alguien recorre, es una ayuda a escribir un nombre. Un tope duro de diez y ahí se acaba.
Con cursor, veinte peticiones devolverían el directorio entero igual, solo que más despacio —
y un directorio de personas que se puede enumerar es uno que alguien acabará descargando.

Las cuatro bandejas, en cambio, van **por cursor**, con filtro por estado y `PENDING` por defecto
([`paginación`](../conventions/pagination.md)). Son bandejas cronológicas, no catálogos sobre
los que se salte a la página 4, y lo que se abre es lo que queda por resolver.

## Efectos en créditos

Ninguno, en ninguna de las diez. Conceder acceso dejó de tener efecto económico con
[`decision:0006`](../../decisions/0006-credit-system.md): lo que mueve créditos es entregar
una corrección, no poder leer.
