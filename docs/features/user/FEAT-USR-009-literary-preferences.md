---
id: FEAT-USR-009
title: Editar las preferencias literarias
context: User
concept: Profile
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/ui/settings.md
  - conversation:2026-09-24
endpoints:
  - GET /me/literary-preferences
  - PUT /me/literary-preferences
events: [LiteraryPreferencesUpdated]
depends_on: [FEAT-USR-023]
updated: 2026-09-24
---

# FEAT-USR-009 — Editar las preferencias literarias

## Resumen

Los géneros que le interesan a alguien se eligen en el onboarding
([`FEAT-USR-023`](FEAT-USR-023-onboarding-select-genres.md)) y **se pueden cambiar después**,
desde «Configuración › Perfil» ([`settings.md`](../../ui/settings.md), `S-18`).

Esto no es una funcionalidad nueva sino **la otra puerta a un dato que ya existe**. `RN-6` de
`FEAT-USR-023` lo dice sin ambigüedad: la selección del onboarding y las preferencias
editables son la misma información y se guardan en un único sitio. Lo que falta es el
endpoint para leerla y reemplazarla fuera del onboarding.

> Por qué importa que sea el mismo dato: los géneros alimentan las sugerencias de autores
> (`FEAT-COM-016`) y las recomendaciones del catálogo. Dos copias que puedan discrepar
> significan recomendaciones que contradicen lo que la persona cree haber elegido.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Leer sus preferencias | Sesión iniciada |
| `User` | Reemplazarlas | Sesión iniciada **y cuenta activada** (`RN-7`) |

Solo las propias. Los géneros de otra persona no se consultan por esta vía: forman parte de
su perfil, y qué enseña un perfil lo deciden `FEAT-USR-014` y los ajustes de privacidad.

## Reglas de negocio

- `RN-1` La selección **reemplaza** a la anterior; no se acumula. Elegir géneros es elegir un
  conjunto, y quien quita uno espera que desaparezca.
- `RN-2` Se exigen **como mínimo tres**, los mismos que en el onboarding. Un mínimo que solo
  rige el primer día no es un mínimo: bastaría con terminar el onboarding y volver a esta
  pantalla para quedarse con uno.
- `RN-3` Sin máximo, igual que en el onboarding (`FEAT-USR-023` `RN-2`).
- `RN-4` Los duplicados **se normalizan**, no se rechazan. Enviar dos veces el mismo género es
  un fallo del cliente, no una decisión de la persona. Como consecuencia,
  `['POETRY','poetry','POETRY']` **no** llega al mínimo de tres: son un género.
- `RN-5` Solo se aceptan géneros del catálogo vigente. Un código desconocido se rechaza **y se
  nombra**; nunca se ignora en silencio.
- `RN-6` **Excepción a `RN-5`: un género retirado que la persona ya tenía elegido se acepta.**
  Ver abajo.
- `RN-7` Editarlas exige la cuenta activada
  ([`decision:0003`](../../decisions/0003-write-operations-require-activated-account.md)). El
  paso del onboarding es la excepción que compra la entrada, y termina con él.
- `RN-8` Se publica `LiteraryPreferencesUpdated` con la selección **entera**, no con lo que
  cambió: quien lo consume quiere con qué quedarse.
- `RN-9` El catálogo se consulta (`GET /genres`), no se codifica en el cliente
  (`FEAT-USR-023` `RN-5`).

## Un género retirado no obliga a rehacer la selección

El catálogo es curable: un género puede retirarse sin borrarse, porque hay personas y obras
que ya apuntan a su código (`Genre::retire()`).

Eso abre un caso que parece menor y no lo es. Si alguien eligió `HUMOUR`, el género se retira,
y esa persona entra a Configuración a **cambiar otra cosa** —quitar un género, añadir otro—,
el cliente reenvía la lista que está viendo, `HUMOUR` incluido. Con `RN-5` a rajatabla, su
guardado falla por algo que no ha tocado y que no puede arreglar.

| Caso | Comportamiento |
|---|---|
| Código que no existe en el catálogo, ni activo ni retirado | Se rechaza siempre |
| Código **retirado** que la persona **ya tenía** | Se acepta: conservarlo no es elegirlo |
| Código **retirado** que la persona **no tenía** | Se rechaza: no se puede elegir de nuevo |

Es la misma idea que en el resto de la pantalla (`S-37`): **un guardado no debe caerse por un
campo que quien guarda no estaba editando**.

Un género retirado desaparece de `GET /genres`, así que el cliente sabe cuáles de las
preferencias de alguien ya no se ofrecen: son las que están en sus preferencias y no en el
catálogo.

## Flujo principal

1. La pantalla pide el catálogo (`GET /genres`) y la selección actual
   (`GET /me/literary-preferences`).
2. La persona marca y desmarca géneros.
3. Al guardar, el cliente envía **la lista completa**.
4. El sistema normaliza, comprueba el mínimo y valida contra el catálogo.
5. La selección anterior se sustituye por la nueva, en una transacción.
6. Se publica `LiteraryPreferencesUpdated`.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Menos de tres géneros | Se rechaza | `422` con `code: NOT_ENOUGH_GENRES` |
| Género inexistente | Se rechaza y se nombra | `422` con `code: UNKNOWN_GENRE` |
| Género retirado que ya tenía | Se acepta | `200` |
| Género retirado que no tenía | Se rechaza | `422` con `code: UNKNOWN_GENRE` |
| Géneros repetidos | Se normalizan (`RN-4`) | `200` |
| Cuenta sin activar | Se rechaza | `403` con `code: ACCOUNT_NOT_ACTIVATED` |
| Sin sesión | Se rechaza | `401` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Leer mis preferencias | `GET /me/literary-preferences` | `getMyLiteraryPreferences` |
| Reemplazarlas | `PUT /me/literary-preferences` | `updateLiteraryPreferences` |

`PUT` y no `PATCH`: el cuerpo es la selección entera y sustituye a la anterior. Es lo
contrario que el perfil (`FEAT-USR-008`), y por una razón: allí los campos son
independientes y omitir uno significa «déjalo como está»; aquí el dato **es** la lista, y una
lista parcial no quiere decir nada.

Las dos respuestas devuelven los géneros con su nombre presentable, no solo el código, para
que la pantalla pueda pintarlos sin cruzar con el catálogo.

Endpoint propio y no un campo de `PATCH /me/profile`, por lo mismo que el nombre de usuario:
la pestaña comparte «Guardar», pero un fallo en un campo no debe llevarse por delante los
demás (`S-37`).

## Eventos

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `LiteraryPreferencesUpdated` | Se guarda la selección | `Community` (sugerencias y read models de recomendación) | `userId`, `genres` |

Es **el mismo evento** que publica el paso del onboarding. Para quien lo consume no hay
diferencia entre elegir por primera vez y cambiar de opinión: en los dos casos lo que cambia
es a qué se parece esa persona.

## Modelo de datos afectado

Ninguno nuevo. `user_genre` y `genre` ya existen desde `FEAT-USR-023`, y esta funcionalidad
escribe exactamente donde escribe el onboarding. **Sin migración.**

## Criterios de aceptación

- [x] Leer las preferencias devuelve los géneros elegidos en el onboarding, con su nombre.
- [x] Guardar una selección nueva sustituye a la anterior, y lo quitado desaparece.
- [x] Guardar dos géneros se rechaza con `NOT_ENOUGH_GENRES`.
- [x] Un código desconocido se rechaza con `UNKNOWN_GENRE` y el mensaje lo nombra.
- [x] Los duplicados no cuentan dos veces para el mínimo.
- [x] Un género retirado que la persona ya tenía se puede conservar al guardar.
- [x] Un género retirado que no tenía no se puede elegir.
- [x] Editar sin la cuenta activada se rechaza; leer no.
- [x] Se publica `LiteraryPreferencesUpdated` con la selección entera.
- [x] Lo guardado aquí es lo que devuelve el onboarding, y al revés.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| S-18 | ¿Están las preferencias literarias en la pestaña «Perfil»? | **Resuelta:** sí ([`settings.md`](../../ui/settings.md)) |
| L-2 | ¿Cambiar de géneros debería rehacer las sugerencias de autores ya vistas? | Hoy solo se publica el hecho; qué hace `Community` con él es suyo |
| L-3 | ¿Hay un máximo razonable? | `FEAT-USR-023` `RN-2` lo dejó abierto y aquí se hereda. Sin máximo, alguien puede marcarlos todos y quedarse sin filtro útil |
| G-1 | ¿Son el mismo catálogo los géneros del lector y la temática de una obra? | Heredada de `FEAT-USR-023`. Hoy **sí**: `Work` valida contra este mismo catálogo por contrato |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Es el reverso de una ficha ya aprobada e
implementada: el dato, el catálogo, la tabla y el evento los fija `FEAT-USR-023`, y lo único
que esta añade es la puerta para editarlo fuera del onboarding y qué hacer con un género
retirado.

**Implementación:** `DONE` (2026-09-24). `GET` y `PUT /api/v1/me/literary-preferences`, sobre
la tabla y el evento que ya existían. **Sin migración**, como la ficha anticipaba.

### Las reglas viven en un solo sitio

`GenreSelection` decide qué es una selección válida —mínimo, duplicados, catálogo, género
retirado— y la usan **las dos puertas**: el paso del onboarding y esta pantalla. Duplicarlas
es cómo se acaba pudiendo quedarse con un género desde una pantalla y no desde la otra, que
es justo lo que un mínimo de tres pretende impedir.

Por lo mismo, `NotEnoughGenres` y `UnknownGenre` se han movido de `Onboarding` a `Profile`.
El onboarding es donde saltan primero, pero lo que protegen son las preferencias literarias,
y esas son de `Profile`: el onboarding es una de sus dos puertas, no su dueño.

### El fallo que encontró esta funcionalidad

Guardar conservando un género que ya se tenía **devolvía un `500`**, y no solo aquí: el mismo
fallo estaba en la reclasificación de una obra (`PUT /works/{workId}/genres`).

La causa es la misma en los dos sitios. «Sustituir la lista» estaba implementado como borrar
todo y volver a insertar, y eso funciona mientras las dos listas no se solapen. En cuanto se
solapan, la fila conservada se marca para borrar y se registra otra vez con la misma
identidad —`(user_id, genre_code)`, `(work_id, genre_code)`— dentro de la misma transacción,
y la unidad de trabajo de Doctrine se encuentra dos objetos para el mismo identificador.

Las pruebas que había no lo cogían porque todas sustituían por listas **disjuntas**, que es
el caso raro: lo normal es cambiar un género y dejar los otros.

Ahora las dos operaciones escriben **solo la diferencia**. Tiene un efecto secundario que es
más correcto: `selectedAt` de lo que se conserva no se toca, porque ese género se eligió
cuando se eligió. Hay una prueba de regresión en cada puerta.

### Un `PUT` y no un `PATCH`

El cuerpo es la selección entera. Es lo contrario que el perfil (`FEAT-USR-008`), donde los
campos son independientes y omitir uno significa «déjalo como está»; aquí el dato **es** la
lista, y una lista parcial no significa nada.

### Qué no lleva

No hay endpoint para las preferencias de otra persona. Los géneros son parte de su perfil, y
quién ve qué de un perfil lo deciden `FEAT-USR-014` y sus ajustes de privacidad; abrir una
puerta lateral aquí se saltaría ambos.

`L-3` sigue abierta: no hay máximo, así que alguien puede marcarlos los dieciocho y quedarse
sin filtro útil. Es una decisión de producto, no una omisión.
