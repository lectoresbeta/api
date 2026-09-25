---
id: FEAT-COM-026
title: Mi muro — publicaciones propias
context: Community
concept: Post
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/ui/my-profile.md
  - docs/features/community/FEAT-COM-001-main-wall.md
  - conversation:2026-09-25
endpoints:
  - listMyPosts
  - listUserPosts
events: []
depends_on: [FEAT-COM-001, FEAT-COM-019, FEAT-COM-034, FEAT-USR-014]
updated: 2026-09-25
---

# FEAT-COM-026 — Mi muro: publicaciones propias

## Resumen

La pestaña «Mi muro» del perfil: lo que esa persona ha publicado y reposteado, paginado.

Y su variante ajena, que es la misma pantalla mirando el perfil de otro.

## La decisión de diseño es que no hay consulta nueva

El muro de un perfil es **el muro general con un filtro más**. Mismo repositorio, misma
consulta, mismas reglas; lo único que cambia es de quién se mira.

Eso no es ahorro de código: es lo que hace verdadera la propiedad que esta ficha necesita —
*mirar el perfil de alguien no enseña nada que su muro no enseñara ya*. Con un endpoint
escrito aparte, las tres reglas de visibilidad —audiencia, bloqueo y privacidad de perfil—
habrían tenido dos copias, y a la larga una de las dos se queda atrás. La que se queda atrás
es la que filtra un texto.

## Reglas de negocio

- `RN-1` El muro de una persona lista **sus publicaciones y sus reposts**, de lo más reciente
  a lo más antiguo, con el mismo cursor compuesto que el muro general.
- `RN-2` **Los reposts cuentan como suyos.** Lo que alguien saca a su muro es suyo aunque el
  texto sea de otro, que es justo lo que dice la cabecera del repost. En la consulta de
  reposts el filtro va por **quien repostea**; en la de publicaciones, por quien escribió.
- `RN-3` En el muro propio se ve **todo lo propio**, incluidas las publicaciones `FOLLOWERS`,
  aunque uno no se siga a sí mismo.
- `RN-4` En el muro ajeno se ve **lo que esa persona te dejaría ver en el muro general**: lo
  `EVERYONE` siempre, y lo `FOLLOWERS` solo si la sigues.
- `RN-5` **Con un bloqueo por medio, el muro sale vacío**, en los dos sentidos. El bloqueo es
  unilateral en la intención y bidireccional en el efecto (`FEAT-COM-034`).
- `RN-6` El muro **propio** no lo afecta a quién haya bloqueado uno: bloquear a alguien no
  borra lo que escribí.
- `RN-7` Lo eliminado no está en ningún muro, tampoco en el de su autor.
- `RN-8` La forma de la respuesta es **idéntica** a la del muro general: la misma tarjeta, el
  mismo `pageInfo`. Es lo que permite a la pestaña del perfil reutilizar el componente del muro
  sin una segunda versión.
- `RN-9` **Las dos operaciones exigen sesión**, por lo mismo que el muro: sin saber quién mira
  no se puede resolver qué publicaciones `FOLLOWERS` le alcanzan.
- `RN-10` El muro de quien no existe **está vacío, no da error**. Es una lista, y la de alguien
  que no está tiene cero elementos. Decir «no existe» convertiría esto en un comprobador de
  quién tiene cuenta.
- `RN-11` La privacidad de perfil sigue siendo un **techo** (`FEAT-USR-014`): quien no es
  visible para quien mira no aparece, y su publicación tampoco.

## Contrato de API

| Operación | `operationId` |
|---|---|
| `GET /api/v1/me/posts` | `listMyPosts` |
| `GET /api/v1/users/{userId}/posts` | `listUserPosts` |

Las dos las sirve el mismo controlador: sin `userId` en la ruta, el muro es el de quien mira.
Dos controladores habrían sido dos sitios donde olvidar la sesión.

La respuesta es la de `listPosts`, sin un solo campo distinto.

## Eventos

Ninguno. Es una lectura.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Ninguno. Las dos consultas del muro ganan un filtro opcional por autor; no hay tabla nueva ni
índice nuevo, porque `idx_post_author (author_id, created_at)` ya existía desde `FEAT-COM-002`.

## Criterios de aceptación

- [x] Mi muro trae lo mío y nada de nadie más, en orden.
- [x] Lo que reposteo aparece en mi muro, con mi cabecera.
- [x] Mis publicaciones `FOLLOWERS` están en mi propio muro.
- [x] En el muro ajeno, una `FOLLOWERS` aparece solo al seguir a esa persona.
- [x] Un bloqueo vacía el muro en los dos sentidos.
- [x] Bloquear a alguien no vacía el muro propio.
- [x] Lo eliminado no aparece.
- [x] Se pagina igual que el muro general.
- [x] Sin sesión, `401` en los dos.
- [x] El muro de quien no existe está vacío.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
