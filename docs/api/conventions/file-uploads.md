# Subida de ficheros

> Estado: `DRAFT` en lo que falta. **El manuscrito ya funciona**
> ([`FEAT-WRK-002`](../../features/work/FEAT-WRK-002-upload-a-manuscript.md)), en `.txt` y
> `.docx`. **La foto de perfil ya funciona**
> ([`FEAT-USR-037`](../../features/user/FEAT-USR-037-upload-profile-photo.md)) y con ella
> existen el puerto `FileStorage`, el normalizador de imágenes y
> `GET /api/v1/media/{key}`. **La portada de una obra publicada también**
> ([`FEAT-USR-029`](../../features/user/FEAT-USR-029-published-books.md)), montada sobre esas
> mismas piezas. Lo que sigue sin implementarse es el manuscrito (`FEAT-WRK-002`) y las demás
> imágenes.

## Tipos de fichero

| Uso | Formatos | Funcionalidad |
|---|---|---|
| Manuscrito | **`.txt` y `.docx`** (`W-3`, resuelta) | `FEAT-WRK-002` |
| Foto de perfil (avatar) | `.jpg`, `.png`, `.webp`. ¿HEIC? ver `F-3` | `FEAT-USR-037` |
| Portada del perfil | `.jpg`, `.png`, `.webp` | `FEAT-USR-028` |
| Portada de una obra publicada | `.jpg`, `.png`, `.webp`. Proporción de libro (2:3) **recomendada, no impuesta** | `FEAT-USR-029` |
| Fondos de la página de autor | `.jpg`, `.png`, `.webp` | `FEAT-USR-016` |

**`W-3` resuelta**: `.docx`, que es lo que Word produce hoy, y `.txt`. El `.doc` binario es un
formato OLE de los noventa que casi nadie produce ya, y el `.pdf` describe dónde va cada letra
y no dónde acaba una idea, así que los párrafos salen mal — además de ser el formato con
capacidad de ejecución que este mismo documento dice que no se trate como texto inofensivo.
Los dos se pueden añadir escribiendo otro adaptador detrás de `DocumentTextExtractor`, que es
para lo que está el puerto.

## Proceso de un manuscrito

1. El escritor sube el fichero.
2. El sistema valida tipo y tamaño.
3. Se extrae el texto plano mediante el puerto `DocumentTextExtractor`.
4. Se propone una división en fragmentos a partir de **los estilos de título que el autor ya
   había puesto** (`W-4`, resuelta: el servidor propone y el autor confirma).
5. El escritor revisa y confirma.
6. Se crea la obra como en `FEAT-WRK-001`.

**Es síncrono**: leer un `.docx` es abrir un zip y recorrer un XML, y el tope de tamaño acota
el peor caso. Una cola y un endpoint de estado para eso sería complejidad sin nada al otro
lado; el día que entre el `.pdf`, se revisa.

## Validación

- El tipo se determina por el **contenido**, no por la extensión ni por el `Content-Type`
  declarado por el cliente.
- Tamaño máximo por tipo:

| Uso | Máximo | Origen |
|---|---|---|
| Foto de perfil | **2 MB** | Anunciado en el propio modal (`FEAT-USR-037`) |
| Portada del perfil | Por definir | — |
| Portada de obra publicada | **2 MB** | `FEAT-USR-029` `P-18` |
| Manuscrito | **10 MB** | `FEAT-WRK-002` `RN-9`. Una novela de 75.000 palabras en `.docx` ocupa uno o dos; el margen es para las imágenes pegadas |

El límite lo aplica **el servidor**. Que el cliente lo anuncie es una cortesía, no un control.
La proporción de la portada de una obra publicada es una recomendación de diseño y **no se
fuerza con un recorte**: las portadas reales no miden todas lo mismo, y recortar la de alguien
para que encaje en una cuadrícula es estropearla. Se guarda con el lado mayor en 900 px,
conservando su proporción.

- Los ficheros se analizan antes de procesarse. Un PDF es un formato con capacidad de
  ejecución y no se trata como texto inofensivo.
- Los nombres de fichero originales se sanean y no se usan como ruta de almacenamiento.

## Almacenamiento

- A través del puerto `FileStorage`, implementado en `Infrastructure`. La implementación de
  hoy escribe en un directorio local (`APP_STORAGE_DIR`); cambiarla por almacenamiento de
  objetos es cambiar **una clase**, que es para lo que está el puerto.
- Nunca en la base de datos. Lo que se guarda en una fila es **la clave**, no la dirección: la
  dirección se calcula en un solo sitio, y así mudarse de dominio o a un CDN no obliga a
  reescribir ninguna tabla.
- Nunca accesibles por URL pública adivinable. Las claves son impredecibles **y** el endpoint
  que sirve ficheros solo entrega las carpetas declaradas públicas: lo que no lo es no sale de
  ahí aunque se conozca su clave. Una frontera que se lee de un vistazo es mejor que un
  secreto que se mantiene por costumbre.

**El fichero original no se conserva** (`FEAT-WRK-002`): se extrae el texto y se descarta.
Conservarlo tenía valor probatorio para el registro de autoría, pero ese registro
(`FEAT-WRK-009`) sigue bloqueado por `W-1` y, cuando se desbloquee, podrá pedir lo que
necesite. Mientras tanto guardaría una copia más de obra inédita a cambio de algo que hoy
nadie usa.

Un zip pequeño puede descomprimirse en gigabytes, así que el tamaño **se vuelve a comprobar
por dentro**: el límite de arriba se mide antes de descomprimir y no protege de eso. Y al leer
el XML de un `.docx` no se expanden entidades ni se abren conexiones, que es la forma clásica
de leer un fichero del servidor a través de un documento subido.

## Imágenes

- Se reprocesan **siempre** al subirlas: redimensionado, normalización de formato y
  eliminación de metadatos EXIF.
- Los metadatos EXIF pueden contener **geolocalización**. No se conservan. Esto vale también
  cuando el cliente ya ha recortado la imagen: un recorte del navegador no garantiza que los
  metadatos hayan desaparecido.
- Si el servidor gira una imagen, debe interpretar antes su orientación EXIF, o las fotos
  hechas con móvil aparecerán tumbadas.
- Las cámaras de iOS producen **HEIC** por defecto, un formato que los navegadores no
  muestran bien. Si se admite, hay que convertirlo en servidor (`F-3`).
