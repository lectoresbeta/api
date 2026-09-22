---
id: FEAT-USR-037
title: Subir y recortar la foto de perfil
context: User
concept: Profile
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P2
sources:
  - conversation:2026-09-22 (capturas del flujo de foto de perfil)
  - docs/ui/profile-photo-upload.md
endpoints: [PUT /me/profile/avatar]
events: [UserProfileUpdated]
depends_on: [FEAT-USR-028]
updated: 2026-09-22
---

# FEAT-USR-037 — Subir y recortar la foto de perfil

## Resumen

El usuario sube su avatar desde el lápiz del perfil, lo encuadra con zoom y giro sobre un
recorte circular, y lo guarda. El resultado aparece de inmediato en el perfil, la cabecera y
la caja de publicación.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Cambiar **su propia** foto | Sesión iniciada y cuenta activada (`FEAT-USR-025`) |

## Reglas de negocio

- `RN-1` Tamaño máximo **2 MB**. El límite lo aplica el servidor; que el cliente lo anuncie
  no es una garantía.
- `RN-2` Solo se aceptan imágenes, **determinado por el contenido del fichero**, no por su
  extensión ni por el `Content-Type` que declare el cliente.
- `RN-3` La imagen se **reprocesa siempre** en el servidor: se normaliza el formato, se
  redimensiona y se **eliminan los metadatos EXIF**. Que el cliente ya la haya recortado no
  exime de esto: los metadatos pueden incluir la geolocalización de la foto.
- `RN-4` El resultado se almacena cuadrado. `180 × 180 px` es la referencia del diseño; el
  recorte circular es una máscara de presentación, **la imagen guardada es cuadrada**.
- `RN-4b` El recorte lo aplica **el cliente**: el endpoint recibe la imagen final y no admite
  parámetros de encuadre. El servidor no recorta ni gira, solo normaliza y sanea.
- `RN-5` Las dimensiones que anuncia el modal son una **recomendación**: una imagen mayor o
  menor se acepta y se redimensiona.
- `RN-6` El fichero se guarda mediante el puerto `FileStorage`, nunca en la base de datos.
- `RN-7` El nombre original del fichero se descarta: no se usa como ruta ni se devuelve.
- `RN-8` Subir una foto nueva **reemplaza** la anterior. La anterior deja de ser accesible.
- `RN-9` Solo el titular cambia su propia foto.
- `RN-10` La respuesta devuelve la URL de la imagen ya procesada.
- `RN-11` Se publica `UserProfileUpdated` para que los read models que muestran el avatar se
  actualicen.

`RN-3` es la regla que no se puede saltar por comodidad. Es tentador confiar en una imagen
que el navegador ya ha recortado y guardarla tal cual; eso publicaría las coordenadas de
donde se tomó la foto.

## El recorte lo hace el cliente

**Decidido:** el navegador aplica el zoom y el giro y sube **la imagen final, ya cuadrada**.
El servidor recibe un fichero y nada más: ningún parámetro de encuadre.

Consecuencias:

- El endpoint es una subida simple. No hay que reproducir en servidor la vista previa que vio
  el usuario, ni arriesgarse a que difieran por redondeos.
- **Desaparece el problema de la orientación EXIF al girar.** El recorte del navegador la
  resuelve antes de subir, así que el servidor nunca tiene que rotar nada.
- El servidor **sigue reprocesando la imagen** (`RN-3`). Que venga recortada del navegador no
  es garantía de que no conserve metadatos: el recorte y el saneamiento son cosas distintas.
- **No se conserva el original.** Reencuadrar más adelante obliga a volver a subir la foto.
  Es coherente con el diseño, donde el lápiz reabre «Añadir foto» desde el principio.

Lo último es lo único que se pierde con esta opción, y se puede revertir subiendo también el
original si algún día compensa (`F-2`).

## Flujo principal

1. El usuario pulsa el lápiz del avatar.
2. Elige un fichero, o hace una foto con la cámara.
3. Se valida el tipo en cliente; si no es imagen, se avisa y el modal sigue abierto.
4. Encuadra con zoom y giro **en el navegador**.
5. Guarda: el cliente genera la imagen recortada y la sube.
6. El servidor valida tipo y tamaño, reprocesa la imagen y la almacena.
7. Devuelve la URL nueva.
8. El cliente refresca el avatar en perfil, cabecera y caja de publicación.

El paso 8 abarca tres sitios porque la cabecera se pinta con el contexto de sesión
(`FEAT-USR-027`). Si no se refresca, el usuario verá el avatar nuevo abajo y el viejo arriba.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| El fichero no es una imagen | Se rechaza. Diseño: «No se acepta el tipo de archivo. Selecciona una imagen.» | `422` con `code: UNSUPPORTED_FILE_TYPE` |
| Supera 2 MB | Se rechaza | `413` con `code: FILE_TOO_LARGE` |
| Imagen corrupta o ilegible | Se rechaza | `422` con `code: INVALID_IMAGE` |
| Formato válido pero no soportado, como HEIC | **Sin definir** (`F-3`) | Pendiente |
| Fallo del almacenamiento | Error, sin dejar el perfil a medias | `502` |
| Cuenta sin activar | Se rechaza | `403` con `code: ACCOUNT_NOT_ACTIVATED` |

El caso HEIC no es teórico: «Usar la cámara» en iOS produce ese formato por defecto y los
navegadores no lo muestran. Si no se convierte en servidor, las fotos hechas desde iPhone
fallarán o se verán rotas.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Cambiar foto de perfil | `PUT /me/profile/avatar` | `updateAvatar` |

`PUT` y no `POST`: el avatar es un recurso único del usuario y volver a subirlo lo reemplaza.

La subida es `multipart/form-data` con **un único fichero**: la imagen ya recortada. No lleva
parámetros de zoom ni de giro.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `user` | `avatar_url` |

La imagen vive en el almacenamiento externo. En base de datos solo queda la referencia.

## Criterios de aceptación

- [ ] Subir una imagen válida actualiza el avatar y devuelve su URL.
- [ ] Un fichero que no es imagen se rechaza con `422`, aunque tenga extensión `.jpg`.
- [ ] Un fichero de más de 2 MB se rechaza con `413`.
- [ ] La imagen almacenada **no conserva metadatos EXIF**.
- [ ] La imagen almacenada es cuadrada.
- [ ] El endpoint no acepta parámetros de encuadre: el recorte ya viene hecho.
- [ ] Una imagen recortada por el navegador tampoco conserva metadatos EXIF tras guardarse.
- [ ] Una imagen de 4000 × 3000 px se acepta y se redimensiona.
- [ ] El nombre original del fichero no aparece en la URL ni en la respuesta.
- [ ] Subir una foto nueva reemplaza la anterior.
- [ ] No se puede cambiar la foto de otro usuario.
- [ ] Con la cuenta sin activar devuelve `403`.
- [ ] Tras subirla, el avatar aparece actualizado en el perfil y en la cabecera.
- [ ] Se publica `UserProfileUpdated`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **F-3** | ¿Se acepta HEIC? La cámara de iOS lo produce por defecto | Sin conversión, las fotos desde iPhone fallan |
| F-1 | ¿Recorta el cliente o el servidor? | **Resuelta:** el cliente. El endpoint recibe la imagen final |
| F-2 | ¿Se conserva el original para reencuadrar sin volver a subir? | **No**, como consecuencia de `F-1`. Se podría añadir subiendo también el original |
| F-4 | ¿Se puede eliminar la foto y volver al avatar por defecto? | Sin diseño |
| F-5 | ¿El mismo flujo sirve para la imagen de portada? | Otras proporciones y otras recomendaciones |
| F-7 | ¿Hay límite de cambios por periodo? | Un avatar es un vector de contenido inapropiado y no hay moderación (`V-1`) |
| F-9 | ¿Se conservan varios tamaños del avatar? | Las tarjetas de autor lo muestran a 40–60 px; servir 180 px en todas es desperdicio |

## Estado

**Especificación:** `DRAFT`. Resuelta `F-1`. Para llegar a `APPROVED` falta decidir si se
acepta HEIC (`F-3`), que es el formato por defecto de la cámara en iOS.

**Implementación:** `TODO`.
