---
id: FEAT-COM-009
title: Filtrar y buscar publicaciones (tipo, texto, usuario, fecha)
context: Community
concept: Post
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/ui/home.md
  - docs/features/community/FEAT-COM-001-main-wall.md
  - conversation:2026-09-25
endpoints:
  - listPosts
  - listMyPosts
  - listUserPosts
events: []
depends_on: [FEAT-COM-001, FEAT-COM-002, FEAT-COM-026]
updated: 2026-09-25
---

# FEAT-COM-009 — Filtrar y buscar publicaciones

## Resumen

Acotar el muro por intención, por texto, por persona y por fechas.

## Por cuál de las dos dimensiones se filtra

`docs/ui/home.md` dejaba la duda abierta: una publicación tiene **intención** (`PostType`) y
**formato** (`PostFormat`), y no decía por cuál filtra esta ficha.

Se filtra **por intención**, y la razón está en para qué sirve cada una. La intención dice
*qué quiere* quien publica —busco lectores, busco writing buddy, me ofrezco—, que es
exactamente lo que alguien busca cuando entra al muro a filtrar. El formato dice si llevaba
una foto, que es cómo se pinta. Nadie entra al muro a buscar publicaciones con imagen.

## Reglas de negocio

- `RN-1` `type` acota por intención. Un valor que no es una de las cuatro se rechaza.
- `RN-2` `q` busca **en el texto de la publicación**, con búsqueda de texto completo en
  español: busca por **raíz**, así que «escribir» encuentra «escribiendo».
- `RN-3` `authorId` acota por quien **escribió**. No es lo mismo que el muro de una persona
  (`FEAT-COM-026`): allí un repost cuenta como de quien lo saca, aquí como de quien lo
  escribió. Son dos preguntas distintas y se parecen lo justo para confundirlas.
- `RN-4` `from` y `to` acotan por fecha, **los dos extremos incluidos**. En una entrada que es
  un repost, la fecha que cuenta es la **del repost**: es cuando esa entrada apareció en el
  muro, y es la misma por la que se ordena. Con la del original, un repost de hoy de algo de
  hace un año desaparecería al acotar «esta semana» estando justo arriba.
- `RN-5` Los filtros **se combinan con Y**: cada uno estrecha lo anterior. Es lo que espera
  cualquiera que haya usado un buscador, y la alternativa haría que añadir un filtro devolviera
  más resultados.
- `RN-6` Los mismos filtros valen en el muro general y en el de una persona. Es la misma
  consulta, así que sale gratis y no puede divergir.
- `RN-7` **Filtrar no abre nada.** Los filtros van dentro de la misma consulta y **después** de
  la visibilidad: una publicación que no te alcanza no aparece ni buscándola por su texto
  exacto. Y la paginación sobrevive: una página de veinte filtrada trae veinte de las que
  coinciden, no las que sobrevivan de las veinte primeras.
- `RN-8` Un filtro mal escrito **se rechaza** con `422`, no se ignora. Quien filtra está
  acotando, y devolverle el muro entero porque escribió mal un día le haría creer lo contrario
  de lo que ve. Lo mismo un rango que acaba antes de empezar.
- `RN-9` Sin filtros, el muro es exactamente el de antes: ninguna condición de más.

## Cómo está implementada la búsqueda

Índice **GIN sobre la expresión** `to_tsvector('spanish', body)`, y la consulta usa esa misma
expresión. Tres decisiones dentro:

**Full-text y no `ILIKE '%texto%'`.** Aquel busca por raíz, tolera acentos mal puestos y usa
índice; un `ILIKE` con comodín por delante no puede usar ninguno y hace un escaneo completo por
búsqueda.

**Índice sobre expresión y no columna generada.** Una columna `tsvector` habría obligado a
mapearla en Doctrine, y con ello a meter un artefacto de persistencia dentro de la entidad de
dominio, que es lo que `AGENTS.md` prohíbe. Un índice sobre expresión es invisible para el
comparador de esquemas y no toca el modelo.

**Una función de DQL y no SQL nativo.** `FULLTEXT_MATCH` deja donde están las reglas de
audiencia, bloqueo y cursor del muro, que son lo más delicado de este backend; reescribir esa
consulta en SQL nativo para añadir una condición habría sido el cambio más arriesgado posible
por el motivo más pequeño.

**El idioma está escrito en los dos sitios y tiene que coincidir.** Si dejan de hacerlo,
PostgreSQL no usa el índice y la búsqueda pasa de instantánea a un escaneo completo **sin dar
ningún error**. Por eso no es una variable de entorno: cambiarlo es una migración que
reconstruye el índice.

`plainto_tsquery` y no `to_tsquery`: lo que llega es lo que alguien escribió en una caja de
búsqueda, y `to_tsquery` reventaría con un apóstrofo o un paréntesis.

## Contrato de API

Cinco parámetros de query, todos opcionales, en las tres operaciones de muro:

| Parámetro | Qué acota |
|---|---|
| `type` | La intención |
| `q` | El texto |
| `authorId` | Quien escribió |
| `from`, `to` | El rango de fechas, extremos incluidos |

## Fuera de alcance

**Ordenar por relevancia** es `FEAT-COM-024`, y sigue bloqueada: nadie ha definido la fórmula
(`H-7`). Aquí se ordena por fecha, como el muro.

## Eventos

Ninguno. Es una lectura.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Ninguna tabla nueva. Un índice: `idx_post_search`.

## Criterios de aceptación

- [x] Filtra por intención.
- [x] Busca por texto, y por raíz: «escribir» encuentra «escribiendo».
- [x] No revienta con lo que la gente escribe de verdad.
- [x] Filtra por quien escribió.
- [x] Filtra por rango de fechas.
- [x] Los filtros se combinan con Y.
- [x] Buscar no alcanza lo que el muro esconde, ni por audiencia ni por bloqueo.
- [x] Los filtros valen también en el muro de una persona.
- [x] Un muro filtrado se pagina igual.
- [x] Un filtro mal escrito se rechaza.
- [x] Sin filtros, el muro no cambia.

## Preguntas abiertas

Ninguna. La que dejaba `docs/ui/home.md` —por cuál de las dos dimensiones se filtra— la
responde la sección de arriba.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
