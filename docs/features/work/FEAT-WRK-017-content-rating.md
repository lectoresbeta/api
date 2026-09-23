---
id: FEAT-WRK-017
title: Clasificación de contenido sensible de una obra
context: Work
concept: Manuscript
actors: [Writer]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-23 (contenido no apto para menores y filtros)
endpoints:
  - PUT /works/{workId}/content-rating
events: [WorkContentRatingSet]
depends_on: [FEAT-WRK-001]
updated: 2026-09-23
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
- `RN-4` Las etiquetas son un **catálogo cerrado** (`W-20`), no texto libre: si fueran libres
  no se podría filtrar por ellas.
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

**Por definir** (`W-20`). Lo que debe cumplir:

- **Corto.** Cuantas más etiquetas, menos se usan bien.
- **Descriptivo, no valorativo.** «Violencia explícita» dice qué hay; «contenido perturbador»
  no dice nada y depende de quién lea.
- **Con un eje aparte para la edad.** «No apto para menores» no es una temática, es un
  público.

Punto de partida razonable: `SEXUAL_CONTENT`, `GRAPHIC_VIOLENCE`, `SELF_HARM`,
`SUBSTANCE_USE`, `STRONG_LANGUAGE`, más el indicador `ADULTS_ONLY`.

`SELF_HARM` merece atención propia: es la etiqueta que más gente necesita para **evitar** un
texto, y la que peor se lleva con el silencio.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Fijar la clasificación | `PUT /works/{workId}/content-rating` | `setWorkContentRating` |
| Catálogo de etiquetas | `GET /content-warnings` | `listContentWarnings` |

Va en el flujo de publicación, no en un ajuste aparte: **si se puede omitir, se omitirá**.

## Eventos

| Evento | Cuándo | Consumidores |
|---|---|---|
| `WorkContentRatingSet` | Al declararla o cambiarla | `Community` (filtrado del muro y recomendaciones), read models del catálogo |

## Criterios de aceptación

- [ ] El autor declara la clasificación en el flujo de publicación.
- [ ] La clasificación se ve en la tarjeta del catálogo, antes de abrir la obra.
- [ ] Una obra no apta para menores no aparece a quien no cumple la edad.
- [ ] Las etiquetas salen de un catálogo cerrado.
- [ ] Una reclamación por contenido correctamente etiquetado se desestima.
- [ ] Etiquetar mal puede dar lugar a reclamación estimada.
- [ ] Cambiar la clasificación no altera correcciones en curso.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **W-20** | ¿Qué catálogo de etiquetas? | Define el filtro, la advertencia y el criterio del moderador |
| **W-19** | ¿La clasificación es por obra o por capítulo? | Una novela entera cargando la etiqueta de un capítulo |
| OB-7 | ¿Hay edad mínima y fecha de nacimiento fiable? | Sin dato de edad, `ADULTS_ONLY` no se puede aplicar |
| W-21 | ¿Se etiqueta también el **muro** y los comentarios? | Una publicación puede ser igual de fuerte que una obra |
| W-22 | ¿Qué pasa con las obras ya publicadas cuando se añade esta funcionalidad? | Nadie las etiquetó |

`OB-7` es la que más arrastra: sin edad verificable, «no apto para menores» es una etiqueta
informativa y poco más. Conviene ser honestos sobre eso en lugar de fingir un control que no
existe.

## Estado

**Especificación:** `DRAFT`. `W-20` bloquea `APPROVED`.

**Implementación:** `TODO`.
