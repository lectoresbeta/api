---
id: FEAT-USR-030
title: Premios y reconocimientos del autor
context: User
concept: AuthorPage
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P3
sources:
  - docs/ui/profile-more-info.md
  - docs/ui/my-profile.md
  - conversation:2026-09-26 (P-19)
endpoints:
  - GET /users/{userId}/awards
  - POST /me/awards
  - PATCH /me/awards/{awardId}
  - DELETE /me/awards/{awardId}
events: []
depends_on: [FEAT-USR-028, FEAT-USR-029]
updated: 2026-09-26
---

# FEAT-USR-030 — Premios y reconocimientos del autor

## Resumen

La segunda sub-pestaña de **«Más info»**, hermana de las obras publicadas: los méritos que el
autor quiere enseñar. Un premio, una mención, una beca, un finalista.

## No hay diseño, y esto es lo que se ha decidido

`P-19` —«¿qué contiene Premios y reconocimientos?»— llevaba abierta desde que se leyeron las
capturas de «Mi perfil», porque la sub-pestaña aparece nombrada y **no hay ni una captura de
su interior**.

Decidido (2026-09-26): es **una ficha que declara el autor**, con el mismo patrón que
[`FEAT-USR-029`](FEAT-USR-029-published-books.md) y sin portada.

Se descartaron las otras dos lecturas:

- **entrada mínima, solo título y año.** El perfil no podría decir quién concedió el premio ni
  enlazar la convocatoria, que es justo lo que lo hace verificable, y añadirlo después es una
  migración;
- **insignias que otorga la plataforma.** Es otra funcionalidad —con reglas de concesión, y
  ganada, no declarada— y dejaría los premios de fuera sin sitio. Si algún día existe, será su
  propia ficha y convivirá con esta, igual que `Work` convive con `PublishedBook`.

## Un premio no se valida, igual que una editorial no se valida

`FEAT-USR-029` ya resolvió esta discusión para la editorial: el campo dice «Amazon» y no se
comprueba contra ningún catálogo, porque hacerlo dejaría fuera al perfil más habitual de esta
plataforma.

Aquí es lo mismo y más fuerte: **no existe un registro universal de premios literarios**. El
concurso del ayuntamiento, la mención del taller y el Premio Planeta se declaran igual. Lo que
la plataforma ofrece no es una acreditación, es un sitio donde ponerlo, y el enlace —opcional—
es lo que permite al que lee comprobarlo por su cuenta.

Esto es `P-9` («¿se valida que el libro exista?») aplicado a los premios, y la respuesta es la
misma: **no**, y se dice en la ficha para que nadie lo lea como una verificación.

## Datos

| Campo | Obligatorio | Nota |
|---|---|---|
| Título | Sí | El nombre del premio o del reconocimiento. 180 caracteres |
| Quién lo concede | No | **Texto libre.** El certamen, la institución, la revista. 180 caracteres |
| Año | No | El año en que se concedió |
| Nota | No | Una línea para lo que no cabe en el título: «finalista», «categoría relato». 280 caracteres |
| Enlace | No | URL externa al fallo, a las bases o a la noticia. 512 caracteres |

**Sin imagen.** Un premio no tiene portada, y la sub-pestaña hermana ya arrastra una subida de
ficheros. Añadir aquí otra —diploma, logotipo— sería otro tipo de imagen, otro límite y otra
pantalla, por un adorno.

## El orden lo pone el año, y el autor no lo toca

Es la única diferencia deliberada con las obras publicadas, donde el autor arrastra las
tarjetas (`P-15`).

Ahí el orden era una decisión suya porque la cuadrícula es su escaparate y el año de edición
no dice cuál es su libro importante. Un historial de premios se lee de otra manera: **lo
último primero**, como un currículo. Nadie ordena sus méritos a mano, y una lista que se puede
reordenar es una lista donde el orden pasa a ser información.

Orden: **año descendente**, y los que no llevan año al final, por orden de alta descendente.
Es la misma regla que `Bibliography` aplica al colocar una obra nueva —«sin año ordena peor
que cualquier fecha»— pero aquí gobierna la lista entera y no hay columna `position`.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Writer` | Añadir, editar y borrar **sus** premios | Cuenta activada |
| Cualquiera, con sesión o sin ella | Ver los premios de un perfil | Los que el perfil deje ver |

**Es contenido público**, como la bibliografía: acreditar una trayectoria es enseñarla. Y por
lo mismo que allí, la lista **pregunta primero por el perfil**: uno restringido o eliminado
responde `404`, o esta lista sería un camino lateral para confirmar que una cuenta existe justo
cuando su titular ha pedido que no se sepa (`FEAT-USR-038`).

## Reglas de negocio

- `RN-1` Un premio pertenece a **un único usuario** y solo él lo gestiona.
- `RN-2` Es **contenido público**: sale en el perfil que ven los demás, con el techo de
  privacidad del perfil.
- `RN-3` **No se valida contra nada.** Lo declara el autor.
- `RN-4` Solo el **título** es obligatorio.
- `RN-5` El año, si viene, tiene que ser creíble: se reutiliza el mismo rango que
  `PublishedBookPolicy` (de 1450 al año que viene). El tope atrapa el error de teclado, no
  discute la trayectoria de nadie.
- `RN-6` El enlace es **externo** y se valida con `WebAddress`: solo `http` y `https`. Sale de
  la plataforma, y el cliente lo abre aparte y sin arrastrar la sesión.
- `RN-7` Como mucho **50 premios** por perfil, el mismo tope que la bibliografía. Un perfil no
  es un palmarés interminable.
- `RN-8` El orden es **año descendente**, sin año al final. El autor no lo reordena.
- `RN-9` No interviene en créditos, ni en lectores beta, ni en rankings. No publica eventos.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Título vacío | Se rechaza | `422` `VALIDATION_FAILED` |
| Título de más de 180 caracteres | Se rechaza | `422` `AWARD_TITLE_TOO_LONG` |
| Año fuera de rango | Se rechaza | `422` `IMPLAUSIBLE_AWARD_YEAR` |
| Enlace que no es `http`/`https` | Se rechaza | `422` `INVALID_AWARD_URL` |
| Llegar a 50 | Se rechaza | `409` `TOO_MANY_AWARDS` |
| Premio de otra persona | Se rechaza **diciéndolo** | `403` `NOT_YOUR_AWARD` |
| Premio inexistente | | `404` `AWARD_NOT_FOUND` |
| Perfil restringido o eliminado | Igual que inexistente | `404` |

`NOT_YOUR_AWARD` responde `403` y no `404`, igual que en la bibliografía: esconder la
existencia de algo solo tiene sentido cuando lo que se protege es saber que existe, y un
premio se enseña en un perfil abierto.

## Contrato de API

| Operación | Método y ruta | `operationId` | Documento |
|---|---|---|---|
| Ver los premios de alguien | `GET /users/{userId}/awards` | `listAwards` | `openapi/paths/profiles.yaml` |
| Añadir | `POST /me/awards` | `addAward` | `openapi/paths/profiles.yaml` |
| Editar | `PATCH /me/awards/{awardId}` | `updateAward` | `openapi/paths/profiles.yaml` |
| Borrar | `DELETE /me/awards/{awardId}` | `deleteAward` | `openapi/paths/profiles.yaml` |

No pagina, por el tope de 50.

`PATCH` y no `PUT`: se editan los campos que vienen. Mandar la ficha entera para cambiar un año
obliga al cliente a reenviar lo que no toca, y un campo que se olvida se borra sin querer.

## Eventos

Ninguno. Esto no mueve créditos, no cuenta como relato y no interesa a ningún otro contexto. Un
evento «por si acaso» es un contrato que luego hay que mantener.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Tabla nueva `user_ctx.award`:

| Columna | Tipo | Nota |
|---|---|---|
| `id` | `UUID` | |
| `user_id` | `UUID` | Índice `(user_id, year DESC NULLS LAST, created_at DESC)` |
| `title` | `VARCHAR(180)` | |
| `awarded_by` | `VARCHAR(180)` | Nulo |
| `year` | `SMALLINT` | Nulo |
| `note` | `VARCHAR(280)` | Nulo |
| `url` | `VARCHAR(512)` | Nulo |
| `created_at` | `TIMESTAMP` | Desempata a los que no llevan año |

Migración `Version20260928010000`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| P-19 | ¿Qué contiene «Premios y reconocimientos»? | **Resuelta:** una ficha declarada por el autor —título, quién lo concede, año, nota y enlace—, sin imagen y sin validar contra nada |

## Estado

**Especificación:** `APPROVED` (2026-09-26). Redactada junto con la implementación, sobre la
maqueta y las decisiones ya tomadas.

**Implementación:** `DONE` (2026-09-26). Añadir, editar, reordenar y retirar premios, con el tope de cincuenta por autor.

**Un premio sin año va al final, no al principio.** DQL no tiene `NULLS LAST`, así que el
orden lo resuelve una columna oculta en la consulta; escribirlo al revés habría puesto lo
que no se sabe cuándo pasó por delante de lo de este año.
