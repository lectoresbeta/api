---
id: FEAT-COM-027
title: Mis Amigos — seguidos y seguidores
context: Community
concept: Subscription
actors: [User, Guest]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/ui/my-profile.md
  - docs/ui/user-profile.md
  - conversation:2026-09-24
endpoints:
  - GET /users/{userId}/subscriptions
  - GET /users/{userId}/subscribers
events: []
depends_on: [FEAT-COM-010, FEAT-USR-014]
updated: 2026-09-24
---

# FEAT-COM-027 — Seguidos y seguidores

## Resumen

La pestaña «Mis Amigos» del perfil propio ([`my-profile.md`](../../ui/my-profile.md)) y la
equivalente del perfil ajeno ([`user-profile.md`](../../ui/user-profile.md)): **a quién sigue
una persona y quién la sigue**, paginados.

> «Mis Amigos» no describe lo que contiene: seguir es una relación asimétrica, no una amistad
> (`P-8`, `CM-15`). El nombre de la pantalla es cosa de la interfaz; aquí los dos listados se
> llaman por lo que son.

Es la continuación natural de [`FEAT-COM-010`](FEAT-COM-010-subscribe-to-author.md), que dejó
el grafo escrito pero solo consultable de uno en uno —«¿sigo a esta persona?»—. Lo que esta
ficha añade no es la consulta, sino **la decisión que aquella aplazó**: quién puede ver el
grafo de quién.

## `CM-14`, resuelta: las listas se ven tanto como el perfil que las tiene

Las dos preguntas obvias —¿son públicas?, ¿las ve solo su dueño?— tienen la misma respuesta
mala. Públicas del todo, convierten un ajuste de privacidad en papel mojado. Solo para su
dueño, contradicen un diseño en el que el perfil ajeno **tiene esa pestaña** y su estado vacío
está escrito en tercera persona: «Juanjo todavía no sigue a otros autores».

La regla es más simple que cualquiera de las dos, y no añade un ajuste nuevo:

- **La lista hereda la visibilidad del perfil que la tiene.** Si puedes abrir el perfil de
  alguien, puedes ver a quién sigue y quién le sigue. Si su perfil te responde `404` —porque
  lo tiene en `NOBODY`, o en `FOLLOWERS` y no le sigues—, sus listas responden lo mismo.
- **Cada fila hereda la visibilidad de la persona que la ocupa.** Quien tiene su perfil
  cerrado **no aparece en la lista de nadie**.

La segunda mitad es la que importa y la que se olvidaría. Sin ella, cerrar tu perfil no sirve
de nada: bastaría con mirar los seguidores de cualquier autor popular para encontrarte. El
ajuste de `FEAT-USR-038` promete que la cuenta **no se encuentra**, y una lista ajena es
exactamente un sitio donde se encontraría.

```text
Ana tiene el perfil en NOBODY y sigue a Juanjo.

  GET /users/{juanjo}/subscribers   ──▶  Ana no sale, la mire quien la mire
  GET /users/{ana}/subscriptions    ──▶  404, salvo para la propia Ana
```

Su titular **siempre se ve a sí mismo** en sus propias listas, por la misma razón que ve su
propio perfil: esconderle lo suyo sería absurdo.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Guest` | Ver las listas de un perfil público | Ninguna. No hace falta sesión |
| `User` | Ver las listas de un perfil que puede abrir | Sesión, si la visibilidad del perfil la exige |

**Sin sesión no hay nada más que restringir**: quien pasa sin identificarse ve exactamente lo
que es público, que es la misma regla del perfil (`FEAT-USR-014`).

No hace falta cuenta activada: es solo lectura.

## Reglas de negocio

- `RN-1` Las dos listas se paginan **por cursor**, como el resto de flujos cronológicos
  ([`paginación`](../../api/conventions/pagination.md)). Se ordenan por el momento del
  seguimiento, del más reciente al más antiguo.
- `RN-2` Si el perfil del titular no es visible para quien pregunta, sus listas responden
  `404`, igual que el perfil. Nunca `403`: un `403` confirmaría que la cuenta está ahí.
- `RN-3` De cada lista se retiran las personas cuyo perfil no sea visible para quien pregunta.
- `RN-4` Una cuenta eliminada no aparece en ninguna lista, ni tiene listas propias.
- `RN-5` **Una página puede traer menos filas que el límite pedido sin que eso signifique que
  se acabó.** Lo que decide si hay más es `pageInfo.nextCursor`, nunca cuántos elementos
  llegaron. Es la consecuencia inevitable de `RN-3`: el filtro se aplica después de paginar.
- `RN-6` Ninguna de las dos lleva total. Los contadores del perfil son `FEAT-USR-028`, y
  contar aquí obligaría a contar **lo filtrado**, que es una cifra distinta para cada visitante.
- `RN-7` Las filas llevan tarjeta de perfil —`@usuario`, nombre y avatar— y no solo
  identificadores: una lista de UUID obligaría al cliente a una petición por fila.
- `RN-8` Los datos de las personas los sirve `User` por **contrato publicado**. `Community` no
  guarda una copia de los perfiles para pintar esta pantalla, y no lee sus tablas.

## Por qué el filtro vive en `User` y no aquí

`Community` sabe quién sigue a quién. **No sabe, ni debe aprender, qué hace visible a una
persona**: eso son los ajustes de privacidad, el grafo de seguidores que ya proyecta `User` y
el estado de la cuenta.

Así que el contrato que responde no es «dame estos perfiles» sino **«dame los que esta persona
puede ver, de estos»**. La diferencia es la que separa una regla que se cumple siempre de una
que se cumple mientras nadie olvide aplicarla: un consumidor que recibe perfiles y luego tiene
que acordarse de filtrarlos es un consumidor que algún día no se acuerda.

Es la misma decisión, y por el mismo motivo, que llevó el techo de privacidad dentro de
`ReaderDirectory` en [`FEAT-RDG-006`](../reading/FEAT-RDG-006-find-beta-readers.md).

## Flujo principal

1. Alguien abre el perfil de una persona y pulsa «Amigos».
2. Se pide su lista de seguidos, o la de seguidores, con un límite.
3. `Community` recorta la página por cursor y le pregunta a `User`, **de una vez para toda la
   página**, cuáles de esas personas puede ver quien pregunta.
4. Se devuelven las que puede ver, con su tarjeta, y el cursor de la siguiente página.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| El perfil del titular no es visible | Se responde como si no existiera | `404` con `code: PROFILE_NOT_FOUND` |
| El identificador no es un identificador | Lo mismo | `404` con `code: PROFILE_NOT_FOUND` |
| Cursor manipulado o ilegible | Se rechaza | `422` con `code: INVALID_CURSOR` |
| La lista está vacía | No es un error | `200` con `data: []` |
| Todas las filas de la página están filtradas | Tampoco | `200` con `data: []` y **cursor siguiente** |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| A quién sigue | `GET /users/{userId}/subscriptions` | `listAuthorSubscriptions` |
| Quién le sigue | `GET /users/{userId}/subscribers` | `listSubscribers` |

Dos endpoints y no uno con un parámetro `direction`: son dos preguntas distintas sobre la
misma tabla, y un parámetro que cambia el significado de la respuesta es un parámetro que
alguien acabará documentando mal.

`limit` por defecto 20, máximo 100. `cursor` opaco.

## Modelo de datos afectado

Ninguno nuevo. Se lee `community_ctx.author_subscription`, que ya existe, por sus dos índices
—`subscriber_id` y `author_id`—, que son exactamente las dos direcciones que esta ficha
necesita.

`User` expone un contrato nuevo, sin tabla nueva: se apoya en los perfiles, en los ajustes de
privacidad y en la proyección de seguidores que ya mantiene.

## Criterios de aceptación

- [x] La lista de seguidos devuelve a quién sigue esa persona, con su tarjeta de perfil.
- [x] La lista de seguidores devuelve quién la sigue.
- [x] Las dos se ordenan del seguimiento más reciente al más antiguo.
- [x] Las dos paginan por cursor, y la segunda página no repite ni se salta a nadie.
- [x] Un cursor manipulado se rechaza.
- [x] Quien tiene el perfil en `NOBODY` no aparece en la lista de nadie.
- [x] Quien tiene el perfil en `FOLLOWERS` aparece solo para quien le sigue.
- [x] Las listas de un perfil no visible responden `404`.
- [x] Su titular ve sus propias listas completas aunque tenga el perfil cerrado.
- [x] Sin sesión se ven las listas de un perfil público, ya filtradas.
- [x] Una cuenta eliminada no aparece en ninguna lista. *Por construcción, en el mismo sitio que lo comprueba el perfil; sin prueba porque no hay borrado de cuenta (`FEAT-USR-013` está `BLOCKED`).*
- [x] Dejar de seguir retira a esa persona de las dos listas.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| CM-14 | ¿La lista de seguidores es pública? | **Resuelta aquí:** se ve tanto como el perfil que la tiene, y cada fila tanto como la persona que la ocupa |
| CM-15 | ¿«Mis Amigos» es un buen nombre para una relación asimétrica? | Sigue abierta, y es de interfaz. El backend no la usa |
| C-25 | ¿Debería poder ocultarse la lista de seguidos sin cerrar el perfil entero? | Sería un ajuste más en `FEAT-USR-038`. Hoy no existe y nadie lo ha pedido |
| C-26 | ¿Las tarjetas deberían decir si yo sigo a cada una? | Lo pide la interfaz para pintar el botón. Hoy se resuelve con `getAuthorSubscription` por fila, que es una petición por fila |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Resuelve `CM-14`, que `FEAT-COM-010` dejó
explícitamente aplazada.

**Implementación:** `DONE` (2026-09-24). `GET /api/v1/users/{userId}/subscriptions` y
`/subscribers`, sobre la tabla que ya existía y por sus dos índices. **Sin migración.**

### El contrato está preguntado al revés a propósito

`VisibleProfiles` no responde «dame estos perfiles» sino **«dame los que esta persona puede
ver, de estos»**. Es la diferencia entre una regla que se cumple siempre y una que se cumple
mientras nadie la olvide: un contexto que recibiera los perfiles y tuviera que acordarse de
filtrarlos es un contexto que algún día no se acuerda, y lo que se filtraría de más es la
gente que pidió no ser encontrada.

`Community` sabe quién sigue a quién; **no sabe qué hace visible a una persona**, y no tiene
por qué aprenderlo. Es la misma decisión que llevó el techo de privacidad dentro de
`ReaderDirectory` en [`FEAT-RDG-006`](../reading/FEAT-RDG-006-find-beta-readers.md).

Se pregunta **por la página entera de una vez**. Una llamada por fila sería un N+1 escondido
detrás de un contrato, que es peor que un N+1 a la vista porque nadie lo ve al leer el código
que lo provoca.

### Filtrar después de paginar tiene una consecuencia rara, y está probada

Una página puede venir **corta, o completamente vacía, y seguir teniendo siguiente**. Lo que
dice si hay más es `pageInfo.nextCursor`, nunca cuántas filas llegaron. Un cliente que cuente
elementos para decidir si sigue pidiendo se parará antes de tiempo, así que está dicho en la
ficha (`RN-5`), en el OpenAPI y en una prueba que construye exactamente ese caso.

La alternativa —filtrar antes de paginar— exigiría preguntar la visibilidad de todas las filas
para poder recortar, que es justamente lo que no se puede hacer con una lista que crece.

### Dos endpoints, no un parámetro

Seguidos y seguidores son la misma tabla por sus dos extremos, y aun así son dos preguntas
distintas. Un `?direction=` que cambiara el significado de la respuesta acabaría mal
documentado; la lógica compartida —paginar, comprobar el titular, filtrar— vive en un solo
sitio, que es donde tiene que estar.

### Lo que no lleva, y por qué

- **Sin total.** Contar aquí sería contar lo filtrado, una cifra distinta para cada visitante.
  Los contadores del perfil son `FEAT-USR-028`.
- **Sin «¿sigo yo a esta persona?» en cada fila** (`C-26`). La interfaz lo necesita para
  pintar el botón de cada tarjeta y hoy se resuelve con `getAuthorSubscription` por fila, que
  es una petición por fila. Es lo primero que conviene añadir cuando la pantalla exista.
