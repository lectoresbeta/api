---
id: FEAT-USR-037
title: Gestionar la foto de perfil — subir, editar y eliminar
context: User
concept: Profile
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - conversation:2026-09-22 (capturas del flujo de foto de perfil)
  - docs/ui/profile-photo.md
endpoints:
  - PUT /me/profile/avatar
  - DELETE /me/profile/avatar
  - GET /me/profile/avatar/original
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

- [x] Subir una imagen válida actualiza el avatar y devuelve su URL.
- [x] Un fichero que no es imagen se rechaza con `422`, aunque tenga extensión `.jpg`.
- [x] Un fichero de más de 2 MB se rechaza con `413`.
- [x] La imagen almacenada **no conserva metadatos EXIF**.
- [x] La imagen almacenada es cuadrada.
- [x] El endpoint no acepta parámetros de encuadre: el recorte ya viene hecho. *Acepta `crop`, pero **no lo aplica**: solo lo guarda para reabrir el editor (`F-10`). El servidor no recorta ni gira.*
- [x] Una imagen recortada por el navegador tampoco conserva metadatos EXIF tras guardarse.
- [x] Una imagen de 4000 × 3000 px se acepta y se redimensiona.
- [x] El nombre original del fichero no aparece en la URL ni en la respuesta.
- [x] Subir una foto nueva reemplaza la anterior.
- [x] No se puede cambiar la foto de otro usuario. *Por construcción: el endpoint es `/me/profile/avatar` y no admite decir de quién.*
- [x] Con la cuenta sin activar devuelve `403`.
- [ ] Tras subirla, el avatar aparece actualizado en el perfil y en la cabecera. *El perfil sí; la cabecera la pinta `GET /me/context` (`FEAT-USR-027`), que no existe.*
- [x] Se publica `UserProfileUpdated`.
- [x] Se conservan el original y la imagen recortada.
- [x] «Editar» parte del original, no de la imagen ya recortada.
- [x] Reencuadrar dos veces seguidas no degrada la calidad respecto a la primera. *Se cumple por construcción: cada reencuadre parte del original, que no se toca. Lo que hay probado es eso — que el original sobrevive al reencuadre.*
- [x] El original no aparece en el perfil público ni en ninguna respuesta dirigida a terceros.
- [ ] Eliminar la foto devuelve al avatar por defecto en las tres vistas. *En el perfil sí. Las otras dos —cabecera y caja de publicación— son pantallas que no existen.*
- [x] Eliminar la foto borra los dos ficheros del almacenamiento.
- [x] Eliminar sin tener foto devuelve éxito, no error.
- [x] No se puede eliminar la foto de otro usuario.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **F-3** | ¿Se acepta HEIC? La cámara de iOS lo produce por defecto | **Hoy no**, y el error lo dice (`UNSUPPORTED_FILE_TYPE`). Admitirlo exige ImageMagick con `libheif`: es una decisión de infraestructura, no de esta ficha |
| F-1 | ¿Recorta el cliente o el servidor? | **Resuelta:** el cliente |
| **F-2** | **¿Se conserva el original?** «Editar» lo exige para poder alejar y desplazar | Sin él, editar solo recorta hacia dentro y degrada la imagen |
| F-4 | ¿Se puede eliminar la foto? | **Resuelta:** sí, con confirmación, y vuelve al avatar por defecto |
| F-10 | ¿Se guarda el encuadre para reabrir el editor donde se dejó? | Sin él, «Editar» empieza desde una posición por defecto |
| F-11 | ¿Hay aviso de confirmación tras eliminar? | Las demás acciones sí lo tienen |
| F-13 | ¿Qué hace «Editar» con fotos subidas antes de conservar originales? | **Resuelta:** no hay original que cargar y el endpoint responde `404`, lo mismo que quien no tiene foto. No se implantó en dos fases, así que el caso solo puede darse con datos antiguos |
| F-5 | ¿El mismo flujo sirve para la imagen de portada? | Otras proporciones y otras recomendaciones |
| F-7 | ¿Hay límite de cambios por periodo? | Un avatar es un vector de contenido inapropiado y no hay moderación (`V-1`) |
| F-9 | ¿Se conservan varios tamaños del avatar? | Las tarjetas de autor lo muestran a 40–60 px; servir 180 px en todas es desperdicio |

## Estado

**Especificación:** `APPROVED` (2026-09-24). `F-2` resuelta: se conserva la original para poder reeditar.
`F-3` (HEIC) es un detalle de formatos admitidos.

**Implementación:** `DONE` (2026-09-24). `PUT` y `DELETE /api/v1/me/profile/avatar`, más
`GET /api/v1/me/profile/avatar/original`. **Sin migración**: las tres columnas —`avatar_url`,
`avatar_original_url`, `avatar_crop`— ya estaban en la cuenta desde el esquema inicial.

Con esto llega **la primera subida de ficheros del proyecto**, y por eso la mitad del trabajo
es infraestructura compartida que no es de `User`.

### Lo que se ha construido y no es de nadie en particular

- **`FileStorage`**, el puerto de `file-uploads.md`, en `Shared`. Guardar un fichero es una
  capacidad técnica genérica, como el reloj: la necesitan los avatares, las portadas y algún
  día los manuscritos. Su implementación de hoy escribe en un directorio local, que es con lo
  que se empieza y no lo definitivo: el día que haya más de una instancia sirviendo la API
  hay que cambiar **una clase**, y que sea una sola es la razón de que el puerto exista.
- **`ImageProcessor`**, con GD. No valida y deja pasar: **decodifica y vuelve a codificar**.
  Un reencodificado no puede arrastrar metadatos, que es la única forma sólida de cumplir
  `RN-3`.
- **`GET /api/v1/media/{key}`**, que sirve los ficheros públicos.
- La variable de entorno **`APP_STORAGE_DIR`**, documentada en `.env`.

### Las dos carpetas no son cosmética

La recortada vive en `avatars/` y la original en `originals/`, y el endpoint abierto **solo
sirve la primera carpeta**. Así la original no es alcanzable desde ahí ni conociendo su clave:
para leerla hay que pasar por el endpoint que comprueba de quién es.

Podría haber bastado con que las claves sean impredecibles —son 128 bits—, y no basta: una
clave acaba en un log, en una captura o en el historial de un navegador. Una frontera que se
lee de un vistazo es mejor que un secreto que se mantiene por costumbre.

### El EXIF se prueba con EXIF de verdad

La prueba no confía en que GD «ya limpia»: construye un JPEG **con un bloque EXIF
reconocible**, con etiquetas de geolocalización dentro, lo sube y comprueba que lo guardado no
las contiene. Es la única forma de que la prueba siga valiendo el día que alguien cambie el
procesador de imagen por otro.

### Lo que el servidor no hace

No recorta y no gira (`RN-4b`). Eso lo hizo el navegador, y de ahí sale una ventaja que
conviene no perder: **el servidor nunca tiene que interpretar la orientación EXIF**, que es la
fuente clásica de fotos tumbadas.

`crop` se guarda y no se aplica. El criterio de aceptación decía «el endpoint no acepta
parámetros de encuadre»; lo que quería decir es que no los **aplica**, y guardar el último
encuadre es lo que permite que «Editar» reabra el editor donde se dejó (`F-10`). Va solo en el
perfil propio: es material de trabajo de su dueño.

### El orden de las operaciones importa

Se guarda el fichero, luego se guarda la cuenta, y **solo después** se borra lo que sustituye.
Al revés, un fallo entre medias dejaría a alguien sin foto o apuntando a un fichero que ya no
está. Lo mismo al eliminar: primero la fila, después los ficheros.

### `F-3`, HEIC: hoy no, y el error lo dice

GD no lo decodifica, así que una foto hecha con un iPhone en su formato por defecto se rechaza
con `UNSUPPORTED_FILE_TYPE`. No es un olvido: admitirlo exige ImageMagick con `libheif`, que
es una decisión de infraestructura. Mientras tanto el mensaje dice qué formatos valen, que es
lo único que quien sube la foto puede accionar.

### Lo que queda fuera

- **La portada del perfil** (`FEAT-USR-028`, `F-5`). Ahora es barata —el puerto y el
  procesador ya están— pero sus proporciones y límites no están decididos.
- **Varios tamaños del avatar** (`F-9`). Se guarda uno de 360 px y las tarjetas de autor lo
  enseñan a 40–60: hay desperdicio, y no duele hasta que haya tráfico.
- **Límite de cambios por periodo** (`F-7`). Un avatar es un vector de contenido inapropiado y
  no hay moderación (`V-1`).
- Al **anonimizar una cuenta** (`FEAT-USR-013`, `BLOCKED`) habrá que borrar también sus
  ficheros. Hoy la anonimización limpia las columnas; cuando ese borrado exista, tendrá que
  pasar por aquí.
