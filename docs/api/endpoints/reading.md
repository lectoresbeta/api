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
| `GET /api/v1/works/{workId}/beta-readers` | `listWorkBetaReaders` | Quién puede leer mi obra | FEAT-RDG-010 | **Implementado** |
| `DELETE /api/v1/works/{workId}/beta-readers/{readerId}` | `revokeBetaReaderAccess` | Retirarle el acceso | FEAT-RDG-010 | **Implementado** |
| `POST /api/v1/me/beta-reader-groups` | `createBetaReaderGroup` | Crear un grupo | FEAT-RDG-007 | **Implementado** |
| `GET /api/v1/me/beta-reader-groups` | `listMyBetaReaderGroups` | Mis grupos | FEAT-RDG-007 | **Implementado** |
| `GET /api/v1/beta-reader-groups/{groupId}` | `getBetaReaderGroup` | Ver un grupo y sus miembros | FEAT-RDG-007 | **Implementado** |
| `PATCH /api/v1/beta-reader-groups/{groupId}` | `renameBetaReaderGroup` | Renombrar | FEAT-RDG-007 | **Implementado** |
| `DELETE /api/v1/beta-reader-groups/{groupId}` | `deleteBetaReaderGroup` | Borrar | FEAT-RDG-007 | **Implementado** |
| `PUT /api/v1/beta-reader-groups/{groupId}/members/{readerId}` | `addBetaReaderGroupMember` | Añadir a alguien | FEAT-RDG-007 | **Implementado** |
| `DELETE /api/v1/beta-reader-groups/{groupId}/members/{readerId}` | `removeBetaReaderGroupMember` | Quitar a alguien | FEAT-RDG-007 | **Implementado** |
| `POST /api/v1/works/{workId}/group-invitations` | `inviteBetaReaderGroup` | Invitar al grupo entero | FEAT-RDG-007 | **Implementado** |

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
| `READER_CANNOT_SEE_THIS_WORK` | 409 | Esa persona no podría abrir la obra. Hoy: obra no apta para menores e invitado que no es mayor de edad |
| `WORK_GONE` | 410 | La obra se borró mientras la solicitud o la invitación esperaban |

`READER_CANNOT_SEE_THIS_WORK` **no dice por qué**, y es deliberado: la edad de otra persona no
es asunto de quien invita, y un mensaje que lo insinuara convertiría el botón de invitar en un
comprobador de quién es menor. Se comprueba al invitar y no solo al leer
([`FEAT-USR-044`](../../features/user/FEAT-USR-044-age-based-content-filtering.md) `RN-10`),
porque una invitación que se cursa y falla después deja al autor viendo a alguien aceptar y no
poder entrar, sin explicación ninguna.

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

---

## `GET /api/v1/works/{workId}/beta-readers` y `DELETE .../{readerId}`

**`operationId`:** `listWorkBetaReaders`, `revokeBetaReaderAccess` · **Funcionalidad:**
[`FEAT-RDG-010`](../../features/reading/FEAT-RDG-010-revoke-beta-reader-access.md)

### Propósito

Quién puede leer una obra, y retirarle el acceso. Cierra `R-1`: se entraba por **tres
caminos** y no había forma de salir salvo bloquear a la persona, que es una respuesta social a
un problema que muchas veces no lo es.

### Autorización

Solo el autor de la obra. Una obra ajena responde `404` y no `403`: un `403` confirmaría que
esa obra existe. Revocar exige además la cuenta activada; consultar, no.

### Reglas aplicadas

- Revocar es **idempotente**: sin acceso vivo que retirar, no pasa nada y no se publica nada.
- El acceso se retira **desde ese instante**. Una corrección en curso deja de poder
  entregarse, quien la escribía no cobra —nunca entregó— y su borrador se conserva.
- **Lo ya entregado no se toca**: el autor lo pagó y el lector lo ganó.
- Un acceso **ganado** también se puede retirar: lo que se quita es la lectura futura, no lo
  ganado.
- La lista **no se filtra por privacidad**: quien tiene acceso a una obra inédita aparece
  aunque haya cerrado su perfil, o cerrar el perfil sería volverse invisible para el autor
  cuya obra se está leyendo.

### Lo que no consigue

**Revocar no expulsa de una obra `PUBLIC`.** Esa persona vuelve a tener acceso en cuanto
empiece otra corrección, porque así funciona esa modalidad. Para dejar a alguien fuera hay dos
herramientas distintas: **cerrar la modalidad** —afecta a todos— o **bloquearle**
([`FEAT-COM-034`](../../features/community/FEAT-COM-034-block-user.md)) —afecta solo a esa
persona, y en todas partes—.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `WORK_NOT_FOUND` | 404 | La obra no existe **o no es suya** |
| `ACCOUNT_NOT_ACTIVATED` | 403 | Revocar sin haber activado la cuenta |
| `INVALID_CURSOR` | 422 | Al listar, un cursor que no produjo esta API |

### Efectos

Publica `BetaReaderAccessRevoked`, **el mismo hecho** que publican descartar un borrador y un
bloqueo. Quien lo consume no tiene por qué saber cuál de los tres caminos lo provocó.

## La agenda del autor y por qué no abre puertas

[`FEAT-RDG-007`](../../features/reading/FEAT-RDG-007-beta-reader-groups.md) añade un cuarto
recurso, `beta-reader-group`, y la regla que hay que leer antes que cualquier otra cosa es
que **no es un camino de acceso**.

Un grupo es una lista de contactos privada. Pertenecer a uno no abre ninguna obra, y salir de
uno no cierra ninguna. El acceso sigue naciendo por los tres caminos de siempre y siempre a
nombre de una persona.

Se descartó lo contrario —que meter a alguien en el grupo le abriera las obras asociadas—
porque sería un cuarto camino de entrada, y el más silencioso: quien mira quién puede leer su
obra vería una lista de personas, y el motivo por el que una de ellas está ahí estaría en otra
pantalla. Revocar el acceso tampoco significaría nada mientras el grupo siguiera concediéndolo.

### Invitar en bloque devuelve un parte, no un recurso

`POST /works/{workId}/group-invitations` recorre los miembros y cursa **una invitación normal
por cada uno**, con sus mismas comprobaciones y su mismo evento `BetaReaderInvited`.

Responde `200` y no `201`: no se ha creado un recurso. El cuerpo dice a quién se invitó y a
quién no, y el motivo de cada omisión es **el mismo `code`** que habría devuelto la invitación
individual.

| Situación del miembro | `reason` |
|---|---|
| Ya es lector beta | `ALREADY_A_BETA_READER` |
| Ya tenía invitación abierta | `INVITATION_ALREADY_PENDING` |
| Ya había solicitado acceso | `REQUEST_ALREADY_PENDING` |
| Tiene el buzón cerrado | `INVITATIONS_NOT_ACCEPTED` |
| No tiene edad para la obra | `READER_CANNOT_SEE_THIS_WORK` |
| Su cuenta ya no existe | `USER_NOT_FOUND` |

Devolver `422` porque uno de doce no se puede invitar dejaría al autor sin los once que sí.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `GROUP_NOT_FOUND` | 404 | El grupo no existe **o no es suyo** |
| `GROUP_NAME_REQUIRED` | 422 | Nombre vacío o solo espacios |
| `GROUP_NAME_TOO_LONG` | 422 | Más de 80 caracteres |
| `GROUP_NAME_ALREADY_USED` | 409 | Otro grupo suyo ya se llama así, sin distinguir mayúsculas |
| `TOO_MANY_GROUPS` | 409 | El tope son 50 |
| `TOO_MANY_GROUP_MEMBERS` | 409 | El tope son 200 |
| `USER_NOT_FOUND` | 422 | Quien se añade no existe |
| `AUTHOR_CANNOT_BE_A_MEMBER` | 422 | El autor no se apunta a su propia agenda |

### Dos listas sin paginar, a propósito

Ni los grupos ni los miembros paginan. Los topes son 50 y 200, caben en una pantalla, y un
cursor sobre cincuenta filas es maquinaria que nadie usa. Lo que sí hay es `?query=` sobre los
grupos, que es lo que hace falta cuando uno tiene treinta.
