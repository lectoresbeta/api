---
id: FEAT-WRK-012
title: Sección «Leer» — catálogo de obras con filtros y ordenación
context: Work
concept: Manuscript
actors: [User]
spec_status: APPROVED
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-22 (capturas de la sección «Leer»)
  - docs/ui/read-section.md
endpoints:
  - GET /works
depends_on: [FEAT-WRK-016, FEAT-CRD-013]
events: []
updated: 2026-09-24
---

# FEAT-WRK-012 — Sección «Leer»: catálogo de obras

## Resumen

Pantalla de descubrimiento: **«¿Qué te apetece leer hoy?»**. Lista las obras disponibles con
filtros por temática, tiempo de lectura y estado, ordenación y paginación numerada.

Es la puerta de entrada al ciclo del producto: de aquí sale lo que el usuario leerá y, si la
obra está en corrección, lo que corregirá.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Ver el catálogo y filtrarlo | Sesión iniciada |
| `Guest` | Pendiente de `L-8` | ¿Es público el catálogo? |

El catálogo muestra **metadatos**, nunca contenido. Quién puede leer el texto de una obra es
una decisión distinta, que resuelve `FEAT-WRK-004`.

## Reglas de negocio

- `RN-1` El catálogo incluye obras en estado `PUBLISHED` e `IN_CORRECTION`. **Nunca `DRAFT`**
  ([`FEAT-WRK-016`](FEAT-WRK-016-work-status.md)).
- `RN-2` El filtro de estado ofrece solo esos dos valores. `DRAFT` no puede aparecer como
  opción ni por manipulación del parámetro.
- `RN-3` El recuento total refleja **los filtros aplicados**, no el catálogo entero.
- `RN-4` La ordenación por defecto es **relevancia**, definida en
  [`decision:0008`](../../decisions/0008-catalogue-ordering.md): reparte trabajo, **no premia
  popularidad**.
- `RN-5` El filtro de temática es **multiselección**.
- `RN-6` La insignia de créditos de cada obra la resuelve `Credits`, no `Work`
  ([`FEAT-CRD-013`](../credits/FEAT-CRD-013-work-credit-badge.md)). Una obra que no está en
  corrección muestra `0` (`L-3`).
- `RN-7` El catálogo **excluye las obras propias** del usuario (`L-6`).
- `RN-8` Las obras de usuarios bloqueados no aparecen
  ([`FEAT-COM-034`](../community/FEAT-COM-034-block-user.md)).
- `RN-9` El catálogo **respeta las preferencias de contenido sensible** del usuario
  ([`FEAT-USR-043`](../user/FEAT-USR-043-content-preferences.md)): lo excluido **no llega al
  cliente**, no se oculta en la interfaz.
- `RN-10` Hay **filtro explícito por etiquetas de contenido**
  ([`FEAT-WRK-017`](FEAT-WRK-017-content-rating.md)), además del filtrado implícito por
  preferencias.
- `RN-11` Las obras y capítulos **bloqueados por reclamación** nunca aparecen
  ([`FEAT-MOD-003`](../moderation/FEAT-MOD-003-block-work.md)).

`RN-1` y `RN-2` son la misma regla vista desde dentro y desde el borde. La segunda hace falta
porque un filtro de la interfaz no es una autorización: `?status=DRAFT` debe rechazarse en el
backend aunque el desplegable no lo ofrezca.

## La relevancia reparte trabajo

```text
puntuación = capacidad × desatención × frescura

capacidad   = min(saldo del autor ÷ precio del capítulo, 10)
desatención = 1 ÷ (1 + correcciones recibidas)
frescura    = 1 ÷ (1 + semanas abierta a corrección)^0.5
```

Ninguno de los tres factores es la popularidad, y es deliberado: **la plataforma no existe
para que se lean obras, sino para que se corrijan**. Ordenar por popularidad concentraría las
correcciones en pocos textos y dejaría a la mayoría de autores sin ninguna.

La propiedad que hace que se sostenga solo: **aparecer arriba consume lo que te puso arriba**.
Cada corrección recibida gasta saldo del autor y sube su contador, así que los dos primeros
factores bajan a la vez y la obra deja sitio a otra.

El tope de 10 en la capacidad impide que un autor con mucho saldo monopolice el catálogo.

Antes de puntuar hay un **filtro duro**: solo entran capítulos corregibles ahora mismo.
Enseñar algo que el lector no puede corregir desperdicia el sitio más valioso de la pantalla.

## De dónde salen los datos de `Credits`

La ordenación necesita el **saldo del autor** y el **precio del capítulo**, que son de otro
contexto. No se resuelve con un `JOIN` —sería acceso directo al modelo de `Credits`— sino con
un **read model del catálogo** alimentado por eventos (`L-9`, resuelta en
[`decision:0008`](../../decisions/0008-catalogue-ordering.md)).

`catalogue_entry` guarda lo justo para filtrar y ordenar, **nada de contenido**, y se
reconstruye reprocesando eventos.

## Paginación numerada, como excepción

La pantalla usa **páginas numeradas** —`‹ 1 2 3 4 5 ›`— y muestra el total: «948 historias».

Eso se aparta de la [convención de paginación](../../api/conventions/pagination.md), que fija
cursor por defecto. La excepción está justificada:

| | Cursor | Página numerada |
|---|---|---|
| Bueno para | Flujos cronológicos que crecen por arriba | Catálogos estables |
| Permite saltar | No | Sí |
| Total conocido | No necesariamente | Sí |

Un catálogo filtrado no es un flujo: el usuario quiere saber cuántos resultados hay y saltar
a la página 4. Ver `L-4`.

El coste es real y conviene anotarlo: contar el total de un catálogo grande con filtros
combinados es la consulta cara de esta pantalla, y `COUNT(*)` sobre cada cambio de filtro es
lo primero que se degrada. Si el volumen lo exige, un recuento aproximado es preferible a una
pantalla lenta.

## Flujo principal

1. El usuario abre «Leer».
2. El sistema devuelve la primera página del catálogo, ordenada por relevancia, con el total.
3. El usuario aplica filtros; cada cambio recalcula resultados y total, y vuelve a la
   página 1.
4. «Restablecer filtros» los elimina todos.
5. El usuario abre una obra.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Ningún resultado con los filtros | Lista vacía, total `0` | `200` |
| Página fuera de rango | Lista vacía | `200` |
| Filtro con valor desconocido | Se rechaza | `422` |
| `status=DRAFT` | Se rechaza | `422` |
| Catálogo vacío (plataforma nueva) | Estado vacío | `200` |

El último caso no es teórico: al arrancar la plataforma no habrá obras, igual que no habrá
autores que sugerir en el onboarding. Conviene que el estado vacío diga algo útil y no
parezca un error.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Listar el catálogo | `GET /works` | `listWorks` |

Parámetros: `genres[]`, `readingTime`, `status`, `contentWarnings[]`, `sort`, `page`,
`perPage`.

La respuesta incluye `total` y `totalPages` además de los elementos. Cada elemento lleva
portada, título, sinopsis truncada, géneros, tiempo de lectura, métricas y la insignia de
créditos.

## Eventos

No publica ni consume. Es una consulta.

## Modelo de datos afectado

No añade agregados. Necesita **índices pensados para este acceso**: estado, géneros y el
criterio de ordenación.

El filtro por géneros con multiselección sobre un campo multivaluado es el que más
condiciona: en PostgreSQL, un índice `GIN` es la opción natural. La decisión concreta
pertenece a la implementación, pero la necesidad nace aquí.

La insignia de créditos viene de `Credits`. **No se resuelve con un `JOIN`**: sería una
consulta directa a las tablas de otro contexto acotado, que `AGENTS.md` prohíbe
explícitamente. Hace falta un read model o un contrato de consulta (`L-9`).

## Diseño (Figma)

[`../../ui/read-section.md`](../../ui/read-section.md).

## Criterios de aceptación

- [ ] El catálogo nunca devuelve obras en `DRAFT`, ni siquiera forzando el parámetro.
- [ ] Nunca devuelve obras ni capítulos bloqueados por reclamación.
- [ ] El contenido excluido por preferencias **no llega al cliente**.
- [ ] Filtrar por varias temáticas a la vez funciona.
- [ ] El total refleja los filtros aplicados.
- [ ] Cambiar un filtro vuelve a la página 1.
- [ ] La respuesta incluye `total` y `totalPages`.
- [ ] Un valor de filtro desconocido produce `422`, no una lista vacía.
- [ ] Las obras propias del usuario no aparecen.
- [ ] La insignia de créditos no se obtiene consultando tablas de `Credits`.
- [ ] Con el catálogo vacío se devuelve `200` con lista vacía.
- [ ] Una obra que recibe una corrección **baja de posición**.
- [ ] Un capítulo que deja de ser corregible desaparece del catálogo.
- [ ] No existe ningún `JOIN` entre tablas de `Work` y de `Credits`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| L-1 | ¿Qué rangos tiene «Tiempo de lectura»? | Define el filtro |
| L-2 | ¿Qué otras opciones de ordenación hay? | Solo se ve «relevancia» |
| L-6 | ¿Se excluyen las obras propias? | `RN-7` lo asume |
| L-7 | Varios géneros: ¿`Y` u `O`? | Cambia por completo los resultados |
| L-8 | ¿El catálogo es público para `Guest`? | Decide autorización y SEO |
| L-4 | ¿Se acepta la paginación numerada? | Excepción a la convención |

`L-7` parece menor y no lo es: con `Y`, filtrar por «Ficción» y «YoungAdult» devuelve las
obras que son ambas cosas; con `O`, las que son cualquiera de las dos. Los resultados no se
parecen en nada, y la maqueta no lo aclara. Lo habitual en un catálogo de descubrimiento es
`O`.

## Estado

**Especificación:** `APPROVED` (2026-09-24). `CM-4` y `L-9` resueltas en
[`decision:0008`](../../decisions/0008-catalogue-ordering.md). Las preguntas que quedan son
detalles de filtro que no afectan al modelo.

**Implementación:** `TODO`.
