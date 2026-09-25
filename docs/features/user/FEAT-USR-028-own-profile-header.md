---
id: FEAT-USR-028
title: Mi perfil — cabecera, datos y contadores
context: User
concept: Profile
actors: [User]
spec_status: APPROVED
impl_status: PARTIAL
priority: P1
sources:
  - conversation:2026-09-22 (capturas de «Mi perfil»)
  - docs/ui/my-profile.md
endpoints: [GET /me/profile, PATCH /me/profile, PUT /me/profile/avatar, PUT /me/profile/cover]
events: [UserProfileUpdated]
depends_on: [FEAT-USR-022]
updated: 2026-09-25
---

# FEAT-USR-028 — Mi perfil: cabecera, datos y contadores

## Resumen

La cabecera del perfil propio: portada, avatar, nombre, identificador, descripción editable
y cuatro contadores de actividad.

Es la primera pantalla donde el usuario ve **su huella en la plataforma**, y por eso concentra
agregados de tres bounded contexts distintos.

## Elementos

| Elemento | Editable | Origen |
|---|---|---|
| Imagen de portada | Sí, lápiz | `User`. **Concepto nuevo** |
| Avatar | Sí, lápiz | `User`. Subir, reencuadrar y eliminar en [`FEAT-USR-037`](FEAT-USR-037-upload-profile-photo.md) |
| Nombre | No desde aquí (`FEAT-USR-008`) | `User` |
| `@identificador` | Sí, una vez cada 30 días (`FEAT-USR-034`) | `User` — es el `Username` |
| Descripción | Sí, en línea | `User` |
| Compartir | — | `FEAT-USR-032` |

## Contadores

| Contador | Qué cuenta | Contexto propietario |
|---|---|---|
| Seguidos | Autores a los que sigue | `Community` |
| Seguidores | Quienes le siguen | `Community` |
| Relatos | Obras propias | `Work` |
| Correcciones | Feedback que ha dado | `Feedback` |

### Cómo se obtienen sin romper las fronteras

Los cuatro pertenecen a tres contextos ajenos a `User`. Mismo patrón que
[`FEAT-USR-027`](FEAT-USR-027-session-context.md):

- `RN-1` Cada contexto expone un **contrato de consulta explícito** que devuelve el contador.
  `User` no consulta sus tablas.
- `RN-2` La composición vive en `Infrastructure` y solo ensambla.
- `RN-3` Si un contador no está disponible, se devuelve marcado como tal y el perfil se pinta
  igual. Un contador caído no debe tumbar la pantalla.

Alternativa a valorar si el perfil se consulta mucho: mantenerlos como **read model**
alimentado por eventos (`AuthorSubscribed`, `WorkPublished`, `FeedbackSubmitted`). Es lo que
ya se propone para los contadores de las tarjetas de autor (`FEAT-COM-016`). Ver `P-12`.

## Reglas de negocio

- `RN-4` Portada, avatar y descripción son **datos públicos**: aparecen en el perfil que ven
  los demás.
- `RN-5` Las imágenes se procesan al subirlas: redimensionado y **eliminación de metadatos
  EXIF**, que pueden contener geolocalización (`file-uploads.md`). El detalle del avatar está
  en [`FEAT-USR-037`](FEAT-USR-037-upload-profile-photo.md).
- `RN-6` La descripción se sanea: no admite HTML arbitrario.
- `RN-7` La descripción está limitada a **300 caracteres** y es **el mismo campo** que la
  «Biografía» de Configuración ([`FEAT-USR-008`](FEAT-USR-008-edit-profile.md)). Los dos
  puntos de edición escriben en el mismo sitio, con el mismo límite y el mismo saneado.
- `RN-7b` Solo el titular edita su propio perfil. No existe edición de perfiles ajenos.
- `RN-8` Los contadores son informativos y **nunca se usan para autorizar** nada.
- `RN-9` Editar el perfil requiere la cuenta activada (`FEAT-USR-025`): es escritura.
- `RN-10` Ver el perfil propio funciona con la cuenta sin activar.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Ver mi perfil | `GET /me/profile` | `getMyProfile` |
| Editar descripción y datos | `PATCH /me/profile` | `updateMyProfile` |
| Cambiar avatar | `PUT /me/profile/avatar` | `updateAvatar` |
| Cambiar portada | `PUT /me/profile/cover` | `updateCover` |

Es el mismo endpoint que usa Configuración. `PATCH` y no `PUT` precisamente porque la edición
en línea toca un solo campo.

`GET /me/profile` devuelve la cabecera con sus contadores. Las pestañas se piden aparte y
paginadas: cargarlas todas de golpe haría inútil la paginación.

## Eventos

| Evento | Cuándo | Consumidores |
|---|---|---|
| `UserProfileUpdated` | Cambian datos públicos | `Community` (read models de tarjetas de autor) |

El evento lleva los campos modificados, no el perfil entero.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `user` | `avatar_url`, `cover_url`, `description` |

Las imágenes se guardan con el puerto `FileStorage`, nunca en la base de datos.

## Criterios de aceptación

- [x] El perfil propio devuelve nombre, descripción, avatar, portada y los cuatro contadores.
- [x] Los contadores proceden de contratos de consulta, no de consultas a tablas ajenas.
- [x] Si un contador falla, el perfil se devuelve igualmente con ese campo marcado.
- [x] La descripción se almacena saneada frente a inyección de HTML. *De [`FEAT-USR-008`](FEAT-USR-008-edit-profile.md), donde se implementó.*
- [ ] Las imágenes subidas pierden sus metadatos EXIF. *No hay subida de imágenes todavía: el avatar es [`FEAT-USR-037`](FEAT-USR-037-upload-profile-photo.md) y la portada no tiene ficha propia.*
- [x] No se puede editar el perfil de otro usuario. *Por construcción: los endpoints son `/me/profile` y no admiten decir de quién.*
- [x] Editar con la cuenta sin activar devuelve `403 ACCOUNT_NOT_ACTIVATED`.
- [x] Consultar el perfil propio funciona con la cuenta sin activar.
- [x] Cambiar la descripción publica `UserProfileUpdated`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| P-1 | ¿Existe `@identificador`? | **Resuelta:** es el `Username` (`FEAT-USR-033`) |
| P-5 | ¿Es esta la «página de autor» de `FEAT-USR-015`? | Si no, hay dos perfiles que mantener |
| P-12 | ¿Los contadores se componen en cada petición o viven en un read model? | **Resuelta por ahora:** en cada petición, tres consultas de conteo sobre índices. Ver abajo |
| P-13 | ¿Hay límites de tamaño y proporción para la **portada**? | Los del avatar están definidos (`FEAT-USR-037`); los de la portada no (`F-5`) |
| P-14 | ¿Longitud máxima de la descripción? | **Resuelta:** 300 caracteres (`FEAT-USR-008` `RN-3`) |
| P-11 | ¿Qué devuelve la vista pública de este perfil? | Sin capturas |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Los contadores son cinco desde 2026-09-25**, no cuatro: se añade «recibidos en propinas»
([`FEAT-CRD-017`](../credits/FEAT-CRD-017-author-tip.md) `RN-3c`), que contesta `Community`
porque `Credits` no publica contratos. Todo lo que sigue vale igual para la quinta.

**Implementación:** `PARTIAL` (2026-09-24). **Los cuatro contadores**, sobre el `GET` y el
`PATCH /me/profile` que ya existían. Sin tabla nueva y sin migración.

Falta **todo lo que es subir una imagen**: portada y avatar. No es un olvido de esta tanda —
no existe aún ninguna subida de ficheros en el backend, el avatar tiene ficha propia
([`FEAT-USR-037`](FEAT-USR-037-upload-profile-photo.md)) y la portada no tiene ninguna, ni
límites de tamaño o proporción decididos (`P-13`, `F-5`). El resto de la cabecera —nombre,
`@usuario`, descripción— ya estaba, repartido entre `FEAT-USR-008` y `FEAT-USR-034`.

### Tres contratos nuevos, uno por contexto

`RN-1` pedía contratos de consulta explícitos, y son tres porque son tres dueños:
`SubscriptionCounts` en `Community`, `AuthoredWorkCount` en `Work` y
`DeliveredCorrectionCount` en `Feedback`. Los tres devuelven **una cifra, nunca la lista**, y
eso no es economía sino una regla: `FEAT-USR-014` `U-17` decide a propósito que el contador
de correcciones sea público y la lista no, y un contrato que devolviera correcciones haría esa
decisión imposible de sostener. Lo mismo con las obras — servirlas aquí sería enseñar
borradores ajenos por una puerta lateral.

Seguidos y seguidores viajan **juntos en una sola respuesta**: se enseñan juntos, y separarlos
serían dos viajes para dos números pegados.

### Un contador caído no tumba la pantalla

`RN-3`, y es la regla que más código tiene detrás. Cada contrato se pregunta por separado y
un fallo se convierte en `null` —«no se ha podido saber»—, que **no es lo mismo que cero**.
El perfil se devuelve igual con las cifras que sí se supieron, y el error se registra en el
log, que es donde sirve de algo.

Sin esto, la cabecera del perfil propio dependería de que los cuatro contextos estuvieran
sanos a la vez: cuatro veces más probabilidades de no poder abrir tu propia pantalla.

La prueba que lo defiende sustituye el contrato por uno que revienta. Romper su tabla habría
sido más realista y habría probado menos: dentro de la transacción del test envenenaría
también las consultas de los otros tres contadores, que es justo lo que hay que ver
sobrevivir.

### `P-12`, resuelta por ahora: se componen en cada petición

Tres consultas de conteo sobre índices, en la lectura que abre la pantalla. La alternativa —un
read model alimentado por `AuthorSubscribed`, `WorkPublished` y `FeedbackSubmitted`— llegará
cuando las cifras digan que hace falta, y no antes: trae su propia deuda, que es un contador
capaz de quedarse atrás sin que nadie lo note.

### Los contadores no se filtran por privacidad, y hay que saberlo

`followers` cuenta a todo el mundo, también a quien tiene el perfil cerrado, así que puede ser
**mayor que las filas** que devuelve `listSubscribers` ([`FEAT-COM-027`](../community/FEAT-COM-027-following-and-followers.md)
`RN-3`). Es deliberado: filtrar la cifra obligaría a resolver la visibilidad de cada seguidor
para pintar un número y la haría **distinta para cada visitante**, con lo que el contador
dejaría de ser un dato de esa persona para ser uno de quien mira.

`works` cuenta las obras de cualquier estado porque es el perfil propio. Si algún día sale en
el perfil ajeno hará falta otra pregunta: contar allí los borradores diría cuánto tiene
alguien sin publicar.
