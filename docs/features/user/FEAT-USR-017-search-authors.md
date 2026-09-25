---
id: FEAT-USR-017
title: Buscar autores por nombre o temática
context: User
concept: Profile
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - _sources/use-cases.pdf#p5
  - docs/ui/post-interactions.md
  - conversation:2026-09-25
endpoints: [GET /authors]
events: []
depends_on: [FEAT-USR-038]
updated: 2026-09-25
---

# FEAT-USR-017 — Buscar autores

## Resumen

Encontrar personas en la plataforma escribiendo parte de su nombre, de su nombre de usuario, o
eligiendo una temática.

## Qué NO es

**No es la búsqueda global** del encabezado, que busca también obras y publicaciones: esa es
`FEAT-COM-025` y está en backlog. Si esta devolviera obras, las dos acabarían siendo la misma
cosa con dos respuestas distintas, y la que sobreviviera se llevaría por delante las reglas de
la otra.

Tampoco es el **buscador del backoffice** (`FEAT-MOD-005`), que busca por correo y devuelve
cuentas en cualquier estado, incluidas las eliminadas. Aquel vive detrás de un contrato
aparte y deja traza de cada consulta, justamente porque hace lo que este no debe hacer.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Buscar personas | Con sesión |

**Exige sesión, y el perfil suelto no.** No es incoherente: abrir el perfil de alguien que te
ha pasado su enlace es una cosa, y poder recorrer el padrón de la plataforma es otra. Un
buscador de personas abierto es una superficie de extracción de datos, y quien la use no deja
rastro de quién es.

## Qué se puede buscar

| Criterio | Sobre qué |
|---|---|
| Texto | Nombre y nombre de usuario, por coincidencia parcial |
| Temática | Los **géneros que esa persona declaró** en sus preferencias literarias (`FEAT-USR-009`) |

### «Temática» son sus preferencias, no los géneros de sus obras

Es la interpretación que este contexto puede sostener: las preferencias literarias son de
`User` y están ahí desde el onboarding.

Buscar por los géneros de lo que alguien **ha escrito** es otra pregunta, la responde `Work`,
y traerla aquí costaría una dependencia entre contextos para una pantalla de búsqueda. Queda
anotado (`U-24`).

En la práctica se parecen más de lo que la distinción sugiere: casi todo el mundo declara los
géneros que lee **y** escribe.

## Reglas de negocio

- `RN-1` Solo aparecen cuentas **activas**. Una eliminada está anonimizada y no queda nada que
  enseñar; una expulsada no vuelve por la puerta del buscador.
- `RN-2` **No se busca nunca por correo.** Responder si una dirección tiene cuenta es
  exactamente lo que el alta y la recuperación de contraseña se cuidan de no decir, y un
  buscador que lo hiciera tiraría por tierra las dos.
- `RN-3` **El ajuste de privacidad manda** (`FEAT-USR-038`). Quien tiene el perfil en `NOBODY`
  no aparece; quien lo tiene en `FOLLOWERS` aparece solo para quien le sigue.
- `RN-4` Un bloqueo **esconde en los dos sentidos**: ni el bloqueado encuentra al bloqueador
  ni al revés (`FEAT-COM-034`).
- `RN-5` Quien busca **se ve a sí mismo**, si encaja con lo que escribió. Esconderle su propia
  ficha sería raro y no protege nada.
- `RN-6` El texto se busca **sin distinguir mayúsculas ni comodines**: un `%` tecleado por
  alguien no puede convertir su búsqueda en «devuélvemelo todo».
- `RN-7` Sin criterios no hay resultados. Un buscador que con la caja vacía devuelve el padrón
  entero **es** el padrón entero.
- `RN-8` Como mucho 20 por página.

`RN-3` y `RN-4` son las que hacen que esto no sea un agujero: sin ellas, el buscador sería la
forma cómoda de saltarse todos los ajustes de privacidad de la plataforma.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Buscar personas | `GET /api/v1/authors` | `searchAuthors` |

**No es `GET /users` con `searchUsers`**, como proponía la tabla de endpoints: ese
`operationId` ya lo ocupa el buscador del backoffice desde `FEAT-MOD-005`, y el nombre de ruta
es el `operationId` (`decision:0010`). `authors` dice además lo que la pantalla dice.

| Parámetro | Qué hace |
|---|---|
| `q` | Texto contra nombre y nombre de usuario |
| `genre` | Código de género. Se puede repetir |
| `limit` | Hasta 20 |

`q` y `genre` se combinan con **y**: quien manda los dos pide las dos cosas.

## Modelo de datos afectado

Ninguno. Se consulta lo que ya existe.

## Criterios de aceptación

- [x] Buscar parte de un nombre encuentra a esa persona.
- [x] Buscar parte de un nombre de usuario también.
- [x] No se encuentra a nadie por su correo.
- [x] Filtrar por género devuelve solo a quien lo declaró.
- [x] Texto y género se combinan.
- [x] Quien tiene el perfil en `NOBODY` no aparece.
- [x] Quien lo tiene en `FOLLOWERS` aparece solo para sus seguidores.
- [x] Un bloqueo esconde en los dos sentidos.
- [x] Una cuenta eliminada o expulsada no aparece.
- [x] Sin criterios no hay resultados.
- [x] Un `%` en el texto no devuelve a todo el mundo.
- [x] Sin sesión, `401`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| U-24 | ¿Debería poder buscarse por los géneros de las **obras** de alguien, y no solo por los que declaró? | Lo responde `Work`. Hoy se resuelve con lo que `User` posee |
| U-25 | ¿Qué orden tienen los resultados? Hoy alfabético por nombre de usuario | Cualquier orden por relevancia o popularidad es el mismo vacío que `CM-4` |
| U-26 | ¿Se busca también entre alias de nombre de usuario vigentes? | Quien cambió de nombre hace una semana no se encuentra por el anterior |

## Estado

**Especificación:** `APPROVED` (2026-09-25).

**Implementación:** `DONE` (2026-09-25).
