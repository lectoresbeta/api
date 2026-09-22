---
id: FEAT-USR-029
title: Obras publicadas del autor
context: User
concept: AuthorPage
actors: [Writer]
spec_status: DRAFT
impl_status: TODO
priority: P2
sources:
  - conversation:2026-09-22 (capturas de «Mi perfil» → «Más info»)
  - docs/ui/my-profile.md
  - docs/ui/profile-more-info.md
endpoints: [GET /users/{userId}/published-books, POST /me/published-books, PATCH /me/published-books/{id}, DELETE /me/published-books/{id}]
events: []
depends_on: [FEAT-USR-028]
updated: 2026-09-22
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

El listado es público: no requiere sesión, igual que el resto del perfil.

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `published_book` | `user_id`, título, editorial (texto libre), año, `purchase_url`, `cover_url`, posición |

Índice sobre `published_book(user_id, position)`.

## Criterios de aceptación

- [ ] Un autor añade una obra publicada con solo el título y se guarda.
- [ ] La obra publicada aparece en su perfil público.
- [ ] No se puede solicitar acceso de lector beta a una obra publicada.
- [ ] No se puede dejar feedback sobre una obra publicada.
- [ ] Añadirla no mueve créditos.
- [ ] Un usuario no puede editar ni borrar las obras publicadas de otro.
- [ ] Un enlace de compra malformado se rechaza con `422`.
- [ ] El enlace de compra se marca como externo en la respuesta.
- [ ] Con la cuenta sin activar, añadir devuelve `403 ACCOUNT_NOT_ACTIVATED`.
- [ ] Se acepta cualquier texto como editorial, incluida una plataforma como «Amazon».
- [ ] La portada subida pierde sus metadatos EXIF.
- [ ] Una obra publicada sin portada se acepta y se muestra sin fallar.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| P-9 | ¿Se valida de algún modo que el libro exista, por ISBN o similar? | Viendo que la editorial puede ser «Amazon», lo natural es que **no** |
| P-18 | ¿Qué proporción y tamaño máximo tiene la portada? | El diseño no da recomendaciones, a diferencia del avatar |
| P-20 | ¿Qué se muestra si el autor no sube portada? | Hace falta un marcador por defecto |
| P-10 | ¿Hay afiliación en el enlace «Comprar»? | Implicaciones comerciales y legales |
| P-15 | ¿Quién decide el orden de la lista? | Propuesta: el autor, con año descendente por defecto |
| P-16 | ¿Hay límite de obras publicadas por perfil? | Evitar perfiles inflados |
| P-17 | ¿Cuenta como «Relato» en los contadores? | **No.** Son conceptos distintos (`RN-3`) |

## Estado

**Especificación:** `DRAFT`. La estructura está clara; faltan validación (`P-9`) y orden
(`P-15`).

**Implementación:** `TODO`.
