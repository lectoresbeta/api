---
id: FEAT-RDG-007
title: Gestionar grupos de lectores beta
context: Reading
concept: BetaReaderGroup
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - _sources/use-cases.pdf
  - docs/bounded-contexts/reading.md
  - conversation:2026-09-26 (R-4, R-15 y R-20)
endpoints:
  - POST /me/beta-reader-groups
  - GET /me/beta-reader-groups
  - GET /beta-reader-groups/{groupId}
  - PATCH /beta-reader-groups/{groupId}
  - DELETE /beta-reader-groups/{groupId}
  - PUT /beta-reader-groups/{groupId}/members/{readerId}
  - DELETE /beta-reader-groups/{groupId}/members/{readerId}
  - POST /works/{workId}/group-invitations
events: [BetaReaderInvited]
depends_on: [FEAT-RDG-004, FEAT-RDG-006]
updated: 2026-09-26
---

# FEAT-RDG-007 — Gestionar grupos de lectores beta

## Resumen

La agenda del autor. Un **grupo** es una lista de personas con un nombre que el autor elige
—«Los del taller», «Novela negra», «Los que no se cortan»— y sirve para no volver a buscar a
las mismas diez personas obra tras obra.

## Un grupo no concede nada

Es lo que hay que fijar antes de modelar nada, y cierra `R-4`.

**Pertenecer a un grupo no da acceso a ninguna obra.** El acceso sigue naciendo por los tres
caminos de [`bounded-contexts/reading.md`](../../bounded-contexts/reading.md) y siempre a
nombre de una persona: se invita (`FEAT-RDG-004`), se solicita (`FEAT-RDG-002`) o la obra es
`PUBLIC`. Un grupo es una lista de contactos, no un permiso.

La alternativa —que meter a alguien en el grupo le abriera las obras asociadas— se descartó:
sería un cuarto camino de entrada, el más silencioso de todos. Quien mira quién puede leer su
obra vería una lista de personas, y el motivo por el que una de ellas está ahí estaría en otra
pantalla. Revocar el acceso tampoco significaría nada mientras el grupo siguiera concediéndolo.

## Lo que sí hace: invitar en bloque

Cierra `R-15`. El grupo es un **atajo para invitar**, no un permiso.

`POST /works/{workId}/group-invitations` recorre los miembros del grupo y cursa **una
invitación normal por cada uno**, exactamente la de `FEAT-RDG-004`, con sus mismas
comprobaciones y su mismo evento. Cada persona recibe su aviso y decide por su cuenta.

Lo que ahorra es el clic, no las reglas. Y por eso **la operación no es atómica**: unos
miembros se invitan y otros no, y la respuesta dice cuáles y por qué.

| Miembro | Qué pasa |
|---|---|
| Se puede invitar | Se crea su invitación y se publica `BetaReaderInvited` |
| Ya es lector beta de la obra | Se omite, motivo `ALREADY_A_BETA_READER` |
| Ya tiene una invitación pendiente | Se omite, motivo `INVITATION_ALREADY_PENDING` |
| Ya había solicitado acceso | Se omite, motivo `REQUEST_ALREADY_PENDING` |
| Tiene el buzón de invitaciones cerrado | Se omite, motivo `INVITATIONS_NOT_ACCEPTED` |
| No tiene edad para una obra `ADULTS_ONLY` | Se omite, motivo `READER_CANNOT_SEE_THIS_WORK` |
| Ya no existe su cuenta | Se omite, motivo `USER_NOT_FOUND` |

Devolver `422` porque uno de doce no se puede invitar dejaría al autor sin los once que sí. Un
resultado parcial descrito es más honesto que un fallo total.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Writer` | Crear, renombrar y borrar sus grupos | Cuenta activada |
| `Writer` | Añadir y quitar miembros | Es dueño del grupo |
| `Writer` | Ver sus grupos y sus miembros | Es dueño del grupo |
| `Writer` | Invitar a un grupo entero a **su** obra | Es dueño del grupo y autor de la obra |
| Cualquier otro | — | El grupo ajeno responde `404`, no `403` |

**Los grupos son privados.** No hay vista pública de un grupo, ni el miembro sabe que está en
uno. Es una anotación del autor sobre otras personas, no una relación entre ellas: publicarla
convertiría «a quién suelo pedir que me lea» en información sobre terceros.

## Reglas de negocio

- `RN-1` Un grupo pertenece a **un único autor** y solo él lo ve y lo gestiona.
- `RN-2` El nombre es obligatorio, se recorta, no puede quedar vacío y admite **80 caracteres**.
- `RN-3` **Dos grupos del mismo autor no se llaman igual**, sin distinguir mayúsculas. Dos
  listas con el mismo nombre no se pueden elegir en un desplegable.
- `RN-4` Un autor tiene como mucho **50 grupos**.
- `RN-5` Un grupo tiene como mucho **200 miembros**. Es una agenda, no una lista de difusión.
- `RN-6` Un miembro es **un usuario que existe**. Se comprueba con `RegisteredUsers`.
- `RN-7` **El autor no se añade a sí mismo.** No se invita a leer su propia obra.
- `RN-8` Añadir a quien ya está es **idempotente**: responde `204` y no duplica nada. Por eso
  es `PUT` y no `POST`.
- `RN-9` Quitar a quien no está también responde `204`.
- `RN-10` Borrar un grupo borra sus miembros y **no toca ninguna invitación ya cursada**: lo
  que se invitó, invitado está.
- `RN-11` Pertenecer a un grupo **no concede acceso a nada** (`R-4`).
- `RN-12` La invitación en bloque es **parcial y descrita**: devuelve `200` con lo invitado y
  lo omitido, aunque no se invite a nadie.
- `RN-13` La lista de grupos admite **buscar por nombre** (`R-20`), sin distinguir mayúsculas
  ni acentos del propio nombre. Buscar entre los miembros no: el listado de un grupo cabe
  entero en la pantalla.

## Por qué los miembros se leen con `ProfileCards` y no con `VisibleProfiles`

El mismo motivo que la lista de bloqueados (`FEAT-COM-034`): **quien pregunta ya sabe quiénes
son**, porque los metió él. Si el listado filtrara por privacidad, un miembro que cierre su
perfil desaparecería del grupo y el autor no podría ni quitarlo — una lista de la que no se
puede borrar a nadie.

Lo que se enseña de cada miembro es su tarjeta: identificador, `username`, nombre y avatar. Ni
correo, ni actividad, ni nada que el autor no viera ya al invitarlo.

## Flujo principal

1. El autor crea un grupo con un nombre.
2. Busca personas con `FEAT-RDG-006` —o llega a ellas por su perfil— y las va añadiendo.
3. Al abrir una obra a lectores beta, elige el grupo e invita en bloque.
4. `Reading` cursa una invitación por miembro y devuelve qué se invitó y qué se omitió.
5. Cada invitado resuelve su invitación en `FEAT-RDG-005`, como cualquier otra.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Nombre vacío o solo espacios | Se rechaza | `422` `GROUP_NAME_REQUIRED` |
| Ya hay un grupo con ese nombre | Se rechaza | `409` `GROUP_NAME_ALREADY_USED` |
| El autor llega a 50 grupos | Se rechaza | `409` `TOO_MANY_GROUPS` |
| El grupo llega a 200 miembros | Se rechaza | `409` `TOO_MANY_GROUP_MEMBERS` |
| El miembro no existe | Se rechaza | `422` `USER_NOT_FOUND` |
| El autor se añade a sí mismo | Se rechaza | `422` `AUTHOR_CANNOT_BE_A_MEMBER` |
| Grupo de otra persona, o inexistente | Igual que inexistente | `404` `GROUP_NOT_FOUND` |
| Invitar un grupo vacío | No se invita a nadie, y se dice | `200`, `invited: []` |
| Invitar a la obra de otro | Igual que obra inexistente | `404` `WORK_NOT_FOUND` |

## Contrato de API

| Operación | Método y ruta | `operationId` | Documento |
|---|---|---|---|
| Crear grupo | `POST /me/beta-reader-groups` | `createBetaReaderGroup` | `openapi/paths/reading.yaml` |
| Mis grupos | `GET /me/beta-reader-groups` | `listMyBetaReaderGroups` | `openapi/paths/reading.yaml` |
| Ver un grupo | `GET /beta-reader-groups/{groupId}` | `getBetaReaderGroup` | `openapi/paths/reading.yaml` |
| Renombrar | `PATCH /beta-reader-groups/{groupId}` | `renameBetaReaderGroup` | `openapi/paths/reading.yaml` |
| Borrar | `DELETE /beta-reader-groups/{groupId}` | `deleteBetaReaderGroup` | `openapi/paths/reading.yaml` |
| Añadir miembro | `PUT /beta-reader-groups/{groupId}/members/{readerId}` | `addBetaReaderGroupMember` | `openapi/paths/reading.yaml` |
| Quitar miembro | `DELETE /beta-reader-groups/{groupId}/members/{readerId}` | `removeBetaReaderGroupMember` | `openapi/paths/reading.yaml` |
| Invitar al grupo | `POST /works/{workId}/group-invitations` | `inviteBetaReaderGroup` | `openapi/paths/reading.yaml` |

La lista de grupos **no pagina**: el tope son 50 y caben en una pantalla. La de miembros
tampoco, por lo mismo con 200.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `BetaReaderInvited` | Una vez **por miembro invitado** | El mismo de `FEAT-RDG-004` |

No hay ningún evento de grupo. Un grupo es una anotación privada del autor y no es un hecho
que nadie fuera de `Reading` necesite conocer.

**Consume**

Ninguno.

## Efectos en créditos

Ninguno. Un grupo no mueve créditos, y las invitaciones que genera tampoco: los créditos se
mueven cuando hay corrección entregada (`FEAT-CRD-011`).

## Modelo de datos afectado

Las dos tablas **ya existen** desde `Version20260923174400`:

| Tabla | Nota |
|---|---|
| `reading_ctx.beta_reader_group` | `id`, `author_id`, `name`, `created_at`. Índice por `author_id` |
| `reading_ctx.beta_reader_group_member` | Clave `(group_id, reader_id)`, `added_at`. Índice por `reader_id` |

No hace falta migración nueva. No se añade unicidad de `(author_id, name)` en base de datos:
`RN-3` compara sin mayúsculas y una restricción que no distingue mayúsculas sería un índice
funcional que la comprobación de aplicación ya cubre, con el coste de no poder dar un mensaje.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| R-4 | ¿Los grupos conceden acceso en bloque? | **Resuelta: no.** Un grupo es una agenda. Lo que se hace en bloque es **invitar**, y cada invitación es individual y se resuelve por separado |
| R-15 | ¿Se puede invitar a un grupo entero? | **Resuelta: sí**, con `POST /works/{workId}/group-invitations`. El resultado es parcial y descrito |
| R-20 | ¿Se puede buscar entre los propios grupos? | **Resuelta: sí**, por nombre, con `?query=` en `GET /me/beta-reader-groups`. Entre los miembros no |

## Estado

**Especificación:** `APPROVED` (2026-09-26). Redactada junto con la implementación, sobre la
maqueta y las decisiones ya tomadas.

**Implementación:** `DONE` (2026-09-26). Crear, renombrar, listar, borrar, componer el grupo e **invitar en bloque**.

La invitación en bloque reutiliza la de uno en uno, entera: las mismas reglas, los mismos
rechazos. Lo que hace de más es **no parar en el primer no** — un miembro que ya tiene
acceso, o que ha cerrado las propuestas, se salta y se informa, en vez de tumbar la tanda.
