---
id: FEAT-COM-033
title: Silenciar a un usuario
context: Community
concept: Curation
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P3
sources:
  - docs/ui/user-profile.md
  - conversation:2026-09-26
endpoints:
  - PUT /users/{userId}/muted
  - DELETE /users/{userId}/muted
  - GET /me/muted-users
events: []
depends_on: [FEAT-COM-001, FEAT-COM-034]
updated: 2026-09-26
---

# FEAT-COM-033 — Silenciar a un usuario

## Resumen

Dejar de ver a alguien en el muro sin cortar nada. Sale del menú «···» del perfil ajeno, junto a
bloquear y denunciar, y es la más suave de las tres.

## Silenciar y bloquear no son lo mismo

Es lo que `docs/features/README.md` ya avisaba y esta ficha cierra. **Silenciar es una
preferencia de visualización; bloquear es una regla de acceso** que atraviesa varios bounded
contexts.

| | Silenciar | Bloquear (`FEAT-COM-034`) |
|---|---|---|
| Sus publicaciones en mi muro | Desaparecen | Desaparecen |
| Sus reposts en mi muro | Desaparecen | Desaparecen |
| Sigo siguiéndole | **Sí**, el seguimiento no se toca | No, se deshace en ambos sentidos |
| Puede comentarme | **Sí** | No |
| Puede escribirme | **Sí** | No |
| Puede mencionarme | **Sí** | No |
| Sus comentarios en hilos ajenos | **Los sigo viendo** | No |
| Su perfil y sus obras | **Los sigo viendo** | Ninguno ve al otro |
| Acceso de lector beta y créditos | **Nada** | Se revoca el acceso vivo |
| ¿Se entera? | No | No |
| Contextos implicados | Solo `Community` | `Community`, `Reading`, `Credits`, `Notification` |

**El alcance es el muro y solo el muro** (decidido el 2026-09-26). Extenderlo a las
notificaciones habría metido a `Community` dentro de las preferencias de `Notification`
(`FEAT-USR-039`), y extenderlo a los comentarios habría dejado huecos en las conversaciones
ajenas y habría convertido silenciar en un bloqueo con otro nombre. Lo que hay entre las dos
cosas es precisamente esto: **quitarle de mi muro**.

## Y no se aplica en su propio perfil

Si visito el perfil de alguien a quien silencié, **veo sus publicaciones**.

No es una excepción caprichosa: silenciar dice «no me lo pongas delante sin pedirlo», y entrar
en su perfil es pedirlo. La alternativa —un perfil vacío sin explicación— haría que la persona
que silenció creyera que el otro dejó de publicar.

Una publicación **ocultada una a una** (`FEAT-COM-022`) sí sigue oculta ahí, porque aquella
acción es sobre esa tarjeta concreta.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Silenciar y dejar de silenciar a alguien | Cuenta activada; esa persona existe |
| `User` | Ver a quién ha silenciado | La suya. No existe la lista de otro |

**No se avisa al silenciado**, igual que con el bloqueo. Avisarle convertiría una preferencia
privada en una confrontación.

## Reglas de negocio

- `RN-1` Silenciar es **privado y unilateral**.
- `RN-2` No se puede silenciar a uno mismo.
- `RN-3` Se silencia a alguien que **existe**. Se comprueba con `RegisteredUsers`.
- `RN-4` Es **idempotente**, y por eso es `PUT`.
- `RN-5` **No toca el seguimiento.** Se puede seguir a alguien silenciado, y tiene sentido:
  quiero saber de sus obras, no leer su muro.
- `RN-6` Afecta a sus publicaciones **y a sus reposts** en el muro general. Nada más.
- `RN-7` **No se aplica en el muro de su propio perfil.**
- `RN-8` La lista de silenciados se lee **sin filtrar por privacidad**, como la de bloqueados y
  por lo mismo: quien pregunta ya sabe a quién silenció, y filtrar haría imposible deshacerlo.
- `RN-9` No publica eventos. A diferencia del bloqueo, esto no significa nada fuera de
  `Community`: un consumidor que reaccionara a un silencio estaría convirtiendo una preferencia
  de pantalla en una regla del sistema.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Silenciarse a uno mismo | Se rechaza | `422` `CANNOT_MUTE_YOURSELF` |
| Esa persona no existe | Se rechaza | `422` `USER_NOT_FOUND` |
| Silenciar a quien ya está silenciado | Nada | `204` |
| Dejar de silenciar a quien no lo estaba | Nada | `204` |

## Contrato de API

| Operación | Método y ruta | `operationId` | Documento |
|---|---|---|---|
| Silenciar | `PUT /users/{userId}/muted` | `muteUser` | `openapi/paths/community.yaml` |
| Dejar de silenciar | `DELETE /users/{userId}/muted` | `unmuteUser` | `openapi/paths/community.yaml` |
| A quién he silenciado | `GET /me/muted-users` | `listMutedUsers` | `openapi/paths/community.yaml` |

La lista pagina con cursor, como la de bloqueados.

## Eventos

Ninguno, y es la diferencia de fondo con `FEAT-COM-034`, que publica `UserBlocked` justamente
porque otros contextos tienen que reaccionar.

## Efectos en créditos

Ninguno. Un lector beta silenciado sigue corrigiendo y sigue cobrando: silenciar no toca el
trabajo, solo el muro.

## Modelo de datos afectado

Tabla nueva `community_ctx.muted_member`: clave `(member_id, muted_id)`, más `muted_at`.
Migración `Version20260928020000`.

## Estado

**Especificación:** `APPROVED` (2026-09-26). Redactada junto con la implementación, sobre la
maqueta y las decisiones ya tomadas.

**Implementación:** `DONE` (2026-09-26). Silenciar, dejar de silenciar y la lista de silenciados.

**Silenciar no es bloquear**, y la diferencia está en el código: el silenciado no se entera,
puede seguir interactuando, y lo único que cambia es qué ve quien silenció. En «solo
guardados» el silencio **no se aplica**: quien guardó una publicación pidió verla.
