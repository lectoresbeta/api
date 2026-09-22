---
id: FEAT-COM-017
title: Home — carrusel de obras recomendadas
context: Community
concept: Recommendation
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - figma:1800-14717
  - docs/ui/home.md
endpoints: [GET /home/recommended-works]
events: []
depends_on: [FEAT-USR-023, FEAT-WRK-012]
updated: 2026-09-22
---

# FEAT-COM-017 — Home: carrusel de obras recomendadas

## Resumen

Lo primero que ve el usuario al entrar: un carrusel de obras sugeridas a partir de los
géneros que eligió en el onboarding. Es la vía principal para que empiece a leer y, por
tanto, para que gane créditos y entre en el ciclo de reciprocidad del producto.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Ver sus recomendaciones | Sesión iniciada. Funciona con la cuenta sin activar: es solo lectura |

## Datos de cada tarjeta

| Dato | Origen | Nota |
|---|---|---|
| Portada | `Work` | |
| Título | `Work` | |
| `1 / 1` | `Work` | Fragmentos. Si es progreso de lectura, hace falta seguimiento que hoy no existe (`H-2`) |
| Me gusta | Agregado | |
| Vistas | Agregado | **Concepto nuevo** (`H-3`) |
| Sinopsis | `Work` | Truncada en la tarjeta |
| Géneros | `Genre` | Hasta dos visibles |
| Tiempo de lectura | Derivado de `wordCount` | |
| Créditos | `Credits` | `FEAT-CRD-013`, hoy `BLOCKED` |

## Reglas de negocio

- `RN-1` Las recomendaciones se basan en los géneros elegidos en `FEAT-USR-023`.
- `RN-2` Solo se recomiendan obras **visibles y abiertas a lectores beta**. Una obra `HIDDEN`
  o `PRIVATE` no aparece jamás: sería filtrar contenido inédito.
- `RN-3` No se recomiendan obras propias del usuario.
- `RN-4` No se recomiendan obras de autores con la cuenta sin activar (`FEAT-USR-025`), que
  no pueden recibir comentarios.
- `RN-5` El tiempo de lectura se **deriva** del número de palabras con un ritmo de lectura
  fijo y documentado. No lo introduce el autor.
- `RN-6` La tarjeta **nunca incluye contenido de la obra**, solo su sinopsis.
- `RN-7` `Community` no consulta las tablas de `Work` ni de `Credits`: mantiene una
  proyección alimentada por eventos.

`RN-2` y `RN-6` son las dos que protegen el activo del producto. Un fallo aquí expone obra
inédita a quien no tiene acceso concedido.

## Cuando no hay recomendaciones

Mismo problema que el paso 3 del onboarding: al principio no habrá catálogo.

Cadena de relleno, igual que en `FEAT-COM-016`:

1. obras de los géneros elegidos, ordenadas por criterio de relevancia (`H-9`);
2. si no se alcanza el mínimo, obras recientes de cualquier género;
3. si aun así no hay ninguna, **la sección no se muestra** y la Home empieza directamente
   por el muro.

La decisión la toma el servidor, con el mismo patrón `shouldDisplay` que `FEAT-COM-016`: la
regla vive en un solo sitio.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Obras recomendadas | `GET /home/recommended-works` | `listRecommendedWorks` |

Respuesta con `shouldDisplay`, `reason` y la lista de tarjetas.

## Eventos consumidos

| Evento | Origen | Efecto |
|---|---|---|
| `WorkPublished` | `Work` | Añade la obra al catálogo recomendable |
| `WorkContentUpdated` | `Work` | Actualiza palabras, nivel y tiempo de lectura |
| `WorkAccessModeChanged`, `WorkDeleted` | `Work` | Retira o vuelve a admitir la obra |
| `LiteraryPreferencesUpdated` | `User` | Recalcula a qué usuario le encaja |
| `AccountActivated` | `User` | Habilita las obras de ese autor (`RN-4`) |

## Criterios de aceptación

- [ ] Las obras recomendadas pertenecen a los géneros elegidos por el usuario.
- [ ] Nunca se recomienda una obra `HIDDEN` ni una obra `PRIVATE`.
- [ ] Nunca se recomiendan obras propias.
- [ ] La respuesta no incluye el contenido de ninguna obra, solo su sinopsis.
- [ ] Sin obras recomendables, la respuesta es `200` con `shouldDisplay: false`.
- [ ] El tiempo de lectura se deriva del número de palabras, no lo fija el autor.
- [ ] `Community` no consulta tablas de `Work` ni de `Credits`.
- [ ] La Home carga aunque la insignia de créditos no esté disponible.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| H-2 | ¿«1 / 1» es progreso de lectura o solo el total de fragmentos? | Si es progreso, hace falta seguimiento de lectura |
| H-3 | ¿Qué cuenta como «vista» y quién la registra? | Escritura en cada lectura; hay que acotarla |
| H-4 | ¿Cómo funciona «Solicitar otro relato»? ¿Se descarta de forma permanente? | Funcionalidad sin documentar |
| H-9 | ¿Se excluyen obras ya leídas o ya comentadas? | Calidad de la recomendación |
| H-1 | ¿Qué significa la insignia de créditos? | `FEAT-CRD-013` |
| R-1 | ¿Qué ritmo de lectura se usa para el tiempo estimado? | Propuesta: 200 palabras por minuto |

## Estado

**Especificación:** `DRAFT`. Para llegar a `APPROVED` hacen falta el criterio de orden
(`H-9`), qué es una vista (`H-3`) y el significado de la insignia (`H-1`).

**Implementación:** `TODO`.
