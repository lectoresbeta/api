---
id: FEAT-WRK-017
title: Clasificación de contenido sensible de una obra
context: Work
concept: Manuscript
actors: [Writer]
spec_status: APPROVED
impl_status: PARTIAL
priority: P1
sources:
  - conversation:2026-09-23 (contenido no apto para menores y filtros)
endpoints:
  - PUT /works/{workId}/content-rating
  - GET /content-warnings
events: []
depends_on: [FEAT-WRK-001, FEAT-WRK-012]
updated: 2026-09-24
---

# FEAT-WRK-017 — Clasificación de contenido sensible

## Resumen

Al publicar, el autor **declara si su obra contiene material sensible**: no apta para menores,
violencia explícita, contenido sexual, etc.

Esa declaración hace tres cosas a la vez:

1. permite al lector **filtrar** lo que no quiere ver;
2. protege al autor: una reclamación por contenido sensible **correctamente etiquetado se
   desestima**;
3. convierte el etiquetado en un deber, no en una cortesía: **mal etiquetar sí es
   reclamable**.

## El mecanismo de defensa funciona en los dos sentidos

Es lo más elegante de esta decisión y conviene verlo explícito:

| Situación | Qué ocurre con una reclamación |
|---|---|
| Obra con contenido fuerte, **bien etiquetada** | **Se desestima.** El autor avisó y el lector eligió entrar |
| Obra con contenido fuerte, **sin etiquetar** | **Prospera.** El lector no pudo elegir |
| Obra **etiquetada de más** | Nadie se queja. Solo pierde lectores |

Con eso, el sistema deja de castigar el contenido difícil y pasa a castigar **el engaño**, que
es lo correcto en una plataforma literaria: la literatura incómoda tiene derecho a existir; lo
que no lo tiene es aparecer sin avisar.

## Reglas de negocio

- `RN-1` La clasificación se declara **al publicar** y se puede cambiar después.
- `RN-2` Es **por obra** (`W-19`). Si una novela tiene un capítulo más duro que el resto,
  la obra se etiqueta por lo más fuerte que contiene.
- `RN-3` Una obra **no apta para menores** no se muestra a cuentas que no cumplan la edad
  mínima, cuando exista dato de edad (`OB-7`).
- `RN-4` Las etiquetas son un **catálogo cerrado** de cinco valores más el indicador
  `ADULTS_ONLY` (`W-20`), no texto libre: si fueran libres no se podría filtrar por ellas.
- `RN-5` Cambiar la clasificación **no afecta a las correcciones en curso**.
- `RN-6` Una reclamación por contenido sensible sobre una obra **correctamente etiquetada se
  desestima** ([`FEAT-MOD-002`](../moderation/FEAT-MOD-002-review-claim.md) `RN-12`).
- `RN-7` **Etiquetar mal es reclamable**, y puede acabar en sanción.
- `RN-8` La clasificación es **visible antes de abrir la obra**: en la tarjeta del catálogo y
  en la cabecera.

`RN-8` es la que hace útil todo lo demás. Una advertencia que solo aparece cuando ya estás
leyendo no advierte de nada.

`RN-2` es discutible y por eso está `W-19`: etiquetar por obra es simple, pero hace que una
novela entera cargue con la etiqueta de un solo capítulo.

## El catálogo

**Decidido** (`W-20`):

| Etiqueta | Qué declara |
|---|---|
| `SEXUAL_CONTENT` | Contenido sexual explícito |
| `GRAPHIC_VIOLENCE` | Violencia explícita |
| `SELF_HARM` | Autolesión, suicidio, trastornos alimentarios |
| `SUBSTANCE_USE` | Consumo de drogas o alcohol como tema |
| `STRONG_LANGUAGE` | Lenguaje soez sostenido |

Y un **indicador aparte**, que no es una temática sino un público:

| Indicador | Qué declara |
|---|---|
| `ADULTS_ONLY` | No apto para menores |

Tres criterios explican por qué el catálogo es así:

- **Corto.** Cuantas más etiquetas haya, peor se usan. Cinco caben en la cabeza de quien
  publica.
- **Descriptivo, no valorativo.** «Violencia explícita» dice qué hay; «contenido perturbador»
  no dice nada y depende de quién lea.
- **La edad va por su propio eje.** Una obra puede ser `ADULTS_ONLY` por acumulación sin que
  ninguna etiqueta concreta sea determinante, y al revés.

`SELF_HARM` merece atención propia: es la etiqueta que más gente necesita para **evitar** un
texto, y la que peor se lleva con el silencio. Conviene que la advertencia sea explícita y no
un icono ambiguo.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Fijar la clasificación | `PUT /works/{workId}/content-rating` | `setWorkContentRating` |
| Catálogo de etiquetas | `GET /content-warnings` | `listContentWarnings` |

Va en el flujo de publicación, no en un ajuste aparte: **si se puede omitir, se omitirá**.

## Eventos

| Evento | Cuándo | Consumidores |
|---|---|---|
| `WorkContentRatingSet` | Al declararla o cambiarla | `Community` (filtrado del muro y recomendaciones), `Moderation` (para juzgar una reclamación) |

**Especificado y todavía no publicado.** Es la misma decisión que con las temáticas: ninguno
de los dos consumidores existe, y publicar un hecho que nadie escucha es inventarse un
contrato que luego hay que mantener. El catálogo no lo necesita —lee la clasificación de las
tablas de su propio contexto, sin cruzar ninguna frontera—, así que el primer consumidor real
será `Moderation`, y el evento se publica entonces.

## Cómo está implementada

`PUT /works/{workId}/content-rating` sustituye la clasificación entera, y `GET
/content-warnings` publica el catálogo cerrado para que nadie lo copie en el cliente.

### La asimetría del cuerpo no es un descuido

`adultsOnly` es obligatorio; `contentWarnings` puede ir vacío.

Una lista vacía **es una declaración**: «no contiene nada de esto». Omitir el indicador de
público no lo es: significa que nadie lo ha dicho, y dar por supuesto lo más permisivo es
exactamente el error que esta funcionalidad existe para evitar. Si las dos cosas pudieran
faltar, un cuerpo vacío diría «apta para todos» sin que ningún autor lo hubiera declarado —y
es el autor quien responde de esa declaración (`RN-7`).

Por eso `JsonBody::bool()` devuelve `null` y no `false` cuando el campo falta: «nadie lo ha
dicho» no es «no», y quien pregunta decide qué hacer con la diferencia.

### Etiquetar de más no se penaliza

No hay tope, al revés que en las temáticas. Y la razón es que los dos filtros del catálogo
tiran en direcciones contrarias: una obra con ocho temáticas aparecería en casi cualquier
búsqueda —de ahí el tope de tres—, mientras que las etiquetas de contenido **quitan** obras.
Declararlas todas solo le cuesta lectores a su autor. Se castiga solo.

### El filtro del catálogo excluye, y por eso se valida

`excludeContentWarnings[]` quita del catálogo cualquier obra que lleve una de las etiquetas
dadas. El nombre no es el que fijaba `FEAT-WRK-012` —`contentWarnings[]`—, y se ha cambiado a
propósito: un parámetro que quita y se llama como si buscara es una trampa para quien integre.

Lo excluido **no llega al cliente** y no cuenta en el total: ocultar en la interfaz lo que el
servidor ya ha enviado no es filtrar.

Una etiqueta que no existe devuelve `422`, al revés que una temática desconocida, que
simplemente no encuentra nada. Allí no hay forma de distinguir un código inventado de uno
retirado del catálogo; aquí la lista es cerrada, así que es una errata. Y el error va en la
dirección peligrosa: darla por buena enseñaría justo lo que el lector ha pedido no ver.

### `RN-5` no se comprueba, se construye

Cambiar la clasificación no toca las correcciones en curso porque aquí no se toca nada más que
la obra. Lo que cambia es quién la encuentra a partir de ahora.

## Criterios de aceptación

- [x] El autor declara la clasificación en el flujo de publicación.
- [x] La clasificación se ve en la tarjeta del catálogo, antes de abrir la obra.
- [x] Una obra no apta para menores no aparece a quien no cumple la edad.
- [x] Las etiquetas salen de un catálogo cerrado.
- [ ] Una reclamación por contenido correctamente etiquetado se desestima.
- [ ] Etiquetar mal puede dar lugar a reclamación estimada.
- [x] Cambiar la clasificación no altera correcciones en curso.

Los dos que faltan son los dos lados del mecanismo de defensa, y ninguno es de este contexto:
los juzga `Moderation` ([`FEAT-MOD-002`](../moderation/FEAT-MOD-002-review-claim.md) `RN-12`),
que no existe todavía. Lo que esta ficha deja hecho es lo que los hace posibles: una
declaración explícita, completa, guardada y visible antes de abrir la obra.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| W-19 | ¿La clasificación es por obra o por capítulo? | Hoy **por obra** (`RN-2`); por capítulo sería más preciso y más trabajo para el autor |
| OB-7 | ¿Hay edad mínima y fecha de nacimiento fiable? | Sin dato de edad, `ADULTS_ONLY` no se puede aplicar |
| W-21 | ¿Se etiqueta también el **muro** y los comentarios? | Una publicación puede ser igual de fuerte que una obra |
| ~~W-22~~ | ¿Qué pasa con las obras ya publicadas cuando se añade esta funcionalidad? | **Resuelta:** se quedan sin etiquetas y no apta para menores a `false`, que es lo que ya decía su fila. No hay migración de datos: una obra sin etiquetar aparece en todas las búsquedas y su autor la clasifica cuando quiera |

`OB-7` es la que más arrastra: sin edad verificable, «no apto para menores» es una etiqueta
informativa y poco más. Conviene ser honestos sobre eso en lugar de fingir un control que no
existe.

## Estado

**Especificación:** `APPROVED` (2026-09-24).

**Implementación:** `PARTIAL` (2026-09-24). La declaración, el catálogo de etiquetas, el
filtro del catálogo y la visibilidad antes de abrir la obra están hechos. Falta la mitad que
vive en `Moderation`: que una reclamación sobre contenido bien etiquetado se desestime y que
etiquetar mal sea reclamable. También `WorkContentRatingSet`, que se publicará cuando exista
su primer consumidor.

`OB-7` sigue siendo la más honesta de las preguntas abiertas: sin edad verificable,
`ADULTS_ONLY` retira la obra de quien no ha declarado fecha de nacimiento, y poco más. El
mecanismo está, el control de edad real no.
