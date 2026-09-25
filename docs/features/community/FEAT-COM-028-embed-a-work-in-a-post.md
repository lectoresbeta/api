---
id: FEAT-COM-028
title: Incluir un relato de la plataforma en una publicación
context: Community
concept: Post
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/ui/create-post.md
  - docs/features/community/FEAT-COM-002-create-post.md
  - conversation:2026-09-25
endpoints:
  - listPosts
  - createPost
events: []
depends_on: [FEAT-COM-002, FEAT-COM-001, FEAT-WRK-013, FEAT-WRK-017]
updated: 2026-09-25
---

# FEAT-COM-028 — Incluir un relato de la plataforma en una publicación

## Resumen

Una publicación que cita una obra de la plataforma se sirve **con la obra dentro**: título,
sinopsis, temáticas, capítulos, tiempo de lectura y lo que declara contener.

El mecanismo de citarla ya existía desde `FEAT-COM-002` —`Post::workId`—, pero lo único que
viajaba era el identificador. Lo que faltaba era la tarjeta, y con ella la propiedad que hace
que valga la pena: **es viva**.

## La diferencia con un enlace externo es toda la ficha

| | Enlace externo (`linkUrl`) | Relato de la plataforma (`work`) |
|---|---|---|
| Qué se guarda | La URL | El `WorkId` |
| Qué se muestra | Lo que hubiera cuando se guardó | Los datos de ahora mismo |
| Si el destino cambia | La previsualización envejece | La tarjeta se actualiza sola |
| Si el destino desaparece | Enlace roto | La tarjeta desaparece, el texto se queda |

## Reglas de negocio

- `RN-1` Una publicación con `workId` se sirve con un objeto `work` resuelto. Un identificador
  suelto obligaría al cliente a una petición por tarjeta para poder pintar el muro.
- `RN-2` La tarjeta lleva **metadatos y ninguna palabra del texto**: título, sinopsis, estado,
  número de capítulos, minutos de lectura, clasificación de edad, advertencias de contenido y
  temáticas. Quién puede leer la obra es otra decisión, y se toma en `GET /works/{workId}`.
- `RN-3` **Es viva.** Se resuelve en cada lectura contra `Work`, así que renombrar una obra
  cambia lo que enseñan todas las publicaciones que la citan, sin tocar ninguna.
- `RN-4` **Si la obra deja de ser visible para cualquiera** —borrada, archivada o bloqueada por
  moderación— `work` viene a `null` y **la publicación se queda entera**, con su texto y su
  `workId`. La asimetría es deliberada: el texto es de quien lo escribió, la obra es de quien
  la escribió, y ninguno decide sobre lo del otro.
- `RN-5` Un **borrador no se anuncia**, ni siquiera desde la publicación de su propio autor:
  quien lo lea no puede abrirlo, porque para él la obra no existe. La publicación se crea
  igual; lo que no sale es la tarjeta.
- `RN-6` La tarjeta **no lleva la modalidad de acceso**. Cómo tiene el autor cerrada su obra no
  es algo que deba leer todo el que pase por el muro.
- `RN-7` Las obras de una página se resuelven **de una vez**, no una por tarjeta.

## Cómo cruza el límite entre contextos

`Community` no consulta las tablas de `Work`. Pregunta por un **contrato publicado**,
`WorkCards`, que vive en `src/Work/Manuscript/Application/Contract/`
([`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md)):

```text
Community/Post/.../ListPostsHandler
        │  ofWorks([...])
        ▼
Work/Manuscript/Application/Contract/WorkCards
        │
        ▼
Work/Manuscript/Application/Service/ResolveWorkCards
```

Tres decisiones dentro de eso:

**Síncrono y no una proyección.** Una tabla de obras en `Community` alimentada por la cola
sería más barata de leer, pero una obra que un moderador acaba de bloquear seguiría
anunciándose con su portada y su título durante lo que tardara el mensaje. La frescura aquí no
es comodidad: es lo que hace que `RN-4` sea cierto en el acto.

**Contrato nuevo y no `WorkAccessBriefs`.** Aquel responde a quien decide si alguien entra, y
por eso lleva `accessMode`. Reutilizarlo habría metido esa respuesta en una tarjeta pública.

**El filtro de visibilidad vive en `Work`.** Lo que no es visible para cualquiera sale ausente
de la respuesta; quien pregunta no recibe la obra y una condición que decidir. Es la misma
regla que hace que `GET /works/{id}` responda `404` a un extraño, y reescribirla fuera de
`Work` sería tener la regla más peligrosa del backend escrita dos veces.

## Contrato de API

Ninguna operación nueva. `PostCard` gana un campo `work`, y `workId` se queda: dice qué obra
citó quien publicó, aunque hoy ya no se pueda enseñar.

## Eventos

Ninguno. Citar una obra no es un hecho nuevo: la publicación ya publica `PostPublished`.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Ninguno. `Post::workId` ya existía y no se guarda nada más: guardar una copia de la obra sería
exactamente la previsualización envejecida que esta ficha evita.

## Criterios de aceptación

- [x] Una publicación con obra se sirve con la tarjeta resuelta dentro.
- [x] Renombrar la obra cambia lo que enseña una publicación anterior.
- [x] Archivar la obra quita la tarjeta y deja la publicación entera.
- [x] Bloquear la obra por moderación deja de anunciarla en el acto.
- [x] Un borrador no se anuncia.
- [x] La tarjeta lleva las advertencias de contenido y la clasificación de edad.
- [x] Una publicación sin obra no trae tarjeta.
- [x] Un muro con varias obras las resuelve todas.

## Preguntas abiertas

Ninguna. La que dejaba abierta `docs/ui/create-post.md` —qué mostrar cuando la obra
desaparece— la resuelve `RN-4`.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
