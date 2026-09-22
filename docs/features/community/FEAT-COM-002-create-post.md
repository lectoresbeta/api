---
id: FEAT-COM-002
title: Crear una publicación
context: Community
concept: Post
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - _sources/use-cases.pdf#p3
  - conversation:2026-09-22 (modal de publicación)
  - docs/ui/create-post.md
endpoints: [POST /posts]
events: [PostPublished]
depends_on: [FEAT-USR-025]
updated: 2026-09-22
---

# FEAT-COM-002 — Crear una publicación

## Resumen

El usuario escribe una publicación desde el modal que se abre al pulsar «¿Qué estás
pensando?», adjunta opcionalmente una imagen, un vídeo, un enlace o un relato de la
plataforma, elige su audiencia y publica.

## Las tres dimensiones de una publicación

Una publicación no tiene un tipo, tiene **tres atributos independientes**:

| Dimensión | Qué expresa | Valores |
|---|---|---|
| **Intención** (`PostType`) | Para qué se publica | `GENERAL`, `LOOKING_FOR_BETA_READERS`, `LOOKING_FOR_WRITING_BUDDY`, `OFFERING_AS_BETA_READER` |
| **Formato** (`PostFormat`) | Qué lleva dentro | `TEXT`, `IMAGE`, `VIDEO`, `LINK`, `WORK` |
| **Audiencia** (`PostAudience`) | Quién puede verla | Solo se conoce «cualquiera» (`C-1`) |

Mezclarlas en un único enum produciría una combinatoria que no describe el producto: una
publicación buscando lectores beta puede llevar imagen, vídeo o nada.

## Reglas de negocio

- `RN-1` El texto es el único contenido que puede ir solo. Un adjunto sin texto **está por
  decidir** (`C-10`).
- `RN-2` El autor es siempre el usuario autenticado. No se acepta un `authorId` en la
  petición.
- `RN-3` Publicar exige la cuenta activada (`FEAT-USR-025`).
- `RN-4` El texto se sanea: no admite HTML arbitrario. Los emojis sí se conservan.
- `RN-5` Las imágenes y los vídeos adjuntos se procesan al subirse y **pierden los metadatos
  EXIF**, igual que el avatar (`file-uploads.md`).
- `RN-6` Un enlace externo se guarda como URL. Su previsualización —título y descripción— se
  obtiene aparte; ver `C-8`.
- `RN-7` Un relato de la plataforma se guarda como **`WorkId`, no como URL**: la tarjeta se
  pinta con los datos vivos de la obra (`FEAT-COM-028`).
- `RN-8` La audiencia determina quién ve la publicación en el muro. El filtrado es de
  servidor: **no se sirve una publicación que el lector no debe ver y se oculta en cliente**.
- `RN-9` Una publicación no consume ni genera créditos.
- `RN-10` Publicar emite `PostPublished`, que `Notification` usa para avisar a los
  suscriptores del autor.

`RN-8` es la regla que hay que vigilar: en cuanto haya audiencias distintas de «cualquiera»,
cualquier consulta del muro que olvide el filtro se convierte en una fuga.

## Adjuntos

| Tipo | Icono | Estado |
|---|---|---|
| Imagen | Foto con «+» | Definido. Mismas reglas que el resto de imágenes |
| **Vídeo** | Cámara con «+» | **Sin definir** (`C-2`). Límites, duración y transcodificación |
| Enlace | Cadena | Definido a medias: falta quién genera la previsualización (`C-8`) |
| Relato | — *(sin icono en el modal)* | Aparece en el muro pero **no hay forma visible de añadirlo** (`C-11`) |

El vídeo merece atención aparte: su coste de almacenamiento y de proceso no se parece al de
una imagen, y arrastra transcodificación, duración máxima y reproducción. Aceptar vídeo es
una decisión de producto con consecuencias de infraestructura, no un adjunto más.

El relato plantea otro problema: la captura del muro muestra una publicación con una tarjeta
de obra, pero el modal solo ofrece vídeo, imagen y enlace. **O falta un icono, o esa
publicación se crea desde otro sitio** —por ejemplo desde la propia obra al publicarla—.

## Flujo principal

1. El usuario abre el modal desde la caja «¿Qué estás pensando?».
2. Escribe el texto.
3. Opcionalmente adjunta imagen, vídeo o enlace.
4. Elige la audiencia. Por defecto, «cualquiera».
5. Publica.
6. El sistema valida, sanea y persiste.
7. Publica `PostPublished`.
8. La publicación aparece en el muro del autor y en el de quienes le siguen.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Publicación vacía, sin texto ni adjunto | Se rechaza | `422` |
| Texto que supera el máximo | Se rechaza | `422`. Máximo por definir (`C-6`) |
| Adjunto de tipo no admitido | Se rechaza | `422` con `code: UNSUPPORTED_FILE_TYPE` |
| Adjunto demasiado grande | Se rechaza | `413` con `code: FILE_TOO_LARGE` |
| Enlace malformado | Se rechaza | `422` |
| Relato inexistente o sin permiso para mostrarlo | Se rechaza | `404` |
| Cuenta sin activar | Se rechaza | `403` con `code: ACCOUNT_NOT_ACTIVATED` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Crear publicación | `POST /posts` | `createPost` |

Lleva texto, audiencia y, según el caso, el adjunto. Los ficheros van en `multipart`; el
enlace y el `workId`, en el cuerpo.

## Eventos

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `PostPublished` | Se publica | `Notification` | `postId`, `authorId`, `type`, `format`, `audience` |

El payload lleva la audiencia porque `Notification` no debe avisar a quien no puede ver la
publicación.

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `post` | `author_id`, texto, `type`, `format`, `audience`, referencias del adjunto |
| `post_attachment` | Ficheros adjuntos, si se admiten varios (`C-5`) |

Índices: `post(author_id, created_at)` para el muro propio y `post(created_at, audience)`
para el muro general.

## Criterios de aceptación

- [ ] Publicar un texto crea la publicación y devuelve `201`.
- [ ] El autor es el usuario autenticado, aunque la petición incluya otro `authorId`.
- [ ] Una publicación sin texto ni adjunto se rechaza.
- [ ] El texto se almacena saneado frente a inyección de HTML, conservando los emojis.
- [ ] Una imagen adjunta pierde sus metadatos EXIF.
- [ ] Un relato adjunto se guarda por `workId`, no por URL.
- [ ] La audiencia se guarda y el muro **filtra en servidor** por ella.
- [ ] Publicar no mueve créditos.
- [ ] Con la cuenta sin activar devuelve `403`.
- [ ] Se publica `PostPublished` con la audiencia incluida.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **C-1** | ¿Qué opciones tiene el selector de audiencia? | **Bloqueante** para el modelo y el filtrado |
| **C-2** | ¿Se admite vídeo? ¿Con qué límites y transcodificación? | Coste de infraestructura muy superior al de una imagen |
| C-5 | ¿Se pueden combinar adjuntos, o son excluyentes? | El pie sugiere combinables; la nota de la Home, excluyentes |
| C-6 | ¿Longitud máxima del texto? | Validación |
| C-8 | ¿Quién genera la previsualización de un enlace externo? | Si es el backend, hace peticiones salientes a URLs que aporta el usuario |
| C-10 | ¿Se puede publicar solo una imagen, sin texto? | Validación |
| C-11 | ¿Desde dónde se adjunta un relato? El modal no ofrece icono para ello | Falta una entrada o la publicación nace en otro sitio |
| C-9 | ¿Se puede editar o eliminar una publicación propia? | El menú «···» no tiene diseño para publicaciones propias |

`C-8` tiene una arista de seguridad: si el servidor visita la URL que el usuario escribe para
generar la previsualización, hay que impedir que se use para alcanzar direcciones internas.

## Estado

**Especificación:** `DRAFT`. El texto y la imagen están claros. Para llegar a `APPROVED` hacen
falta `C-1` (audiencia) y `C-2` (vídeo).

**Implementación:** `TODO`.
