---
id: FEAT-USR-037
title: Gestionar la foto de perfil — subir, editar y eliminar
context: User
concept: Profile
actors: [User]
spec_status: APPROVED
impl_status: TODO
priority: P2
sources:
  - conversation:2026-09-22 (capturas del flujo de foto de perfil)
  - docs/ui/profile-photo.md
endpoints: [PUT /me/profile/avatar, DELETE /me/profile/avatar]
events: [UserProfileUpdated]
depends_on: [FEAT-USR-028]
updated: 2026-09-24
---

# FEAT-USR-037 — Gestionar la foto de perfil: subir, editar y eliminar

## Resumen

El usuario gestiona su avatar desde el lápiz del perfil. Si no tiene foto, se abre el modal de
subida; si ya la tiene, un concentrador con tres acciones:

| Acción | Qué hace |
|---|---|
| **Editar** | Reabre el editor para reencuadrar la foto actual |
| **Cambiar foto** | Vuelve al modal de subida para elegir otra |
| **Eliminar** | Pide confirmación y devuelve el avatar por defecto |

El editor permite **escalar, girar y desplazar** sobre un recorte circular. El resultado
aparece de inmediato en el perfil, la cabecera y la caja de publicación.


> **Dos entradas, un solo flujo.** Además del lápiz del avatar, se llega desde
> **Configuración › Perfil** ([`settings.md`](../../ui/settings.md)). Aquellas capturas
> muestran una zona de arrastre sin modal de recorte: es un **error de maqueta** (`S-6`).
> `RN-4b` sigue vigente en las dos entradas.

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
- `RN-4b` El recorte lo aplica **el cliente**: el servidor no recorta ni gira, solo normaliza
  y sanea.
- `RN-4c` Se conserva **el original además de la recortada**, para que «Editar» pueda alejar,
  desplazar y girar sin degradar la imagen. Ambos ficheros se procesan según `RN-3`.
- `RN-12` **Eliminar** la foto devuelve al usuario al avatar por defecto y **borra los dos
  ficheros**: no se conservan copias de una foto que el usuario ha retirado.
- `RN-13` Eliminar una foto que no existe no es un error: la operación es idempotente.
- `RN-14` El avatar por defecto no es un fichero del usuario: es el marcador de posición de la
  interfaz.
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

**Decidido:** el navegador aplica la escala, la rotación y el desplazamiento, y sube **la
imagen final, ya cuadrada**. El servidor no recorta ni gira: solo normaliza y sanea.

- No hay que reproducir en servidor la vista previa que vio el usuario, ni arriesgarse a que
  difieran por redondeos.
- **Desaparece el problema de la orientación EXIF al girar**, porque el servidor nunca rota.
- El servidor **sigue reprocesando la imagen** (`RN-3`). Que venga recortada del navegador no
  garantiza que no conserve metadatos: recortar y sanear son cosas distintas.

## «Editar» obliga a conservar el original

Al decidir que el recorte lo hace el cliente se dio por supuesto que bastaría con guardar la
imagen recortada. **La acción «Editar» del diseño lo desmiente.**

| Si solo se guarda la recortada | Si se guarda también el original |
|---|---|
| Editar solo puede recortar **dentro** de una imagen de 180 px | Se puede alejar, desplazar y girar con libertad |
| Lo recortado una vez no se recupera nunca | Se recupera todo el encuadre |
| Cada edición degrada la calidad | Siempre se parte del original |

**Propuesta: guardar las dos.** El original es material de trabajo del editor; la recortada es
la que se muestra. El cliente sigue recortando —esa decisión no cambia—, pero sube **dos
ficheros** en lugar de uno.

Conviene además guardar el encuadre aplicado (escala, rotación y desplazamiento) para que
«Editar» reabra el editor donde el usuario lo dejó. Ver `F-10`.

## Flujo principal

1. El usuario pulsa el lápiz del avatar.
2. Elige un fichero, o hace una foto con la cámara.
3. Se valida el tipo en cliente; si no es imagen, se avisa y el modal sigue abierto.
4. Encuadra **en el navegador**: escala, rotación y desplazamiento.
5. Guarda: el cliente sube el original y la imagen recortada.
6. El servidor valida tipo y tamaño, reprocesa ambas y las almacena.
7. Devuelve la URL nueva.
8. El cliente refresca el avatar en perfil, cabecera y caja de publicación.

### Editar una foto existente

1. El usuario abre el concentrador «Foto de perfil» y pulsa «Editar».
2. El editor carga **el original** y, si se guardó, el encuadre anterior.
3. El usuario reencuadra y guarda.
4. Se sube una imagen recortada nueva. **El original no cambia.**

### Eliminar la foto

1. El usuario pulsa «Eliminar» y confirma en el modal de advertencia.
2. El servidor borra los dos ficheros y limpia la referencia.
3. El avatar vuelve al de por defecto en las tres vistas.

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
| Eliminar sin tener foto | Éxito idempotente (`RN-13`) | `204` |
| Editar sin original guardado | **Sin definir** para las fotos anteriores a `RN-4c` (`F-13`) | Pendiente |

El caso HEIC no es teórico: «Usar la cámara» en iOS produce ese formato por defecto y los
navegadores no lo muestran. Si no se convierte en servidor, las fotos hechas desde iPhone
fallarán o se verán rotas.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Subir o reencuadrar la foto | `PUT /me/profile/avatar` | `updateAvatar` |
| Eliminar la foto | `DELETE /me/profile/avatar` | `deleteAvatar` |

`PUT` y no `POST`: el avatar es un recurso único del usuario y volver a subirlo lo reemplaza.

La subida es `multipart/form-data` con la imagen recortada y, la primera vez, también el
original. Al reencuadrar basta con la recortada: el original ya está guardado.

**Subir y reencuadrar usan el mismo endpoint.** Para el servidor son la misma operación:
llega una imagen cuadrada nueva y sustituye a la anterior. La distinción entre «Cambiar» y
«Editar» es de interfaz.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `user` | `avatar_url`, `avatar_original_url`, `avatar_crop` |

Las imágenes viven en el almacenamiento externo; en base de datos solo quedan las
referencias. `avatar_crop` guarda escala, rotación y desplazamiento para reabrir el editor.

`avatar_original_url` **no se expone en el perfil público**: es material de trabajo del
editor, no la imagen que el usuario ha elegido mostrar.

## Se conserva la imagen original

**Decidido** (`F-2`): además de la imagen recortada, se guarda **la original tal como la subió
el usuario**.

Es lo que hace posible el botón «Editar», que reabre el editor con el recorte anterior: sin la
original, «editar» solo podría recortar sobre lo ya recortado y cada pasada degradaría la
imagen un poco más.

| Se guarda | Para qué |
|---|---|
| `avatar_url` | Lo que se muestra |
| `avatar_original_url` | Reabrir el editor sin pérdida |
| `avatar_crop` | Escala, rotación y desplazamiento anteriores |

Tiene un coste que conviene asumir a sabiendas: **se almacena el doble**, y la original puede
ser mucho mayor que el recorte. Por eso conviene normalizar su tamaño máximo al subirla, no
guardarla tal cual venga del móvil.

Y una consecuencia de privacidad: al eliminar la foto **hay que borrar las dos**. Dejar la
original huérfana es guardar una imagen personal que el usuario cree haber borrado.

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
- [ ] Se conservan el original y la imagen recortada.
- [ ] «Editar» parte del original, no de la imagen ya recortada.
- [ ] Reencuadrar dos veces seguidas no degrada la calidad respecto a la primera.
- [ ] El original no aparece en el perfil público ni en ninguna respuesta dirigida a terceros.
- [ ] Eliminar la foto devuelve al avatar por defecto en las tres vistas.
- [ ] Eliminar la foto borra los dos ficheros del almacenamiento.
- [ ] Eliminar sin tener foto devuelve éxito, no error.
- [ ] No se puede eliminar la foto de otro usuario.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **F-3** | ¿Se acepta HEIC? La cámara de iOS lo produce por defecto | Sin conversión, las fotos desde iPhone fallan |
| F-1 | ¿Recorta el cliente o el servidor? | **Resuelta:** el cliente |
| **F-2** | **¿Se conserva el original?** «Editar» lo exige para poder alejar y desplazar | Sin él, editar solo recorta hacia dentro y degrada la imagen |
| F-4 | ¿Se puede eliminar la foto? | **Resuelta:** sí, con confirmación, y vuelve al avatar por defecto |
| F-10 | ¿Se guarda el encuadre para reabrir el editor donde se dejó? | Sin él, «Editar» empieza desde una posición por defecto |
| F-11 | ¿Hay aviso de confirmación tras eliminar? | Las demás acciones sí lo tienen |
| F-13 | ¿Qué hace «Editar» con fotos subidas antes de conservar originales? | Solo relevante si se implanta en dos fases |
| F-5 | ¿El mismo flujo sirve para la imagen de portada? | Otras proporciones y otras recomendaciones |
| F-7 | ¿Hay límite de cambios por periodo? | Un avatar es un vector de contenido inapropiado y no hay moderación (`V-1`) |
| F-9 | ¿Se conservan varios tamaños del avatar? | Las tarjetas de autor lo muestran a 40–60 px; servir 180 px en todas es desperdicio |

## Estado

**Especificación:** `APPROVED` (2026-09-24). `F-2` resuelta: se conserva la original para poder reeditar.
`F-3` (HEIC) es un detalle de formatos admitidos.

**Implementación:** `TODO`.
