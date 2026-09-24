---
id: FEAT-RDG-004
title: Invitar a un usuario a ser lector beta de una obra
context: Reading
concept: AccessInvitation
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - _sources/use-cases.pdf
  - docs/bounded-contexts/reading.md
  - docs/features/work/FEAT-WRK-007-configure-access-mode.md
endpoints:
  - POST /works/{workId}/beta-reader-invitations
  - GET /works/{workId}/beta-reader-invitations
  - DELETE /beta-reader-invitations/{invitationId}
events: [BetaReaderInvited]
depends_on: [FEAT-WRK-007, FEAT-RDG-005]
updated: 2026-09-24
---

# FEAT-RDG-004 — Invitar a un lector beta

## Resumen

El autor elige a quién quiere dentro. Es el único camino de entrada a una obra `PRIVATE` y el
único de los tres que empieza por el autor.

Una invitación **no concede nada**: es una oferta, y quien decide es quien la recibe, en
[`FEAT-RDG-005`](FEAT-RDG-005-resolve-invitation.md). Las dos fichas se implementan juntas.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Writer` | Invitar a un usuario a su obra | Cuenta activada; es el autor |
| `Writer` | Ver y retirar las invitaciones de su obra | Las de su obra |
| `Reader` | — | No invita a nadie, ni a sí mismo |

## De dónde sale el usuario al que se invita

Se invita **por identificador de usuario**, no por nombre ni por correo.

Encontrar ese identificador es otra funcionalidad —[`FEAT-RDG-006`](../README.md), buscar
lectores beta— que todavía no existe. Mientras no exista, esta se puede usar desde el perfil
público de alguien, que es donde hoy se llega a un usuario concreto.

Invitar por **correo** sería otra cosa: una invitación a la plataforma, que es de `User`
(`src/User/Invitation`). Conviene no mezclarlas — una ofrece leer una obra, la otra ofrece una
cuenta.

`Reading` comprueba que el destinatario existe mediante un contrato publicado de `User`,
`RegisteredUsers`, que responde un booleano. Sin esa comprobación, invitar a un identificador
inventado crearía una invitación que nadie puede aceptar y un aviso que no se puede entregar.

## Reglas de negocio

- `RN-1` **Solo el autor de la obra invita** (`bounded-contexts/reading.md` `RN-4`).
- `RN-2` Se invita a un usuario que existe. Uno que no existe se rechaza nombrando el motivo.
- `RN-3` Se puede invitar **en cualquier modalidad**, incluida `PUBLIC`. Ver abajo.
- `RN-4` Una invitación `PENDING` por terna (obra, autor, invitado).
- `RN-5` No se invita a quien **ya es lector beta** de la obra.
- `RN-6` No se invita a quien tiene una **solicitud `PENDING`** sobre esa obra: ahí lo que
  toca es aceptarla ([`FEAT-RDG-003`](FEAT-RDG-003-resolve-access-request.md)).
- `RN-7` El autor no se invita a sí mismo.
- `RN-8` La invitación admite un **mensaje opcional** del autor. En una obra inédita, «te
  invito a leer lo que llevo» dice mucho más que una notificación seca.
- `RN-9` El autor **retira** una invitación mientras siga `PENDING`.
- `RN-10` Invitar **no cuesta ni compromete créditos**
  ([`decision:0006`](../../decisions/0006-credit-system.md)).
- `RN-11` Las invitaciones **no caducan** (`R-2`, misma respuesta que las solicitudes).
- `RN-12` Invitar sobre un **borrador** está permitido. Ver abajo.

## `RN-3` — invitar sí vale en `PUBLIC`, aunque solicitar no

Es la asimetría con [`FEAT-RDG-002`](FEAT-RDG-002-request-beta-reader-access.md) `RN-2`, y
parece incoherente hasta que se mira quién trabaja.

Una **solicitud** sobre una obra `PUBLIC` le pide al autor que haga algo por alguien que ya
podía entrar solo: es trabajo para nada, y por eso se rechaza. Una **invitación** sobre una
obra `PUBLIC` es el autor decidiendo hacer ese trabajo, y además dice algo que la modalidad no
dice: *quiero que tú la leas*. Rechazarla sería corregir al autor sobre su propia obra.

Lo que sí cambia es el efecto: en `PUBLIC` el acceso que la invitación concede no añade
permiso, solo procedencia. Es información honesta —`AUTHOR_INVITATION` en vez de
`PUBLIC_JOIN`— y no molesta a nadie.

## `RN-12` — invitar a un borrador

Está permitido, y es probablemente el caso más valioso de esta ficha: la primera persona a la
que un autor enseña algo suele verlo antes de que exista para nadie más.

No contradice «el borrador ajeno no existe» de
[`FEAT-WRK-004`](../work/FEAT-WRK-004-read-a-work.md): esa regla protege de los extraños, y
aquí es el autor quien abre la puerta, a una persona y por su nombre.

Lo que no puede hacer la invitación es saltarse la lectura: quien acepta obtiene un
`BetaReaderAccess`, y la quinta puerta de `WorkReadPolicy` deja leer a un lector beta **sea
cual sea la modalidad**. Sobre un borrador, esa puerta tiene delante la segunda —«un borrador
no existe para nadie más»—, que se evalúa antes. Dicho claro: **invitar a un borrador es
legítimo, y el invitado no lee nada hasta que la obra se publique.** La invitación se acepta y
espera.

Es un caso incómodo de explicar en pantalla y se apunta como `R-13`. La alternativa —prohibir
invitar sobre borradores— cierra el caso valioso para evitar una explicación.

## Flujo principal

1. El autor elige a alguien y le invita, con o sin mensaje.
2. `Reading` comprueba que la obra es suya, que el destinatario existe y que no hay ya un
   acceso vivo ni una solicitud pendiente de esa persona.
3. Crea la `AccessInvitation` en `PENDING`.
4. Publica `BetaReaderInvited`. `Notification` avisa al invitado.
5. El autor la ve en la lista de su obra hasta que se resuelva o la retire.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| La obra no existe o no es suya | La misma respuesta para las dos | `404 WORK_NOT_FOUND` |
| El usuario invitado no existe | Se nombra el motivo: el autor eligió a alguien | `422 USER_NOT_FOUND` |
| Ya es lector beta | No se crea nada | `409 ALREADY_A_BETA_READER` |
| Tiene una solicitud `PENDING` | Se le remite a resolverla (`RN-6`) | `409 REQUEST_ALREADY_PENDING` |
| Ya hay una invitación `PENDING` | No se crea una segunda | `409 INVITATION_ALREADY_PENDING` |
| El autor se invita a sí mismo | Se rechaza sin fingir nada | `422 AUTHOR_CANNOT_BE_BETA_READER` |
| Retira una invitación ya resuelta | No se deshace nada | `409 INVITATION_ALREADY_RESOLVED` |
| Cuenta sin activar | Regla general de escritura | `403 ACCOUNT_NOT_ACTIVATED` |

`RN-4` se sostiene, como su gemela, en un **índice único parcial** sobre las invitaciones
`PENDING`, no en una lectura previa.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Invitar | `POST /api/v1/works/{workId}/beta-reader-invitations` | `inviteBetaReader` |
| Invitaciones de mi obra | `GET /api/v1/works/{workId}/beta-reader-invitations` | `listWorkBetaReaderInvitations` |
| Retirar una | `DELETE /api/v1/beta-reader-invitations/{invitationId}` | `cancelBetaReaderInvitation` |

La ruta dice `beta-reader-invitations` y no `invitations` a secas porque `User` ya tiene
invitaciones —a la plataforma— y dos cosas distintas con el mismo nombre en la misma API se
confunden una vez y ya no se dejan de confundir.

## Eventos

**Publica**

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `BetaReaderInvited` | Se crea la invitación | `Notification` (avisa al invitado) | `invitationId`, `workId`, `authorId`, `readerId` |

Sin el mensaje del autor, por el mismo motivo que `AccessRequested` va sin el del lector.

Retirar una invitación **no publica nada** hoy, y es discutible: el invitado ya recibió el
aviso y puede llegar a una oferta que ya no está (`R-14`). Se ha preferido no inventar un
evento cuyo consumidor no está especificado, y que quien llegue tarde reciba un `409` claro.

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `WorkDeleted` | `Work` | Cierra las invitaciones `PENDING` de esa obra |
| `UserDeleted` | `User` | Cierra las invitaciones `PENDING` dirigidas a ese usuario |

## Modelo de datos afectado

El agregado `AccessInvitation` **ya existe** con su tabla y su máquina de estados
(`PENDING → ACCEPTED | DECLINED | CANCELLED`). Faltan repositorio, casos de uso, endpoints y
el índice único parcial sobre `(work_id, invitee_id)` restringido a `PENDING`.

Ninguna tabla nueva.

## Criterios de aceptación

- [x] El autor invita a un usuario y este recibe el aviso.
- [x] Invitar dos veces a la misma persona no crea dos invitaciones.
- [x] Invitar a la obra de otra persona responde `404`.
- [x] Invitar a un usuario inexistente responde `422` nombrando el motivo.
- [x] Invitar a quien ya es lector beta responde `409`.
- [x] Invitar a quien tiene una solicitud pendiente responde `409` y no crea una segunda vía.
- [x] El autor no puede invitarse a sí mismo.
- [x] Se puede invitar sobre una obra `PUBLIC` y sobre un borrador.
- [x] Retirar una invitación pendiente la hace desaparecer para el invitado.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| R-13 | ¿Cómo se le explica al invitado de un borrador que aceptó y todavía no puede leer? | Solo de pantalla, pero es una espera sin explicación evidente (`RN-12`) |
| R-14 | ¿Hace falta avisar al invitado de que la invitación se ha retirado? | Un evento más y un consumidor sin especificar |
| R-15 | ¿Se puede invitar a un grupo de lectores beta entero? | Es `R-4` del contexto y [`FEAT-RDG-007`](../README.md): una operación por lotes sobre esta misma |
| R-16 | ¿Hay tope de invitaciones pendientes por obra? | Sin tope, invitar es un canal de mensajería con otro nombre |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Aprobada con sus decisiones discutibles
explícitas: `RN-3` —invitar vale en `PUBLIC` aunque solicitar no— y `RN-12` —invitar a un
borrador, con la espera que eso implica—.

**Implementación:** `DONE` (2026-09-24), junto a
[`FEAT-RDG-005`](FEAT-RDG-005-resolve-invitation.md).

Sin tabla nueva y sin migración: `access_invitation` estaba en el esquema desde el andamiaje
inicial, con su índice único parcial. Lo nuevo fuera de este concepto es **`RegisteredUsers`**,
el contrato de `User` que responde un booleano —existe o no— y nada más.

`RN-6` resultó tener un efecto que no se veía al especificar: como no se puede invitar a quien
tiene una solicitud abierta **ni** a quien ya es lector beta, la única forma de que coexistan
una invitación pendiente y un acceso vivo es que el acceso llegue por la **tercera** puerta —
el autor abre la obra y el invitado entra poniéndose a corregir. Es lo que prueba el test de
`RN-4` de la ficha gemela, y de paso deja escrito que las tres vías se cruzan en un solo
sitio.
