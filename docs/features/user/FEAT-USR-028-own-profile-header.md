---
id: FEAT-USR-028
title: Mi perfil — cabecera, datos y contadores
context: User
concept: Profile
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-22 (capturas de «Mi perfil»)
  - docs/ui/my-profile.md
endpoints: [GET /me/profile, PATCH /me/profile, PUT /me/profile/avatar, PUT /me/profile/cover]
events: [UserProfileUpdated]
depends_on: [FEAT-USR-022]
updated: 2026-09-22
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
| Avatar | Sí, lápiz | `User` |
| Nombre | No desde aquí (`FEAT-USR-008`) | `User` |
| `@identificador` | — | **Sin definir**, ver `P-1` |
| Descripción | Sí, en línea | `User` |
| Insignia de nivel | No | `FEAT-USR-031` |
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
  EXIF**, que pueden contener geolocalización (`file-uploads.md`).
- `RN-6` La descripción se sanea: no admite HTML arbitrario.
- `RN-7` Solo el titular edita su propio perfil. No existe edición de perfiles ajenos.
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

- [ ] El perfil propio devuelve nombre, descripción, avatar, portada y los cuatro contadores.
- [ ] Los contadores proceden de contratos de consulta, no de consultas a tablas ajenas.
- [ ] Si un contador falla, el perfil se devuelve igualmente con ese campo marcado.
- [ ] La descripción se almacena saneada frente a inyección de HTML.
- [ ] Las imágenes subidas pierden sus metadatos EXIF.
- [ ] No se puede editar el perfil de otro usuario.
- [ ] Editar con la cuenta sin activar devuelve `403 ACCOUNT_NOT_ACTIVATED`.
- [ ] Consultar el perfil propio funciona con la cuenta sin activar.
- [ ] Cambiar la descripción publica `UserProfileUpdated`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **P-1** | ¿Existe `@identificador`? El diseño muestra `@bealonso` | **Bloqueante.** Ver [`../../ui/my-profile.md`](../../ui/my-profile.md) |
| P-5 | ¿Es esta la «página de autor» de `FEAT-USR-015`? | Si no, hay dos perfiles que mantener |
| P-12 | ¿Los contadores se componen en cada petición o viven en un read model? | Rendimiento del perfil |
| P-13 | ¿Hay límites de tamaño y proporción para portada y avatar? | Validación de subida |
| P-14 | ¿Longitud máxima de la descripción? | Validación |
| P-11 | ¿Qué devuelve la vista pública de este perfil? | Sin capturas |

## Estado

**Especificación:** `DRAFT`. `P-1` debe resolverse antes de `APPROVED`: si existe un
identificador único, cambia el modelo y probablemente las URLs de perfil.

**Implementación:** `TODO`.
