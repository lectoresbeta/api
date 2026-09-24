---
id: FEAT-COM-010
title: Suscribirse a un autor
context: Community
concept: Subscription
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/ui/user-profile.md
  - docs/ui/account-creation.md
  - conversation:2026-09-24
endpoints:
  - GET /users/{userId}/subscription
  - PUT /users/{userId}/subscription
  - DELETE /users/{userId}/subscription
events: [AuthorSubscribed, AuthorUnsubscribed]
depends_on: [FEAT-USR-014]
updated: 2026-09-24
---

# FEAT-COM-010 — Suscribirse a un autor

## Resumen

Un usuario puede **seguir** a otro y dejar de seguirle. Es la acción del botón «Seguir» del
perfil ajeno ([`user-profile.md`](../../ui/user-profile.md)), del paso 3 del onboarding
(`FEAT-COM-016`) y de las sugerencias de la Home (`FEAT-COM-018`): **una sola acción con
varias puertas**, no tres cosas parecidas.

El glosario la llama `AuthorSubscription`, y la interfaz «Seguir». Los dos nombres se
refieren a lo mismo; el del código es el del glosario, que es normativo.

## Por qué es la primera pieza de `Community`

Hasta ahora `Community` era un modelo sin comportamiento: entidades, tablas y ni un solo
endpoint. Esta funcionalidad es la que lo pone en marcha, y con ella llega la primera pregunta
seria del contexto: **quién puede saber quién sigue a quién.**

Además desatasca una frase que hoy es mentira a medias en `User`. `FEAT-USR-038` ofrece
`FOLLOWERS` en dos ajustes —quién ve mi perfil y quién puede comentar mis textos— y
`CheckAuthorAudience` responde hoy `false` siempre, **porque el conjunto de seguidores de
cualquiera está vacío**. En cuanto se puede seguir, deja de estarlo.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Seguir y dejar de seguir a otro | Sesión iniciada **y cuenta activada** |
| `User` | Saber si sigue a alguien | Sesión iniciada |

Nadie puede consultar a quién sigue **otra** persona por esta vía. Las listas de seguidos y
seguidores son `FEAT-COM-027`, y `CM-14` —si son públicas— sigue abierta: una funcionalidad
que se llama «seguir» no debe decidir de paso quién puede auditar el grafo social.

## Reglas de negocio

- `RN-1` Una suscripción por par (suscriptor, autor). Seguir dos veces **no** crea una
  segunda: la operación es idempotente y responde lo mismo la primera vez y la quinta.
- `RN-2` Nadie se sigue a sí mismo.
- `RN-3` Solo se puede seguir a una cuenta que existe. Se comprueba contra el contrato
  publicado de `User` (`RegisteredUsers`), no consultando sus tablas.
- `RN-4` Dejar de seguir a quien no se sigue **no es un error**: el resultado que pedía el
  cliente —no seguirle— ya se cumple.
- `RN-5` Seguir exige cuenta activada
  ([`decision:0003`](../../decisions/0003-write-operations-require-activated-account.md)).
- `RN-6` Se publica `AuthorSubscribed` al seguir y `AuthorUnsubscribed` al dejar de seguir.
  **Los dos, y no solo el primero**: quien proyecte el grafo necesita las dos mitades, y un
  seguimiento que se deshace sin avisar deja a todo el mundo con una copia que envejece mal.
- `RN-7` Seguir **no concede acceso a nada**. No abre obras, no da acceso de lector beta y no
  crea conversación. Lo único que cambia es qué avisos se reciben y qué audiencias incluyen a
  esa persona.
- `RN-8` Repetir la operación no reabre nada: volver a seguir a alguien a quien se dejó de
  seguir crea una suscripción nueva, con su fecha nueva. No hay histórico.

## `FOLLOWERS` deja de ser una tautología

Esta es la consecuencia que hay que entender antes de implementar nada.

`User` no puede preguntarle a `Community` «¿me sigue esta persona?» en el momento de
responder, y no es una limitación técnica sino la regla 4 de
[`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md): **un contrato
no llama al contrato de otro contexto mientras responde.** `CheckAuthorAudience` es
precisamente un contrato publicado —lo consultan `Work` y `Feedback`—, y `Community` ya llama
a `RegisteredUsers` de `User` para seguir a alguien. Un contrato en sentido contrario cerraría
el ciclo de llamadas que esa regla existe para evitar.

Así que `User` **proyecta** el grafo: mantiene su propia copia de «quién sigue a quién»,
alimentada por `AuthorSubscribed` y `AuthorUnsubscribed`, y la consulta sin salir de casa.

```text
Community                          User
  AuthorSubscribed    ──▶ cola ──▶  proyección de seguidores
  AuthorUnsubscribed  ──▶ cola ──▶        │
                                          ▼
                             CheckAuthorAudience  (techo de comentarios)
                             VisibleProfile       (visibilidad del perfil)
```

La copia es **consistente en diferido**: entre seguir a alguien y poder comentar sus textos
pasa lo que tarde la cola. Es aceptable y conviene decirlo — no lo es al revés, y por eso el
hecho de dejar de seguir viaja por el mismo camino y no se olvida nunca.

> **Lo que esto abre, y hay que decidir** (`C-22`): con el seguimiento **unilateral**, «solo
> mis seguidores» significa en la práctica **«cualquiera que pulse Seguir»**. Quien restringe
> su perfil a `FOLLOWERS` probablemente espera algo más fuerte. Las salidas son dos, y ninguna
> cabe aquí: que seguir a una cuenta restringida **requiera aprobación** —una funcionalidad
> nueva, que no está especificada en ningún sitio— o que `FOLLOWERS` signifique **seguimiento
> mutuo**, que cambia lo que el ajuste promete. Mientras se decide, `FOLLOWERS` hace
> literalmente lo que dice.

## Flujo principal

1. El usuario abre un perfil y pulsa «Seguir».
2. Se comprueba que la cuenta existe y que no es la suya.
3. Se crea la suscripción, si no la había.
4. Se publica `AuthorSubscribed`.
5. `User` proyecta el nuevo seguidor; `Notification` avisará al autor cuando exista.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Ya le seguía | No se crea nada. **Misma respuesta** | `204` |
| Se sigue a sí mismo | Se rechaza | `422` con `code: CANNOT_SUBSCRIBE_TO_YOURSELF` |
| La cuenta no existe o está eliminada | Se rechaza | `404` con `code: USER_NOT_FOUND` |
| Dejar de seguir a quien no seguía | No pasa nada | `204` |
| Cuenta sin activar | Se rechaza | `403` con `code: ACCOUNT_NOT_ACTIVATED` |
| Sin sesión | Se rechaza | `401` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| ¿Sigo a esta persona? | `GET /users/{userId}/subscription` | `getAuthorSubscription` |
| Seguir | `PUT /users/{userId}/subscription` | `subscribeToAuthor` |
| Dejar de seguir | `DELETE /users/{userId}/subscription` | `unsubscribeFromAuthor` |

`PUT` y no `POST` porque **el resultado es el estado, no un suceso**: seguir a alguien a quien
ya sigues deja el mundo exactamente igual, que es la definición de idempotente. Un `POST`
invitaría a devolver `201` la primera vez y `409` después, y un `409` ahí sería decirle a
alguien que ha fallado cuando lo que pedía ya se cumple.

El `GET` responde solo por uno mismo: `{ "subscribed": true|false, "subscribedAt": … }`. Es lo
que necesita el botón del perfil, y nada más — quién sigue a quién en general es `FEAT-COM-027`.

## Eventos

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `AuthorSubscribed` | Alguien empieza a seguir a un autor | `User` (proyección de audiencias), `Notification` | `subscriberId`, `authorId`, `subscribedAt` |
| `AuthorUnsubscribed` | Alguien deja de seguir | `User` (proyección de audiencias) | `subscriberId`, `authorId`, `unsubscribedAt` |

Los dos llevan **los dos identificadores y nada más**. Ni nombres ni perfiles: quien los
consume tiene su propia copia de las personas, y copiar el nombre aquí sería otro sitio donde
envejece.

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `community_ctx.author_subscription` | Ya existe. `id`, `subscriber_id`, `author_id`, `created_at`, con único sobre el par |
| `user_ctx.author_follower` | **Nueva.** La proyección: `author_id`, `follower_id`, `followed_at` |

La proyección tiene clave primaria compuesta `(author_id, follower_id)`, que es lo que la hace
idempotente sin esfuerzo: reprocesar el mismo hecho escribe la misma fila.

No lleva identificador de suscripción ni fecha de la suscripción original. No es lo mismo un
agregado que una copia para responder una pregunta: `Community` es el dueño del seguimiento y
`User` solo necesita saber si existe.

## Criterios de aceptación

- [x] Seguir a alguien y consultarlo devuelve que se le sigue.
- [x] Seguir dos veces no crea dos suscripciones y responde lo mismo.
- [x] Dejar de seguir a quien no se sigue responde lo mismo que dejar de seguir de verdad.
- [x] Nadie puede seguirse a sí mismo.
- [x] Seguir a un identificador que no existe devuelve `404`.
- [x] Seguir a una cuenta eliminada devuelve `404`. *Por construcción: `RegisteredUsers` responde `false` para una cuenta eliminada. No hay prueba porque no hay borrado de cuenta (`FEAT-USR-013` está `BLOCKED`).*
- [x] Seguir exige la cuenta activada; consultar no.
- [x] Se publican `AuthorSubscribed` y `AuthorUnsubscribed` con los dos identificadores.
- [x] Con `commentPermission` en `FOLLOWERS`, un seguidor puede corregir y un extraño no.
- [x] Con `profileVisibility` en `FOLLOWERS`, un seguidor ve el perfil y un extraño no.
- [x] Dejar de seguir retira las dos cosas.
- [x] Volver a seguir las devuelve.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **C-22** | Con seguimiento unilateral, `FOLLOWERS` equivale a «cualquiera que pulse Seguir». ¿Hace falta aprobación, o `FOLLOWERS` debería ser seguimiento mutuo? | **Decisión de producto.** Afecta a lo que promete `FEAT-USR-038`, no a este modelo |
| C-23 | ¿Se puede seguir a alguien cuyo perfil no se puede ver? | Hoy **sí**, si se conoce su identificador. Hace falta conocerlo, y solo se obtiene de un perfil que sí se podía ver |
| CM-14 | ¿La lista de seguidores es pública? | `FEAT-COM-027`. Esta ficha no la decide |
| CM-15 | ¿«Mis Amigos» es un buen nombre para una relación asimétrica? | Seguir no es ser amigo (`P-8`) |
| C-24 | ¿Aparece en el buscador de a quién invitar alguien con el perfil en `FOLLOWERS`, para sus seguidores? | El contrato `ReaderDirectory` no recibe quién busca, así que hoy no. Ver `FEAT-RDG-006` |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Sale de tres documentos de interfaz que la
describen igual —perfil ajeno, onboarding y Home— y de la ficha de contexto de `Community`,
que ya fijaba el agregado y sus dos invariantes.

**Implementación:** `DONE` (2026-09-24). `GET`, `PUT` y `DELETE
/api/v1/users/{userId}/subscription`, **los primeros endpoints de
`Community`**, sobre la tabla `author_subscription` que ya existía. Una tabla
nueva y su migración, pero en `User`: la proyección.

### El rodeo por la cola no es ceremonia

Era tentador publicar un contrato `Followers` en `Community` y que
`CheckAuthorAudience` lo llamase: menos piezas, respuesta inmediata, sin
proyección que mantener. Lo prohíbe la regla 4 de
[`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md),
y aquí se ve por qué existe. `Community` ya llama a `RegisteredUsers` de `User`
para dejar seguir a alguien; un contrato en sentido contrario cerraría el ciclo
de llamadas entre los dos contextos, y `CheckAuthorAudience` —que es un
contrato publicado que responden `Work` y `Feedback`— acabaría llamando a otro
contexto en mitad de su respuesta.

Con la proyección, cada contexto responde con lo suyo. El precio es la
**consistencia en diferido**, y está probado tal cual: las pruebas siguen a
alguien por su endpoint y **entregan la cola** antes de comprobar el efecto.

### Qué hace idempotente a cada mitad

- Seguir dos veces no crea una segunda suscripción ni publica un segundo
  hecho. Con `POST` habría tocado devolver `409` a la segunda, que es decirle a
  alguien que ha fallado cuando lo que pedía ya se cumple.
- Dejar de seguir a quien no se sigue no falla **y no publica nada**: anunciar
  un cambio que no ocurrió hace trabajar a cada proyección para nada.
- La fila de la proyección es el par `(author_id, follower_id)`, así que
  reprocesar un hecho escribe lo mismo. No hace falta un registro de eventos
  procesados como el de `Credits`: allí se protege de **aplicar dos veces un
  efecto** —un abono— y aquí no hay efecto que acumular, hay un estado que
  afirmar.

### Lo que el seguimiento no hace

No abre obras, no concede acceso de lector beta y no inicia conversación
(`RN-7`). Y no anula lo que su dueño cerró: con el perfil en `NOBODY` o los
comentarios en `NOBODY`, seguir no cambia nada. El seguimiento decide **quién
entra en una audiencia**, no cuál eligió su titular. Hay una prueba de cada
cosa, porque es el error que alguien cometerá al añadir la siguiente audiencia.

### Lo que queda pendiente y por qué

- **`C-22` es para producto y es la importante**: con seguimiento unilateral,
  «solo mis seguidores» significa «cualquiera que pulse Seguir». Hoy el ajuste
  hace literalmente lo que promete; si producto quería algo más fuerte, la
  salida es aprobación de seguimiento o `FOLLOWERS` como relación mutua, y
  ninguna de las dos cabe en esta ficha.
- El buscador de a quién invitar (`FEAT-RDG-006`) sigue sin enseñar a quien
  tiene el perfil en `FOLLOWERS`, ni a sus seguidores: su contrato
  `ReaderDirectory` no recibe quién busca (`C-24`).
- `Notification` no consume `AuthorSubscribed` todavía, así que a nadie le
  llega el aviso de que le siguen. El hecho ya viaja; solo falta quien lo
  escuche.
- Las listas de seguidos y seguidores son `FEAT-COM-027`, y con ellas se
  decidirá `CM-14` —si son públicas—. Esta ficha no lo decide, y por eso el
  `GET` responde solo por quien pregunta.
