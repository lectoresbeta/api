---
id: FEAT-USR-023
title: Onboarding paso 2 — elegir al menos tres géneros de interés
context: User
concept: Onboarding
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P0
sources:
  - figma:1800-13778 (1470:9581, nota 1679:9226)
  - docs/ui/account-creation.md
endpoints: [GET /genres, PUT /me/onboarding/genres]
events: [LiteraryPreferencesUpdated]
depends_on: [FEAT-USR-022]
updated: 2026-09-24
---

# FEAT-USR-023 — Onboarding paso 2: elegir al menos tres géneros

## Resumen

El usuario marca los géneros literarios que le interesan, con un mínimo de tres. Esta
selección alimenta las sugerencias de autores del paso siguiente y, más adelante, las
recomendaciones de obras del catálogo.

Nota del diseño: *«El botón de "Continuar" sólo se activará en el momento en que se hayas
seleccionado 3 o más temáticas»*.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Elegir sus géneros | Sesión iniciada. No requiere cuenta activada |

## El catálogo de géneros

**Propuesta aplicada** (`OB-14`). Dieciocho géneros, pensados para que un autor encuentre el
suyo sin tener que leer una lista interminable:

| Género | Código |
|---|---|
| Ficción literaria | `LITERARY_FICTION` |
| Novela negra y policíaca | `CRIME` |
| Thriller y suspense | `THRILLER` |
| Ciencia ficción | `SCIENCE_FICTION` |
| Fantasía | `FANTASY` |
| Terror | `HORROR` |
| Romántica | `ROMANCE` |
| Histórica | `HISTORICAL` |
| Aventuras | `ADVENTURE` |
| Juvenil | `YOUNG_ADULT` |
| Infantil | `CHILDRENS` |
| Relato corto | `SHORT_STORY` |
| Poesía | `POETRY` |
| Teatro | `DRAMA` |
| Ensayo | `ESSAY` |
| Biografía y memorias | `MEMOIR` |
| Crónica y no ficción narrativa | `NARRATIVE_NONFICTION` |
| Humor | `HUMOUR` |

Tres criterios detrás de la lista:

- **Ni demasiado corta ni demasiado larga.** Con seis géneros, todo el mundo elige «ficción» y
  el filtro no sirve. Con cincuenta, nadie los lee y se eligen los tres primeros.
- **Mezcla géneros y formas.** «Poesía», «teatro» y «relato corto» no son temáticas sino
  formas, pero es como un autor busca y como un lector filtra.
- **Reconocibles sin explicación.** Ningún autor debería dudar de dónde encaja su texto.

`SHORT_STORY` convive con las temáticas a propósito: en esta plataforma la extensión es una
señal tan útil como el tema, porque determina cuánto trabajo cuesta corregir.

**Revísala cuando puedas.** Es un enum que aparecerá en migraciones, filtros y en el perfil de
cada usuario, y cambiarlo después obliga a migrar datos.

## Reglas de negocio

- `RN-1` Se exigen **como mínimo tres** géneros. La validación es de servidor: que el botón
  esté deshabilitado en el cliente no es una garantía.
- `RN-2` No hay máximo definido (`OB-14`).
- `RN-3` Solo se aceptan géneros del catálogo vigente. Un identificador desconocido se
  rechaza; no se ignora en silencio.
- `RN-4` La selección no admite duplicados.
- `RN-5` El catálogo de géneros **se consulta, no se codifica en el cliente**. La pantalla
  incluye chips de relleno y scroll, así que la lista no está cerrada.
- `RN-6` La selección es la misma información que las preferencias literarias editables
  después (`FEAT-USR-009`): se guarda en un único sitio, no se duplica.
- `RN-7` El paso es obligatorio para completar el onboarding.
- `RN-8` Este paso funciona con la cuenta en `PENDING_ACTIVATION` (`FEAT-USR-025`, `RN-5`).

## En qué se aparta del diseño

El catálogo vigente es el de [arriba](#el-catálogo-de-géneros), que es el que siembra la
migración `Version20260923174500`. El diseño de Figma mostraba trece chips más cuatro
`Sample`, lo que ya indicaba que la lista no estaba cerrada. Diferencias que conviene tener
presentes:

| Diseño | Catálogo | Por qué |
|---|---|---|
| «Poeta» | `POETRY` — Poesía | Era el único chip que nombraba a la persona y no a la obra |
| Comedia | `HUMOUR` — Humor | En narrativa el término es «humor»; «comedia» es teatro o cine |
| Drama | `DRAMA` — **Teatro** | **Mismo código, significado distinto.** Aquí `DRAMA` es la forma teatral, no el tono |
| Misterio | — | Absorbido por `CRIME` y `THRILLER` |
| — | `LITERARY_FICTION`, `ESSAY`, `MEMOIR`, `NARRATIVE_NONFICTION`, `SHORT_STORY`, `YOUNG_ADULT` | El diseño no cubría la no ficción ni las formas breves |

La fila de `DRAMA` es la que merece confirmación de producto: un cliente que asuma el
significado del diseño mostrará «Drama» donde el catálogo dice «Teatro».

El catálogo se sirve desde el backend con su nombre presentable (`GET /genres`), no se
mantiene como un enum cerrado en el cliente.

Este mismo catálogo se usa en la búsqueda de obras (`FEAT-WRK-012`), en la búsqueda de
autores (`FEAT-USR-017`) y en los filtros de los rankings (`FEAT-COM-013`, `FEAT-COM-014`).

## Flujo principal

1. El sistema devuelve el catálogo de géneros.
2. El usuario marca los que le interesan; puede quitarlos con la «×».
3. Al llegar a tres, el botón se habilita.
4. El usuario continúa.
5. El sistema valida el mínimo y que todos existan, y guarda la selección.
6. El estado pasa a `SUGGESTIONS_PENDING`.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Menos de tres géneros | Se rechaza | `422` con `code: NOT_ENOUGH_GENRES` |
| Género inexistente | Se rechaza (`RN-3`) | `422` con `code: UNKNOWN_GENRE` |
| Géneros repetidos | Se normaliza o se rechaza. **Por definir** | — |
| Paso anterior sin completar | Se rechaza | `409` con `code: ONBOARDING_STEP_OUT_OF_ORDER` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Listar catálogo de géneros | `GET /genres` | `listGenres` |
| Guardar géneros elegidos | `PUT /me/onboarding/genres` | `submitOnboardingGenres` |

`GET /genres` es público: el catálogo no es información sensible y se necesita también en
las pantallas de búsqueda.

## Eventos

| Evento | Cuándo | Consumidores |
|---|---|---|
| `LiteraryPreferencesUpdated` | Se guardan los géneros | `Community` (sugerencias y read models de recomendación) |

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `genre` | Catálogo: identificador, nombre, orden, activo |
| `user_genre` | Relación usuario–género |

Índice sobre `user_genre(user_id)` y sobre `user_genre(genre_id)`: la segunda dirección es la
que necesita `FEAT-COM-016` para sugerir autores por género.

## Diseño (Figma)

`1470:9581`, con la nota `1679:9226`.

Ver [`../../ui/account-creation.md`](../../ui/account-creation.md).

## Criterios de aceptación

- [ ] Guardar tres géneros válidos avanza el estado a `SUGGESTIONS_PENDING`.
- [ ] Guardar dos géneros devuelve `422`, aunque el cliente lo permita.
- [ ] Guardar un identificador de género inexistente devuelve `422`.
- [ ] `GET /genres` devuelve el catálogo con nombre presentable e identificador.
- [x] Los géneros elegidos en el onboarding son los mismos que devuelve y edita `FEAT-USR-009`.
- [ ] El usuario puede completar este paso sin haber activado su cuenta.
- [ ] Añadir un género nuevo al catálogo no exige desplegar el frontend.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| OB-5 | ¿«Poeta» es «Poesía»? | **Resuelto:** sí, se corrige a «Poesía» / `POETRY` |
| OB-14 (revisar) | ¿Cuál es el catálogo completo? ¿Es administrable o fijo? ¿Hay máximo de géneros por usuario? | Los chips `Sample` indican que está sin cerrar |
| OB-13 | ¿El botón se llama «Siguiente» o «Continuar»? | **Resuelto:** «Siguiente» en todo el onboarding |
| G-1 | ¿Los géneros del interés del lector son el mismo catálogo que la temática de una obra? | Si no, hay dos catálogos y las recomendaciones no cruzan |

`G-1` es más importante de lo que parece: si un lector elige «Terror» y las obras se
clasifican con otro vocabulario, ninguna recomendación funcionará.

## Estado

**Especificación:** `APPROVED` (2026-09-24). `OB-14` resuelta con un catálogo de 18 géneros, pendiente de
tu revisión. Lo que queda son detalles de presentación.

**Implementación:** `DONE`.

`GET /api/v1/genres` y `PUT /api/v1/me/onboarding/genres`, con el mínimo de tres, el rechazo
nombrado de los géneros desconocidos, el orden de los pasos y la publicación de
`LiteraryPreferencesUpdated` con la selección entera. Cubierto por
`tests/Functional/User/OnboardingTest.php`.

Dos decisiones que la ficha dejaba abiertas y que la implementación cierra:

- **los duplicados se normalizan, no se rechazan** (`RN-4`). Enviar dos veces el mismo género
  es un fallo del cliente, no una decisión de la persona, y el conjunto que quería decir no es
  ambiguo. Como consecuencia, `['DRAMA','drama','DRAMA']` **no** llega al mínimo de tres, que
  es lo correcto: son un género;
- la selección **sustituye** a la anterior en vez de acumularse.

**Falta:** `RN-2` sigue sin máximo definido, así que no hay ninguno.

`RN-6` deja de ser una promesa: [`FEAT-USR-009`](FEAT-USR-009-literary-preferences.md) lee y
escribe esta misma selección, y las reglas de qué es válida son literalmente el mismo código
(`GenreSelection`), no dos copias que puedan separarse. Por eso `NotEnoughGenres` y
`UnknownGenre` viven ahora en `Profile`: saltan aquí primero, pero lo que protegen son las
preferencias literarias.

Aquella implementación tenía además un fallo que nadie había visto: volver a elegir géneros
**conservando uno** de los anteriores devolvía un `500`. Las pruebas sustituían siempre por
listas disjuntas, que es el caso raro. Está corregido y cubierto.
