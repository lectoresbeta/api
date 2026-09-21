# Subida de ficheros

> Estado: `DRAFT`. Relacionado con `FEAT-WRK-002` (crear obra subiendo un fichero).

## Tipos de fichero

| Uso | Formatos | Funcionalidad |
|---|---|---|
| Manuscrito | `.doc`, `.pdf`, `.txt` (¿y `.docx`?, ver `W-3`) | `FEAT-WRK-002` |
| Foto de perfil y página de autor | `.jpg`, `.png`, `.webp` | `FEAT-USR-015` |
| Fondos de la página de autor | `.jpg`, `.png`, `.webp` | `FEAT-USR-016` |

El documento de origen dice `.doc`; conviene confirmar si incluye `.docx`, que es lo que
realmente produce Word hoy.

## Proceso de un manuscrito

1. El escritor sube el fichero.
2. El sistema valida tipo y tamaño.
3. Se extrae el texto plano mediante el puerto `DocumentTextExtractor`.
4. Se propone una división en fragmentos (pendiente: automática o manual, `W-4`).
5. El escritor revisa y confirma.
6. Se crea la obra como en `FEAT-WRK-001`.

La extracción puede ser lenta para una novela. Si se hace de forma asíncrona, el endpoint
devuelve `202 Accepted` y el cliente consulta el estado del procesamiento.

## Validación

- El tipo se determina por el **contenido**, no por la extensión ni por el `Content-Type`
  declarado por el cliente.
- Tamaño máximo por definir. Referencia: una novela media de 75.000 palabras en `.docx` con
  imágenes puede superar varios megabytes.
- Los ficheros se analizan antes de procesarse. Un PDF es un formato con capacidad de
  ejecución y no se trata como texto inofensivo.
- Los nombres de fichero originales se sanean y no se usan como ruta de almacenamiento.

## Almacenamiento

- A través del puerto `FileStorage`, implementado en `Infrastructure`.
- Nunca en la base de datos.
- Nunca accesibles por URL pública adivinable: el acceso pasa por la misma autorización que
  el contenido que representan.

Pendiente: si el fichero original se conserva tras extraer el texto. Conservarlo tiene valor
probatorio para el registro de autoría, pero multiplica el almacenamiento y la superficie de
exposición del contenido inédito.

## Imágenes

- Se reprocesan al subirlas: redimensionado y eliminación de metadatos EXIF.
- Los metadatos EXIF pueden contener geolocalización. No se conservan.
