---
id: FEAT-USR-014
title: Ver el perfil público de un usuario
context: User
concept: Profile
actors: [User, Guest]
spec_status: APPROVED
impl_status: TODO
priority: P1
sources:
  - _sources/use-cases.pdf#p3
  - conversation:2026-09-22 (perfil de otro usuario)
  - docs/ui/user-profile.md
endpoints: [GET /users/{userId}, GET /profiles/{username}]
events: []
depends_on: [FEAT-USR-028, FEAT-USR-035]
updated: 2026-09-24
---

# FEAT-USR-014 — Ver el perfil público de un usuario

## Resumen

Vista de un perfil ajeno: cabecera, contadores, estado de la relación, acciones sociales y
cuatro pestañas.

Es la misma persona que en [`FEAT-USR-028`](FEAT-USR-028-own-profile-header.md), vista desde
fuera. Lo que importa de esta ficha es **qué se recorta**.

## Qué se ve y qué no

| Dato | ¿Público? |
|---|---|
| Nombre, `@usuario`, avatar, portada, descripción | Sí |
| Contadores: seguidos, seguidores, relatos, correcciones | Sí |
| Muro: publicaciones y reposts | Sí |
| Relatos | Sí, **solo los `VISIBLE` e `IN_CORRECTION`** |
| Amigos: seguidos y seguidores | Sí |
| Obras publicadas y premios | Sí |
| **Lista de correcciones** | **No.** No hay pestaña (`U-17`) |
| Email, fecha de nacimiento | **No**, nunca |
| Borradores | **No**, nunca |

### La lista de correcciones no se expone

El perfil ajeno **no tiene pestaña de correcciones**, aunque sí muestra su contador.

Cuántas correcciones ha hecho alguien funciona como reputación; **cuáles** revelaría qué está
leyendo y, con ello, qué obras hay en corrección y quién las está criticando. El feedback es
una conversación entre corrector y autor sobre un texto inédito.

Se documenta como regla (`RN-4`), pero **conviene confirmar que es deliberado** (`U-17`): en
el diseño podría ser un olvido.

## Reglas de negocio

- `RN-1` Devuelve **solo datos públicos**. Nunca email ni fecha de nacimiento.
- `RN-2` **Nunca devuelve borradores** ajenos. Un borrador es obra inédita que su autor no ha
  decidido enseñar (`FEAT-WRK-016` `RN-4`).
- `RN-3` La pestaña de relatos muestra las obras en `VISIBLE` e `IN_CORRECTION`, **sin la
  insignia de estado**: eso es información de gestión del autor.
- `RN-4` **No se expone la lista de feedback** que ese usuario ha dado. Sí su contador.
- `RN-5` Incluye el estado de la relación en **ambos sentidos**: si le sigo y si me sigue
  («Te sigue»).
- `RN-6` Si hay bloqueo entre ambos, el perfil no se comporta con normalidad
  (`FEAT-COM-034`, `B-1`).
- `RN-7` El perfil de una cuenta eliminada devuelve `404`.
- `RN-8` Una cuenta en `PENDING_ACTIVATION` **puede consultar** perfiles: es solo lectura.
- `RN-9` Los estados vacíos van en tercera persona y no ofrecen acciones al visitante.

`RN-2` es la que hay que probar de verdad. El resto del perfil es información que su dueño ha
elegido mostrar; un borrador, no.

## Acciones sobre el perfil

| Acción | Cuándo | Funcionalidad |
|---|---|---|
| Seguir | Si no le sigo | `FEAT-COM-010` |
| Siguiendo / dejar de seguir | Si le sigo | `FEAT-COM-010` |
| Enviar mensaje | Siempre visible | `FEAT-COM-011`, y ver `U-21` |
| Compartir perfil | Menú «···» | `FEAT-USR-032` |
| Silenciar | Menú «···» | `FEAT-COM-033` |
| Bloquear | Menú «···» | `FEAT-COM-034` |
| Denunciar | Menú «···» | `FEAT-COM-035` |

El botón principal cambia según el estado: «Seguir» si no le sigues, «Enviar mensaje» si ya
le sigues.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Perfil por identificador | `GET /users/{userId}` | `getUserProfile` |
| Perfil por nombre de usuario | `GET /profiles/{username}` | `getProfileByUsername` |

Ambas devuelven lo mismo. La segunda resuelve además alias (`FEAT-USR-035`).

La respuesta incluye la relación: `isFollowing`, `isFollowedBy`, `isBlocked`, `isMuted`. Sin
ella el cliente no puede decidir qué botón pintar sin una segunda petición.

Las pestañas se piden aparte y paginadas.

## Criterios de aceptación

- [ ] El perfil devuelve nombre, `@usuario`, avatar, portada, descripción y contadores.
- [ ] **No devuelve email ni fecha de nacimiento.**
- [ ] **La pestaña de relatos no incluye borradores ajenos.**
- [ ] Los relatos ajenos no muestran la insignia de estado.
- [ ] No existe forma de listar el feedback que otro usuario ha dado.
- [ ] El contador de correcciones sí se devuelve.
- [ ] La respuesta indica si le sigo y si me sigue.
- [ ] El perfil de una cuenta eliminada devuelve `404`.
- [ ] Una cuenta sin activar puede consultar perfiles.
- [ ] Editar el perfil de otro usuario se rechaza, aunque la interfaz muestre los lápices.

El último responde a un error de maqueta: los lápices de edición aparecen en el perfil ajeno.
El backend debe rechazarlo con independencia de lo que pinte el cliente.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **U-17** | ¿Es deliberado que el contador de correcciones sea público y la lista no? | Si no lo es, falta una pestaña |
| U-18 | ¿De dónde sale el nombre de pila de los estados vacíos? | Partir el `Name` es frágil: es texto libre |
| ~~U-20~~ | **Resuelta:** la lista de seguidores **es pública**, sujeta al ajuste de privacidad del perfil |
| U-21 | ¿Se muestra «Enviar mensaje» a quien no los tiene habilitados? | `FEAT-USR-010` |
| B-1 | ¿Qué ve un usuario bloqueado al visitar el perfil de quien le bloqueó? | `FEAT-COM-034` |

## Estado

**Especificación:** `APPROVED` (2026-09-24). `U-20` resuelta: la lista de seguidores es pública. Lo que
queda son detalles de presentación.

**Implementación:** `TODO`.
