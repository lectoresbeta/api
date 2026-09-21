---
id: FEAT-USR-023
title: Onboarding paso 2 — elegir al menos tres géneros de interés
context: User
concept: Onboarding
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - figma:1800-13778 (1470:9581, nota 1679:9226)
  - docs/ui/account-creation.md
endpoints: [GET /genres, PUT /me/onboarding/genres]
events: [LiteraryPreferencesUpdated]
depends_on: [FEAT-USR-022]
updated: 2026-09-21
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

## Catálogo de géneros

Visibles en el diseño, con el identificador propuesto:

| Español | `Genre` |
|---|---|
| Aventura | `ADVENTURE` |
| Ciencia Ficción | `SCIENCE_FICTION` |
| Comedia | `COMEDY` |
| Drama | `DRAMA` |
| Fantasía | `FANTASY` |
| Histórico | `HISTORICAL` |
| Infantil | `CHILDREN` |
| Misterio | `MYSTERY` |
| Poesía *(el diseño dice «Poeta», ver `OB-5`)* | `POETRY` |
| Policíaco | `CRIME` |
| Romance | `ROMANCE` |
| Terror | `HORROR` |
| Thriller | `THRILLER` |

El diseño muestra además cuatro chips `Sample`, lo que confirma que la lista está
incompleta. El catálogo debe servirse desde el backend con su nombre presentable, no
mantenerse como un enum cerrado en el cliente.

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
- [ ] Los géneros elegidos en el onboarding son los mismos que devuelve y edita `FEAT-USR-009`.
- [ ] El usuario puede completar este paso sin haber activado su cuenta.
- [ ] Añadir un género nuevo al catálogo no exige desplegar el frontend.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| OB-5 | ¿«Poeta» es «Poesía»? | Nombre del género en el catálogo |
| OB-14 | ¿Cuál es el catálogo completo? ¿Es administrable o fijo? ¿Hay máximo de géneros por usuario? | Los chips `Sample` indican que está sin cerrar |
| OB-13 | ¿El botón se llama «Siguiente» o «Continuar»? | El diseño y la nota no coinciden |
| G-1 | ¿Los géneros del interés del lector son el mismo catálogo que la temática de una obra? | Si no, hay dos catálogos y las recomendaciones no cruzan |

`G-1` es más importante de lo que parece: si un lector elige «Terror» y las obras se
clasifican con otro vocabulario, ninguna recomendación funcionará.

## Estado

**Especificación:** `DRAFT`. Falta cerrar el catálogo (`OB-14`) y confirmar `G-1`.

**Implementación:** `TODO`.
