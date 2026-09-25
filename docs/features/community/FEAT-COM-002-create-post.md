---
id: FEAT-COM-002
title: Crear una publicación
context: Community
concept: Post
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - _sources/use-cases.pdf#p3
  - conversation:2026-09-22 (modal de publicación)
  - docs/ui/create-post.md
endpoints: [POST /posts, PATCH /posts/{postId}, DELETE /posts/{postId}]
events: [PostPublished]
depends_on: [FEAT-USR-025]
updated: 2026-09-25
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

## Audiencia de una publicación

**Decidido** (`C-1`): dos valores, no más.

| Valor | Quién la ve |
|---|---|
| `EVERYONE` — «Publicar para cualquiera» | Cualquiera |
| `FOLLOWERS` — «Publicar para mis seguidores» | Solo quienes siguen al autor |

Comparte enum con los ajustes de privacidad
([`FEAT-USR-038`](../user/FEAT-USR-038-privacy-settings.md)) menos el valor `NOBODY`, que aquí
no tiene sentido: una publicación que nadie puede ver no es una publicación.

La audiencia **se fija al publicar y no cambia después**. Permitir ampliarla más tarde haría
que algo escrito para un círculo cerrado apareciera de pronto ante todos, que es la clase de
sorpresa que hace que la gente deje de publicar.

Tiene consecuencia en el muro y en las notificaciones: `Notification` **comprueba la audiencia
antes de avisar**. Un aviso sobre algo que no se puede abrir es, además de inútil, una
filtración.

### El vídeo queda fuera de esta versión

`C-2` sigue sin decidir. El modal ofrece adjuntar vídeo, y eso arrastra transcodificación,
almacenamiento y coste que no se han valorado. **Se registra aparte** (`FEAT-COM-037`) para no
bloquear la publicación de texto e imagen, que es lo que el producto necesita ya.

## Un adjunto como máximo

**Decidido** (`C-5`, `C-10`): una publicación lleva **cero o un adjunto**, nunca dos, y el
texto es opcional cuando hay adjunto.

| Lleva | ¿Se publica? |
|---|---|
| Solo texto | Sí |
| Solo imagen | Sí |
| Solo enlace, o solo relato | Sí |
| Texto y un adjunto | Sí |
| Dos adjuntos | No, `422` |
| Ni texto ni adjunto | No, `422` |

Lo que se rechaza es **la publicación vacía**, no la que no lleva texto. Obligar a escribir
algo junto a una foto es una fricción que nadie espera en un muro, y el resultado sería gente
escribiendo un punto.

El formato (`PostFormat`) **se deriva del adjunto** y no se envía: una publicación con imagen
es `IMAGE`, con enlace `LINK`, con relato `WORK`, y sin nada `TEXT`. Pedir que el cliente lo
declare es pedirle que repita algo que ya está diciendo, y abrir la puerta a que lo diga mal.

## El enlace se guarda, no se previsualiza

**Decidido** (`C-8`): se almacena **la URL y nada más**. El título y la descripción de la
tarjeta de artículo no los genera el backend.

La alternativa era que el servidor visitara la dirección que escribe el usuario, y eso es una
capacidad peligrosa por sí misma: convierte cualquier publicación en una petición saliente
hacia donde diga quien publica, alcanzable a direcciones internas, y ata publicar a que un
sitio ajeno responda a tiempo. Se registrará como funcionalidad propia cuando se decida quién
la genera y con qué salvaguardas.

Mientras tanto el cliente pinta un enlace simple, que es información honesta: la URL es lo
que se sabe.

## Editar y eliminar

**Decidido** (`C-9`): el autor puede hacer las dos cosas con lo suyo.

- **Editar cambia el texto.** El adjunto y la audiencia se fijan al publicar. Cambiar el
  adjunto bajo comentarios ya escritos altera aquello a lo que la gente respondió, que es el
  problema que tiene editar en un muro; y ampliar la audiencia haría aparecer ante todos algo
  escrito para un círculo cerrado.
- **La publicación editada se marca como tal.** Quien lee un comentario tiene derecho a saber
  que lo que hay encima no es lo que había cuando se escribió.
- **Eliminar es un borrado lógico y es definitivo para todos**, incluido su autor. No hay
  papelera: «eliminar» tiene que significar lo que la gente cree que significa.

## Reglas de negocio

- `RN-1` Una publicación lleva **texto, un adjunto, o ambos**. Vacía se rechaza.
- `RN-2` El autor es siempre el usuario autenticado. No se acepta un `authorId` en la
  petición.
- `RN-3` Publicar exige la cuenta activada (`FEAT-USR-025`).
- `RN-4` El texto se sanea: no admite HTML arbitrario. Los emojis sí se conservan.
- `RN-5` Las imágenes y los vídeos adjuntos se procesan al subirse y **pierden los metadatos
  EXIF**, igual que el avatar (`file-uploads.md`).
- `RN-6` Un enlace externo se guarda **como URL y nada más** (`C-8`). Solo `http` y `https`:
  cualquier otro esquema es una forma de que el cliente ejecute algo al pulsar.
- `RN-7` Un relato de la plataforma se guarda como **`WorkId`, no como URL**: la tarjeta se
  pinta con los datos vivos de la obra (`FEAT-COM-028`).
- `RN-8` La audiencia determina quién ve la publicación en el muro. El filtrado es de
  servidor: **no se sirve una publicación que el lector no debe ver y se oculta en cliente**.
- `RN-9` Una publicación no consume ni genera créditos.
- `RN-10` Publicar emite `PostPublished`, que `Notification` usa para avisar a los
  suscriptores del autor.
- `RN-11` El texto no pasa de **5.000 caracteres** (`C-6`), medidos **después** de limpiar el
  marcado: contar contra el límite algo que ni se va a guardar castigaría a quien pega desde
  un procesador de textos.
- `RN-12` El **formato se deriva del adjunto**; no se acepta del cliente.
- `RN-13` Editar cambia **solo el texto**, lo marca como editado y no toca audiencia ni
  adjunto. Eliminar es lógico, definitivo y para todos. Ambas, solo el autor: para cualquier
  otro la publicación **no existe** (`404`), no está prohibida.
- `RN-14` Un relato adjunto tiene que **existir y ser visible para quien publica**. No se
  promociona lo que no se puede abrir.

`RN-8` es la regla que hay que vigilar: en cuanto haya audiencias distintas de «cualquiera»,
cualquier consulta del muro que olvide el filtro se convierte en una fuga.

## Adjuntos

| Tipo | Icono | Estado |
|---|---|---|
| Imagen | Foto con «+» | Definido. Mismas reglas que el resto de imágenes |
| **Vídeo** | Cámara con «+» | **Fuera de alcance** (`C-2`), registrado como `FEAT-COM-037` |
| Enlace | Cadena | Definido: se guarda la URL, sin previsualización (`C-8`) |
| Relato | — *(sin icono en el modal)* | Definido por la API (`C-11`): `POST /posts` admite un `workId` |

El vídeo merece atención aparte: su coste de almacenamiento y de proceso no se parece al de
una imagen, y arrastra transcodificación, duración máxima y reproducción. Aceptar vídeo es
una decisión de producto con consecuencias de infraestructura, no un adjunto más.

El relato se resuelve **en la API** (`C-11`): `POST /posts` admite un `workId` y comprueba que
la obra exista y sea visible para quien publica. Desde qué pantalla se pulsa —el modal o la
propia obra— es una decisión de diseño que no toca el backend, y no se crea sola al publicar
una obra: publicar un texto y anunciarlo en el muro son dos decisiones distintas de su autor.

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
| Editar el texto | `PATCH /posts/{postId}` | `editPost` |
| Eliminar | `DELETE /posts/{postId}` | `deletePost` |

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

- [x] Publicar un texto crea la publicación y devuelve `201`.
- [x] Una publicación con solo imagen, solo enlace o solo relato se acepta.
- [x] Dos adjuntos a la vez se rechazan.
- [x] Un enlace con un esquema que no sea `http` o `https` se rechaza.
- [x] El texto que pasa de 5.000 caracteres se rechaza.
- [x] El autor puede editar el texto de lo suyo, y queda marcado como editado.
- [x] Editar no cambia la audiencia ni el adjunto.
- [x] El autor puede eliminar lo suyo, y deja de existir para todos.
- [x] Nadie edita ni elimina la publicación de otro, y recibe `404`.
- [x] El autor es el usuario autenticado, aunque la petición incluya otro `authorId`.
- [x] Una publicación sin texto ni adjunto se rechaza.
- [x] El texto se almacena saneado frente a inyección de HTML, conservando los emojis.
- [x] Una imagen adjunta pierde sus metadatos EXIF.
- [x] Un relato adjunto se guarda por `workId`, no por URL.
- [x] La audiencia se guarda y el muro **filtra en servidor** por ella.
- [x] Publicar no mueve créditos.
- [x] Con la cuenta sin activar devuelve `403`.
- [x] Se publica `PostPublished` con la audiencia incluida.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~C-1~~ | ¿Qué opciones tiene el selector de audiencia? | **Bloqueante** para el modelo y el filtrado |
| ~~C-2~~ | ¿Se admite vídeo? | Fuera de alcance, registrado como `FEAT-COM-037` |
| ~~C-5~~ | ¿Se pueden combinar adjuntos? | Resuelta: uno como máximo |
| ~~C-6~~ | ¿Longitud máxima del texto? | Resuelta: 5.000 caracteres |
| ~~C-8~~ | ¿Quién genera la previsualización de un enlace? | Resuelta: nadie por ahora; se guarda la URL |
| ~~C-9~~ | ¿Se puede editar o eliminar una publicación propia? | Resuelta: las dos, con el texto como único campo editable |
| ~~C-10~~ | ¿Se puede publicar solo una imagen? | Resuelta: sí |
| ~~C-11~~ | ¿Desde dónde se adjunta un relato? | Resuelta: la API acepta un `workId` |
| C-14 | ¿Se puede recuperar una publicación eliminada? | Hoy no. Una papelera es otra funcionalidad |

`C-8` se resolvió justamente por su arista de seguridad: si el servidor visitara la URL que
escribe el usuario, habría que impedir además que se usara para alcanzar direcciones internas.
No generarla no es aplazar el problema, es no crearlo todavía.

## Estado

**Especificación:** `APPROVED` (2026-09-25). `C-1` resuelta: dos audiencias, `EVERYONE` y
`FOLLOWERS`. El vídeo (`C-2`) queda fuera de alcance y se registra como `FEAT-COM-037`.

Resueltas en la implementación, con producto: un adjunto como máximo y texto opcional
(`C-5`, `C-10`), 5.000 caracteres (`C-6`), el enlace se guarda sin previsualizar (`C-8`),
se puede editar el texto y eliminar (`C-9`), y el relato se adjunta por `workId` desde la
API (`C-11`).

**Implementación:** `DONE` (2026-09-25). Se implementa junto a
[`FEAT-COM-001`](FEAT-COM-001-main-wall.md) porque son las dos mitades de lo
mismo: sin el muro, publicar es escribir en un sitio que nadie mira, y la
audiencia —que es una regla de privacidad— no tiene dónde defenderse.

`PostPublished` se publica y **todavía no lo escucha nadie**: avisar a quien
sigue al autor es `FEAT-NOT-001`, y el hecho ya viaja con su audiencia para
que ese día no haya que cambiar el contrato.
