---
id: FEAT-USR-014
title: Ver el perfil público de un usuario
context: User
concept: Profile
actors: [User, Guest]
spec_status: APPROVED
impl_status: PARTIAL
priority: P1
sources:
  - _sources/use-cases.pdf#p3
  - conversation:2026-09-22 (perfil de otro usuario)
  - docs/ui/user-profile.md
endpoints: [GET /users/{userId}, GET /profiles/{username}]
events: []
depends_on: [FEAT-USR-028, FEAT-USR-035]
updated: 2026-09-25
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
| Relatos | Sí, **solo los `PUBLISHED` e `IN_CORRECTION`** |
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
- `RN-3` La pestaña de relatos muestra las obras en `PUBLISHED` e `IN_CORRECTION`, **sin la
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

La respuesta incluye la relación: `isFollowing`, `isFollowedBy`, `isBlocked`. Sin ella el
cliente no puede decidir qué botón pintar sin una segunda petición.

**`isMuted` no está**, y no por olvido: silenciar es
`FEAT-COM-033`, cuya especificación sigue `PENDING`.
Un campo que siempre valiera `false` sería una promesa que el backend no puede cumplir.

Los tres son **nulos sin sesión**, que no es lo mismo que falsos: no hay relación que contar
con un visitante anónimo.

Las pestañas se piden aparte y paginadas.

## Criterios de aceptación

- [x] El perfil devuelve nombre, `@usuario`, avatar, portada y descripción, **y los contadores**.
- [x] **No devuelve email ni fecha de nacimiento.**
- [ ] **La pestaña de relatos no incluye borradores ajenos.** *Las pestañas se piden aparte y todavía no existen.*
- [ ] Los relatos ajenos no muestran la insignia de estado. *Misma pestaña.*
- [ ] No existe forma de listar el feedback que otro usuario ha dado. *Se cumple por construcción —no hay endpoint— pero no hay nada que probar hasta que existan las pestañas.*
- [x] El contador de correcciones sí se devuelve.
- [x] La respuesta indica si le sigo y si me sigue.
- [x] El perfil de una cuenta eliminada devuelve `404`.
- [x] Una cuenta sin activar puede consultar perfiles.
- [x] Editar el perfil de otro usuario se rechaza, aunque la interfaz muestre los lápices. *Se cumple por construcción: [`FEAT-USR-008`](FEAT-USR-008-edit-profile.md) edita `/me/profile` y no admite decir de quién.*

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

**Implementación:** `PARTIAL` (2026-09-24). Sin tabla ni migración: el perfil ya estaba en la
cuenta.

`GET /users/{userId}` y `GET /profiles/{username}` devuelven la misma forma, y con la segunda
se implementa de paso [`FEAT-USR-035`](FEAT-USR-035-resolve-profile-by-username.md): resuelve
primero entre los nombres en uso y solo después entre los **alias vigentes**, con
`canonicalUsername` y `resolvedVia` para que el cliente sustituya la URL sin recargar. No
redirige: este endpoint sirve datos, no páginas.

### Son públicos, y el ajuste de privacidad es lo que los cierra

Un perfil sin restringir es una URL que se comparte; exigir sesión para abrirla haría inútil
compartirla (`FEAT-USR-035` `RN-6`). La sesión se lee **si la hay**, y para una sola cosa: que
su titular se vea siempre a sí mismo. Esconderle su propio perfil sería absurdo, y es lo que
permite deshacer el ajuste — quien lo cierra sigue entrando a abrirlo.

Va en `access_control` y no en un firewall sin seguridad, precisamente para que un visitante
con sesión siga siendo reconocido.

**Con esto, `profileVisibility` significa por fin lo que promete**
([`FEAT-USR-038`](FEAT-USR-038-privacy-settings.md)): hasta ahora en `NOBODY` solo retiraba a
alguien del buscador de a quién invitar; ahora su perfil responde igual que el de alguien que
no existe. `404` y no `403`, porque un `403` confirmaría que la cuenta está ahí y dejaría sin
efecto un ajuste cuya razón de ser es **no ser encontrado**.

Cinco situaciones responden lo mismo: no existe, el identificador está mal escrito, el alias
caducó, la cuenta está eliminada, o su titular ha restringido el perfil.

### Los contadores y la relación, completados (2026-09-25)

**Los contadores se componen, no se proyectan.** Cada uno responde por el contrato publicado de
su contexto —`Community` los seguidos y seguidores, `Work` los relatos, `Feedback` las
correcciones—, exactamente igual que en el perfil propio
([`FEAT-USR-028`](FEAT-USR-028-own-profile-header.md)). Un `null` sigue significando «no se ha
podido saber», que no es lo mismo que cero.

Se consideró y se descartó una proyección en `User` alimentada por eventos, que habría evitado
tres llamadas síncronas en la página más visitada de la plataforma. Se descartó porque
`FEAT-USR-028` `P-12` ya decidió lo contrario **a propósito**: el read model llegará cuando las
cifras digan que hace falta, no antes, porque trae su propia deuda —un contador que puede
quedarse atrás—. Construirlo aquí habría dejado dos verdades sobre los mismos números, una por
endpoint.

**Las propinas recibidas** se añaden como quinta cifra ([`FEAT-CRD-017`](../credits/FEAT-CRD-017-author-tip.md)
`RN-3c`), y quien las contesta es **`Community` y no `Credits`**. Los créditos son de `Credits`,
pero ese contexto no publica contratos ([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md)):
narra sus hechos, y `Community` ya mantenía esa cifra desde `CorrectionTipped` para su propia
reputación. La puerta se abre donde ya está el dato y no donde está su origen.

**La relación sale del grafo local de `User`**, no de un contrato. Es la misma copia que esta
petición ya está usando para decidir si enseña el perfil, así que preguntar en otro sitio podría
dar dos respuestas distintas sobre lo mismo dentro de una sola respuesta HTTP. Va en diferido,
como esa copia.

### Qué falta

- **Las pestañas** (muro, relatos, amigos, obras publicadas), que la propia ficha dice que se
  piden aparte y paginadas. Las obras publicadas son
  [`FEAT-USR-029`](FEAT-USR-029-published-books.md).
- **`isMuted`**, que espera a `FEAT-COM-033`.
- `U-17` sigue abierta, y ahora importa menos de lo que parecía: **no hay pestaña de
  correcciones ni forma de listarlas**, que es lo que la ficha pedía garantizar.
- `B-1` sigue abierta. Hoy un bloqueo **no esconde el perfil**: la respuesta lo dice con
  `isBlocked` y el cliente retira las acciones. Esconderlo del todo es una decisión de
  `FEAT-COM-034` y no de esta ficha.
