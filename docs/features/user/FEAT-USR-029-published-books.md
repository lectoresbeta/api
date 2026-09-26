---
id: FEAT-USR-029
title: Obras publicadas del autor
context: User
concept: AuthorPage
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - conversation:2026-09-22 (capturas de «Mi perfil» → «Más info»)
  - docs/ui/my-profile.md
  - docs/ui/profile-more-info.md
endpoints: [GET /users/{userId}/published-books, POST /me/published-books, PATCH /me/published-books/{id}, DELETE /me/published-books/{id}, PUT /me/published-books/{id}/cover, DELETE /me/published-books/{id}/cover]
events: []
depends_on: [FEAT-USR-028]
updated: 2026-09-25
---

# FEAT-USR-029 — Obras publicadas del autor

## Resumen

Bibliografía que el autor añade a su perfil para acreditar su trayectoria: libros ya editados
**fuera de Lectores Beta**, con su editorial, su año y un enlace de compra.

## Una «obra publicada» no es una `Work`

Es lo que hay que entender antes de modelar nada. Comparten la palabra «obra» y no son el
mismo concepto:

| | `Work` (relato) | `PublishedBook` (obra publicada) |
|---|---|---|
| Qué es | Manuscrito inédito | Libro editado y a la venta |
| Dónde vive | En la plataforma | En el mundo exterior |
| Para qué | Recibir feedback de lectores beta | Acreditar la trayectoria del autor |
| Contenido | Sí, y es el activo que hay que proteger | No: solo metadatos y un enlace |
| Fragmentos, cuestionario, accesos | Sí | No |
| Créditos | Sí | No |
| Quién lo ve | Solo autor y lectores beta con acceso | Cualquiera: es promoción |

Modelarlos juntos, o reutilizar `Work` con una bandera, arrastraría a un agregado que
custodia texto inédito toda la maquinaria de una ficha bibliográfica pública. Son entidades
distintas en conceptos distintos.

Se nombra **`PublishedBook`** precisamente para que la colisión no ocurra en el código.

## Datos

| Campo | Obligatorio | Nota |
|---|---|---|
| Portada | No | Imagen **vertical**, con proporción de portada de libro. La sube el autor |
| Título | Sí | |
| Editorial | No | **Texto libre.** Ver abajo |
| Año | No | Año de publicación |
| Enlace de compra | No | URL externa, botón «Comprar» |

### «Editorial» es texto libre

El ejemplo del diseño dice **«Amazon»**, que no es un sello editorial sino una plataforma de
venta y autopublicación.

**No se valida contra ningún catálogo de editoriales.** Hacerlo dejaría fuera justo al perfil
más habitual de esta plataforma: el autor aficionado que se ha publicado por su cuenta. El
campo responde a «¿dónde salió este libro?», y «Amazon» es una respuesta tan válida como el
nombre de un sello.

### La portada es una subida de imagen

Es un tipo de fichero distinto del avatar y de la portada de perfil: **vertical**, con
proporción de libro. Le aplican las reglas generales de
[`file-uploads.md`](../../api/conventions/file-uploads.md): validación por contenido, límite
de tamaño en servidor y **eliminación de metadatos EXIF**.

## Reglas de negocio

- `RN-1` Una obra publicada pertenece a un único usuario y solo él la gestiona.
- `RN-2` Es **contenido público**: aparece en el perfil que ven los demás.
- `RN-3` No tiene contenido literario. **No se puede leer, ni comentar, ni pedir acceso.**
- `RN-4` No interviene en créditos, ni en lectores beta, ni en rankings.
- `RN-5` El enlace de compra es **externo**. Se valida que sea una URL bien formada y se
  marca como enlace saliente.
- `RN-6` El título es obligatorio; el resto es opcional. Un autor puede querer citar una obra
  descatalogada sin enlace de compra.
- `RN-7` El orden de la lista lo decide el autor, o por defecto el año descendente (`P-15`).
- `RN-9` La **editorial no se valida** contra ningún catálogo: es texto libre.
- `RN-10` La portada es opcional. Sin ella hay que decidir qué se muestra (`P-20`).
- `RN-8` Añadir, editar y borrar requieren la cuenta activada (`FEAT-USR-025`).

`RN-3` es la regla que impide que este concepto contamine el resto: en cuanto algo
«publicado» admita comentarios, habrá que decidir si cuesta créditos, y el modelo entero se
tambalea.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Listar las de un autor | `GET /users/{userId}/published-books` | `listPublishedBooks` |
| Añadir | `POST /me/published-books` | `addPublishedBook` |
| Editar | `PATCH /me/published-books/{publishedBookId}` | `updatePublishedBook` |
| Eliminar | `DELETE /me/published-books/{publishedBookId}` | `deletePublishedBook` |
| Subir la portada | `PUT /me/published-books/{publishedBookId}/cover` | `updatePublishedBookCover` |
| Quitar la portada | `DELETE /me/published-books/{publishedBookId}/cover` | `deletePublishedBookCover` |

El listado es público: no requiere sesión, igual que el resto del perfil. Responde `404`
cuando el perfil no se puede ver —cuenta eliminada o privacidad restringida
(`FEAT-USR-038`)—: contestar por su cuenta sería un camino lateral para confirmar que una
cuenta existe justo cuando su titular ha pedido que no se sepa.

### Las dos operaciones de portada no estaban en la ficha

La ficha daba cuatro endpoints y la portada como un campo más. **No cabe ahí**: PHP solo
desmonta un cuerpo `multipart/form-data` en las peticiones `POST`, así que una portada dentro
del `PATCH` llegaría como un cuerpo vacío, en silencio. Tiene endpoint propio, como la foto de
perfil, y de paso el año no se queda sin guardar porque falló una subida.

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `published_book` | `user_id`, título, editorial (texto libre), año, `purchase_url`, `cover_url`, posición |

Índice sobre `published_book(user_id, position)`.

La tabla y su índice ya existían en `Version20260923174400`, así que esta funcionalidad **no
trae migración**: lo que faltaba era el comportamiento, no el esquema.

## Criterios de aceptación

- [x] Un autor añade una obra publicada con solo el título y se guarda.
- [x] La obra publicada aparece en su perfil público.
- [x] No se puede solicitar acceso de lector beta a una obra publicada.
- [x] No se puede dejar feedback sobre una obra publicada.
- [x] Añadirla no mueve créditos.
- [x] Un usuario no puede editar ni borrar las obras publicadas de otro.
- [x] Un enlace de compra malformado se rechaza con `422`.
- [x] El enlace de compra se marca como externo en la respuesta.
- [x] Con la cuenta sin activar, añadir devuelve `403 ACCOUNT_NOT_ACTIVATED`.
- [x] Se acepta cualquier texto como editorial, incluida una plataforma como «Amazon».
- [x] La portada subida pierde sus metadatos EXIF.
- [x] Una obra publicada sin portada se acepta y se muestra sin fallar.

Los cuatro primeros están en `tests/Functional/User/PublishedBooksTest.php`, y el tercero y el
cuarto en un solo caso —`testAPublishedBookIsNotAWorkAnywhere`— que prueba el identificador de
una obra publicada contra los endpoints de `Work`, `Reading` y `Feedback`. Es el que se pondrá
rojo el día que alguien junte los dos conceptos «porque son casi lo mismo».

## Decisiones tomadas al implementar

| Decisión | Por qué |
|---|---|
| El listado se apoya en la visibilidad del perfil en vez de responder por su cuenta | La regla de quién ve a quién se decide en un solo sitio (`VisibleProfile`). Dos sitios acaban dando dos respuestas |
| Editar la obra de otro responde `403`, no `404` | Esconder la existencia de algo solo sirve cuando lo que se protege es saber que existe, y esto se enseña en un perfil abierto |
| El año se valida entre 1450 y el año que viene | Atrapa el error de teclado —un `19`, un `202`— sin discutirle a nadie su bibliografía. Llega al año que viene porque un libro se anuncia antes de salir |
| No se publica ningún evento | Esto no mueve créditos, ni cuenta como relato, ni le interesa a ningún otro contexto. Un evento «por si acaso» es un contrato que luego hay que mantener |
| `purchaseUrlIsExternal` viaja siempre, y siempre en `true` | No es información que varíe: es el contrato diciendo que ese enlace sale de la plataforma, para que quien lo pinta no tenga que comparar dominios en cada pantalla |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| P-9 | ¿Se valida de algún modo que el libro exista, por ISBN o similar? | **Resuelta:** no. Viendo que la editorial puede ser «Amazon», comprobar la existencia del libro dejaría fuera justo a quien más usa esto |
| P-18 | ¿Qué proporción y tamaño máximo tiene la portada? | **Resuelta:** 2 MB de subida y lado mayor 900 px al guardarla. La proporción 2:3 es una **recomendación de diseño y no se impone**: recortar la portada de alguien para que encaje en una cuadrícula es estropearla, y las reales no miden todas lo mismo |
| P-20 | ¿Qué se muestra si el autor no sube portada? | **Resuelta:** el marcador lo pone la interfaz. La API devuelve `coverUrl: null`, porque una imagen de relleno le quitaría al cliente la única forma de distinguir «no hay portada» de «esta es la portada» |
| P-10 | ¿Hay afiliación en el enlace «Comprar»? | Implicaciones comerciales y legales |
| P-15 | ¿Quién decide el orden de la lista? | **Resuelta:** el autor, con año descendente por defecto. Al añadir una obra se coloca **solo esa**, donde la pondría el año; reordenar la lista entera en cada alta desharía en silencio lo que el autor acababa de arrastrar. Las obras sin año van al final |
| P-16 | ¿Hay límite de obras publicadas por perfil? | **Resuelta:** 50. Un perfil no es un catálogo, y quien de verdad las supere tiene un problema que merece una conversación, no un formulario |
| P-17 | ¿Cuenta como «Relato» en los contadores? | **No.** Son conceptos distintos (`RN-3`) |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `DONE` (2026-09-25). `P-10` —si el enlace «Comprar» lleva afiliación— sigue
abierta y no la decide el backend: es una decisión comercial y legal. El campo guarda la
dirección que escriba el autor, y añadir un parámetro de afiliación el día que se decida no
cambia el modelo.
