---
id: FEAT-USR-032
title: Compartir el perfil
context: User
concept: Profile
actors: [User, Guest]
spec_status: APPROVED
impl_status: DONE
priority: P3
sources:
  - docs/ui/my-profile.md
  - docs/ui/user-profile.md
  - docs/features/work/FEAT-WRK-011-share-a-work-outside.md
updated: 2026-09-26
endpoints:
  - GET /users/{userId}/share
events: []
depends_on: [FEAT-USR-014, FEAT-USR-038, FEAT-WRK-011]
---

# FEAT-USR-032 — Compartir el perfil

## Resumen

La tercera y última tarjeta de previsualización, después de la de una obra
([`FEAT-WRK-011`](../work/FEAT-WRK-011-share-a-work-outside.md)) y la de una publicación
([`FEAT-COM-020`](../community/FEAT-COM-020-share-a-post-outside.md)). Lo que se comparte aquí
es **la página de autor**, que es el perfil (`P-5`).

## Sin enlace generado, como las otras dos

Lo que se comparte es la **dirección canónica** del perfil, la de siempre. No hay token, no
caduca, no se revoca y no abre nada que no fuera ya público. La decisión se tomó en
`FEAT-WRK-011` y aquí no se reabre: un enlace con credencial dentro circulando por redes
sociales es exactamente la puerta trasera que la privacidad cierra.

Lo que esta ficha añade son los **metadatos** con los que WhatsApp, Twitter o LinkedIn pintan
la previsualización: título, descripción e imagen.

## La diferencia con las otras dos: aquí la persona **es** el contenido

`ShareCard` lleva escrito que una tarjeta «no lleva a nadie dentro»: la de una obra habla de la
obra, no de quien la escribió, porque poner ahí el nombre obligaría a resolver la privacidad de
alguien para un rastreador anónimo.

Aquí eso no aplica y conviene decir por qué, para que nadie lea una contradicción donde hay una
regla: **el perfil es el contenido**, y el nombre, la foto y la biografía son exactamente lo
que su titular publicó para que se viera.

La regla sigue siendo la misma, de hecho: **la tarjeta solo existe para un perfil que
cualquiera puede ver**. Y no se comprueba aquí — se pide el perfil **sin espectador**, con
`GetProfileByUserIdHandler` y `viewerId: null`, que es la misma puerta que atiende a un
visitante anónimo.

| Ajuste de privacidad | Qué devuelve la tarjeta |
|---|---|
| `EVERYONE` | La tarjeta |
| `FOLLOWERS` | `404`. Un rastreador no sigue a nadie |
| `NOBODY` | `404` |
| Cuenta eliminada | `404` |

Reescribir la comprobación aquí habría sido la manera de que un día se quedara corta: bastaría
con que la regla de visibilidad cambiara en un sitio y no en el otro para que el buscador
indexara un perfil cerrado.

## Qué lleva la tarjeta

| Campo | De dónde sale |
|---|---|
| `url` | La dirección canónica del perfil, de `PROFILE_SHARE_URL_TEMPLATE` |
| `title` | El **nombre**, y `@username` si todavía no hay nombre |
| `description` | La biografía, recortada a 200 caracteres. Sin biografía, `null` |
| `imageUrl` | El avatar, **absoluto**. Sin avatar, `null` |

### Por qué el avatar tiene que ser absoluto y el resto de la API no

En todas las demás respuestas el avatar viaja como `/api/v1/media/…`, y está bien: quien lo
pinta es un cliente que ya sabe contra qué origen habla.

Una etiqueta `og:image` la lee un rastreador que **no tiene ese contexto**. Una ruta relativa
ahí es una imagen rota en la previsualización, que es lo único que esta ficha produce. De ahí
`MEDIA_BASE_URL`: el origen público desde el que se sirven los ficheros, que es lo único que
falta para componerla.

Es también la primera vez que hace falta, y por eso no existía.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| Cualquiera, **sin sesión** | Pedir la tarjeta de un perfil | Que el perfil sea visible para cualquiera |

Tiene que ser público: quien lo pide es un rastreador. Y se cachea en público cinco minutos,
como las otras dos, porque no hay nada de nadie en la respuesta que no fuera ya visible.

## Reglas de negocio

- `RN-1` **No se genera ningún enlace.** La `url` es la dirección canónica del perfil.
- `RN-2` Solo hay tarjeta para un perfil **visible para cualquiera**. Se decide preguntando por
  el perfil sin espectador, no repitiendo la regla.
- `RN-3` Un perfil que no existe y uno que no se puede enseñar responden **lo mismo**.
- `RN-4` El título nunca va vacío: sin nombre, el `@username`.
- `RN-5` La imagen es **absoluta** o no va.
- `RN-6` No publica eventos y no cuenta nada: compartir no es un hecho de negocio, y llevar la
  cuenta de cuántas veces se comparte un perfil sería otra ficha, con su decisión de privacidad.

## Contrato de API

| Operación | Método y ruta | `operationId` | Documento |
|---|---|---|---|
| Tarjeta del perfil | `GET /users/{userId}/share` | `getProfileShareCard` | `openapi/paths/profiles.yaml` |

Misma forma de respuesta que las otras dos —`url`, `title`, `description`, `imageUrl`—, con
`ShareCardBody`. Tres formas distintas para el mismo destino serían tres plantillas de Open
Graph que mantener.

## Eventos

Ninguno.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Ninguno. No hay tabla nueva ni migración: la tarjeta se compone de lo que ya está.

## Configuración

| Variable | Para qué |
|---|---|
| `PROFILE_SHARE_URL_TEMPLATE` | Dónde vive un perfil en el frontend. `{id}` se sustituye |
| `MEDIA_BASE_URL` | El origen público de los ficheros, para componer una `og:image` absoluta |

## Estado

**Especificación:** `APPROVED` (2026-09-26). Redactada junto con la implementación, sobre la
maqueta y las decisiones ya tomadas.

**Implementación:** `DONE` (2026-09-26). La tarjeta para compartir, que **se genera contra el perfil público** —el que vería un
desconocido— y no contra el de quien la pide. Compartir un perfil no puede enseñar de él más
de lo que enseña su enlace.
