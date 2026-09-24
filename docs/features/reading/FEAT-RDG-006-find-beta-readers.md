---
id: FEAT-RDG-006
title: Buscar lectores beta a quienes invitar
context: Reading
concept: AccessInvitation
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - _sources/use-cases.pdf
  - docs/features/reading/FEAT-RDG-004-invite-beta-reader.md
  - docs/features/user/FEAT-USR-038-privacy-settings.md
endpoints:
  - GET /works/{workId}/invitable-readers
events: []
depends_on: [FEAT-RDG-004]
updated: 2026-09-24
---

# FEAT-RDG-006 — Buscar a quién invitar

## Resumen

[`FEAT-RDG-004`](FEAT-RDG-004-invite-beta-reader.md) invita **por identificador de usuario**, y
no dijo de dónde sale ese identificador. Sale de aquí.

Es un buscador de escritura anticipada: el autor teclea un nombre y elige de una lista corta.
Sin esto, invitar existe pero no se puede usar.

## Esto no es un buscador de personas nuevo

Es lo más importante de la ficha y conviene decirlo antes que nada.

**El índice de personas es de `User`**, que posee los perfiles, los nombres de usuario y las
preferencias literarias. Ya hay una funcionalidad para eso,
[`FEAT-USR-017`](../README.md) —buscar autores por nombre o temática—, y construir aquí un
segundo índice sobre las mismas personas sería exactamente lo que `AGENTS.md` prohíbe: dos
contextos consultando el mismo modelo.

Lo que esta ficha añade **no es la búsqueda, es el descarte**: quitar de la lista a quien ya
está dentro. Y eso solo lo sabe `Reading`.

```text
GET /works/{workId}/invitable-readers?query=ana

Reading ──ReaderDirectory.search("ana")──▶ User    devuelve candidatos
   │
   └─ quita: el autor, quien ya es lector beta,
             quien tiene invitación o solicitud abierta
```

Así el emparejamiento de nombres vive en un sitio —`User`— y lo usan las dos pantallas. Cuando
`FEAT-USR-017` se implemente, expondrá **el mismo servicio** por su propio endpoint.

`ReaderDirectory` es el tercer contrato que `Reading` consume de `User`, y no crea ningún ciclo
nuevo: esa dirección ya estaba abierta ([`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md)).

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Writer` | Buscar a quién invitar a **su** obra | Es el autor; una obra ajena responde `404` |
| Cualquiera | — | No hay listado general de personas por aquí |

Que cuelgue de la obra no es decorativo: **liga la búsqueda a un autor con una obra**, que es
la única razón legítima para recorrer personas en este contexto.

## Reglas de negocio

- `RN-1` Solo el autor de la obra busca sobre ella. Una obra ajena o inexistente responden lo
  mismo.
- `RN-2` Se busca por **nombre y nombre de usuario**. Nunca por correo. Ver abajo.
- `RN-3` La consulta tiene un **mínimo de dos caracteres**. Menos devuelve lista vacía, no el
  directorio.
- `RN-4` **No hay paginación.** Un tope duro de resultados y ahí se acaba. Ver abajo.
- `RN-5` Solo aparecen cuentas **activas**. Una eliminada no aparece nunca: está anonimizada.
- `RN-6` No aparece quien **ya está dentro** de esa obra: el propio autor, quien tiene acceso
  vivo, y quien tiene una invitación o una solicitud abierta.
- `RN-7` Cada resultado lleva **lo mismo que una tarjeta de perfil**: identificador, nombre de
  usuario, nombre y avatar. Nada más.
- `RN-8` El ajuste de privacidad del perfil es un **techo**: quien lo tenga en `NOBODY` no
  aparece ([`FEAT-USR-038`](../user/FEAT-USR-038-privacy-settings.md)). Ver abajo.
- `RN-9` Buscar no avisa a nadie ni deja rastro visible para el buscado.

## `RN-2` — por qué no se busca por correo

Un buscador que acepta una dirección de correo responde, sin querer, a otra pregunta: **«¿esta
persona tiene cuenta aquí?»**. Eso convierte el formulario en un comprobador de direcciones
para cualquiera con una lista.

El proyecto ya toma esa precaución en el registro, donde la respuesta no revela si un correo
está dado de alta ([`FEAT-USR-001`](../user/FEAT-USR-001-register-with-email.md) `RN-14`), y
sería incoherente abrir por aquí lo que allí se cerró.

Invitar por correo a alguien que **no** tiene cuenta es otra cosa —una invitación a la
plataforma, que es de `User`— y no se mezcla con esta.

## `RN-3` y `RN-4` — el directorio no se puede recorrer

Las dos reglas son la misma precaución vista dos veces: **una lista de personas que se puede
enumerar es una lista de personas que alguien acabará descargando.**

- sin mínimo de caracteres, la consulta vacía devuelve a todo el mundo;
- con paginación, veinte peticiones devuelven a todo el mundo igual, solo que más despacio.

Por eso este endpoint se aparta de la convención de paginar toda colección
([`paginación`](../../api/conventions/pagination.md)) y **es una excepción justificada**: no es
una colección que alguien recorre, es una ayuda a escribir un nombre. Quien no encuentra a
quien busca teclea dos letras más, que es lo que se hace de verdad con un desplegable de
autocompletado.

El tope es **diez**. Si el nombre es tan común que no cabe, afinar es más rápido que paginar.

## `RN-8` — el techo de privacidad, hoy inerte

[`FEAT-USR-038`](../user/FEAT-USR-038-privacy-settings.md) está aprobada y **no implementada**:
nadie tiene todavía ajustes de privacidad, y su valor por defecto es `EVERYONE` (`RN-4` de esa
ficha). Así que hoy la regla no descarta a nadie.

Se escribe igualmente, y el descarte se implementa en el sitio que le corresponde —dentro de
`User`, que es quien poseerá el ajuste— para que el día que exista **no haya que acordarse de
esta pantalla**. Un buscador de personas al que hay que volver a enseñarle a respetar la
privacidad es un buscador que un día no la respeta.

## Flujo principal

1. El autor abre «invitar» en su obra y teclea dos o más caracteres.
2. `Reading` comprueba que la obra es suya.
3. Pide candidatos a `User` por el contrato, con margen sobre el tope.
4. Descarta a quien ya está dentro (`RN-6`).
5. Devuelve hasta diez.

Se piden más candidatos de los que se devuelven precisamente porque el descarte ocurre después:
pedir diez y quitar tres dejaría siete donde había diez disponibles.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| La obra no existe o no es suya | La misma respuesta | `404 WORK_NOT_FOUND` |
| Consulta de menos de dos caracteres | Lista vacía, **no un error** | `200` con `readers: []` |
| Nadie coincide | Lista vacía | `200` |
| Todos los que coinciden ya están dentro | Lista vacía | `200` |
| Sin sesión | — | `401` |

Una consulta demasiado corta no es un error porque el cliente la manda en cada pulsación: la
primera letra de un nombre no es una equivocación del usuario, es el principio de lo que está
escribiendo.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Buscar a quién invitar | `GET /api/v1/works/{workId}/invitable-readers` | `searchInvitableBetaReaders` |

Parámetros: `query`. Sin `cursor` ni `limit`, por `RN-4`.

## Eventos

No publica ni consume. Es una consulta.

## Contratos

**Consume**, nuevo:

| Contrato | De | Responde |
|---|---|---|
| `ReaderDirectory` | `User` | Hasta N personas cuyo nombre o nombre de usuario encajan: identificador, nombre de usuario, nombre y avatar |

Devuelve **una tarjeta de perfil y nada más**. Ni correo, ni fecha de nacimiento, ni estado de
la cuenta: quien pregunta está dibujando una fila de un desplegable.

## Modelo de datos afectado

Ninguno. Ni tabla ni migración: se consulta `user_ctx.account`, que ya tiene índice por
`name` y unicidad por `username`.

El emparejamiento por nombre es `%texto%` y no usa índice. Con el volumen previsible no
importa; si importara, la respuesta es un índice trigram (`pg_trgm`) en `User`, no un read
model en `Reading`.

## Criterios de aceptación

- [x] El autor teclea dos letras y recibe hasta diez personas.
- [x] Encuentra por nombre y por nombre de usuario.
- [x] Una dirección de correo no encuentra a su dueño.
- [x] Una consulta de una letra devuelve lista vacía y no el directorio.
- [x] No aparece el propio autor.
- [x] No aparece quien ya es lector beta de esa obra.
- [x] No aparece quien tiene una invitación abierta, ni quien tiene una solicitud abierta.
- [x] Vuelve a aparecer si su invitación se retira.
- [x] Buscar sobre la obra de otra persona responde `404`.
- [x] El resultado no lleva correo ni ningún dato privado.
- [x] Una cuenta sin activar no aparece. *Una eliminada tampoco, pero eso no se puede provocar todavía: `FEAT-USR-013` no está implementada.*

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| R-19 | ¿Debería el autor ver algo que le ayude a elegir —temáticas que lee, correcciones hechas— y no solo el nombre? | Es lo que convertiría una lista de nombres en una recomendación. Depende de `Community` y de `FEAT-USR-017` |
| R-20 | ¿Se puede buscar entre los propios grupos de lectores beta? | [`FEAT-RDG-007`](../README.md), que tampoco existe |
| R-21 | ¿Un usuario bloqueado debe desaparecer de esta lista? | Sí, casi seguro, pero [`FEAT-COM-034`](../community/FEAT-COM-034-block-user.md) no existe |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las tres decisiones con consecuencias visibles
—no buscar por correo, no paginar y aparecer solo si la cuenta está activa— son las tres la
opción prudente, que es la que este producto toma cuando custodia datos de personas.

**Implementación:** `DONE` (2026-09-24). Ni tabla ni migración: se consulta `user_ctx.account`.

`ReaderDirectory` vive en `User` y hoy lo usa solo esta pantalla. Cuando
[`FEAT-USR-017`](../README.md) se implemente, expondrá **ese mismo servicio** por su propio
endpoint en lugar de escribir un segundo emparejamiento de nombres.

Las tres reglas que protegen a las personas buscadas —nunca por correo, solo cuentas activas y
el techo de privacidad— viven **dentro del contrato** y no en quien lo llama. Un llamante que
tenga que acordarse de ellas es un llamante que algún día no se acuerda.

La única que todavía no descarta a nadie es el techo: `FEAT-USR-038` sigue sin implementarse y
su valor por defecto es `EVERYONE`. El sitio donde irá está escrito y comentado en
`SearchReaderDirectory`.
