# 0008 — El catálogo reparte trabajo, no premia popularidad

- **Estado:** Aceptada
- **Fecha:** 2026-09-24
- **Afecta a:** `Work`, `Credits`, `Community`
- **Resuelve:** `CM-4` para el catálogo, y `L-9`

## Contexto

La sección «Leer» ordena por **relevancia**, y hasta ahora no había fórmula. La respuesta
intuitiva —ordenar por popularidad, como cualquier catálogo— es la equivocada en este
producto, y conviene dejar escrito por qué.

**Lectores Beta no existe para que se lean obras: existe para que se corrijan.** Si el
catálogo pone delante lo más popular, las mismas obras acaparan las correcciones y la mayoría
de los autores no recibe ninguna. Un sitio de lectores beta donde la mayoría de los textos
nunca se corrige ha fracasado, por mucho tráfico que tenga.

## Decisión

El catálogo ordena por una puntuación de **tres factores**, y ninguno es la popularidad.

### Primero, un filtro duro

Solo entran capítulos **corregibles ahora**: obra en `IN_CORRECTION`, saldo del autor
suficiente y menos de tres correcciones abiertas. Se excluyen además las obras propias, las de
usuarios bloqueados, las que el lector ya leyó o corrigió y las que sus preferencias de
contenido excluyen.

Mostrar algo que el lector no puede corregir es desperdiciar el sitio más valioso de la
pantalla.

### Después, la puntuación

```text
puntuación = capacidad × desatención × frescura

capacidad   = min(saldo del autor ÷ precio del capítulo, 10)
desatención = 1 ÷ (1 + correcciones recibidas)
frescura    = 1 ÷ (1 + semanas abierta a corrección)^0.5
```

| Factor | Qué favorece | Por qué |
|---|---|---|
| **Capacidad** | Autores con créditos acumulados | Puede pagar varias correcciones: mostrar su obra produce trabajo real, no una única corrección y un muro |
| **Desatención** | Obras con pocas correcciones | Reparte el trabajo y evita que tres textos acaparen la plataforma |
| **Frescura** | Lo abierto recientemente | Evita que el catálogo se convierta en un archivo de obras que nadie quiso corregir |

El tope de 10 en la capacidad impide que un autor con mucho saldo monopolice el catálogo.

## La propiedad que hace que esto funcione solo

**Mostrar una obra consume lo que le dio visibilidad.**

Cuando un capítulo aparece arriba, recibe correcciones. Cada corrección **gasta saldo del
autor** y **sube su contador de correcciones recibidas**: los dos primeros factores bajan a la
vez. La obra desciende sola y deja sitio a otra.

Es un sistema que se reequilibra sin intervención, y es lo contrario de lo que ocurre con la
popularidad, donde aparecer arriba genera más popularidad y el efecto se realimenta.

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| **Popularidad** (lecturas, likes, valoración) | Es lo que todo el mundo espera de un catálogo | Concentra las correcciones en pocas obras y realimenta la concentración | Contradice la razón de ser del producto |
| **Solo desatención** | Reparto perfecto | Pone delante obras cuyo autor solo puede pagar una corrección | La capacidad de pago es información útil que estaba disponible |
| **Cronológico puro** | Trivial y predecible | Un texto que nadie corrige en su primera semana no vuelve a verse nunca | Demasiado duro con quien publica en mal momento |
| **Mezcla 70/30** con destacadas | Concede algo de escaparate | Complica y reintroduce el problema por la puerta de atrás | Se puede añadir después si el reparto puro resulta aburrido |

## Consecuencias

**Positivas**

- El trabajo se reparte solo, sin necesidad de intervenir.
- Ningún lector ve en el catálogo algo que no puede corregir.
- No hay bucle de realimentación: la visibilidad se gasta al usarse.
- No hace falta calcular ni almacenar señales de popularidad para ordenar.

**Negativas**

- **El catálogo no premia la calidad.** Una obra excelente y una mediocre compiten igual
  mientras estén igual de desatendidas. Quien busque «lo mejor» no lo encontrará aquí, sino en
  los rankings, cuando existan.
- Favorecer al autor con saldo **da ventaja a quien más ha corregido**, que es deseable, pero
  también a quien llegó antes. Conviene vigilarlo.
- Introduce una **dependencia de datos entre contextos**: ver abajo.

**Qué coste tendría revertirla**

Bajo. Es una consulta de ordenación sobre un read model; cambiar los pesos o los factores no
toca el modelo de dominio.

## `L-9`: cómo llegan los datos de `Credits` al catálogo

La ordenación necesita **el saldo del autor y el precio del capítulo**, que son de `Credits`.
El catálogo es de `Work`. Eso no puede resolverse con un `JOIN`: sería acceso directo al
modelo de otro contexto acotado
([`decision:0002`](0002-credits-as-isolated-bounded-context.md)).

Se resuelve con un **read model del catálogo**, alimentado por eventos:

```text
Credits ──CreditBalanceChanged──────────────▶ ┐
Credits ──ChapterCorrectabilityChanged──────▶ │
Work    ──WorkOpenedForCorrection───────────▶ ├─▶ catalogue_entry
Work    ──ChapterContentUpdated─────────────▶ │
Feedback──FeedbackSubmitted─────────────────▶ ┘
```

`catalogue_entry` guarda lo justo para filtrar y ordenar —capacidad, correcciones recibidas,
fecha de apertura, etiquetas, géneros— y **nada de contenido**. Se reconstruye entero
reprocesando eventos.

Que el catálogo vaya unos segundos por detrás no importa: el peor caso es enseñar un capítulo
que acaba de dejar de ser corregible, y ahí el lector recibe el mismo mensaje que si hubiera
llegado tarde.

## Cumplimiento

- Ningún `JOIN` entre tablas de `Work` y de `Credits`. Verificable revisando las consultas del
  catálogo.
- `catalogue_entry` no contiene texto de obras.
- Un test comprueba que una obra que recibe una corrección **baja de posición**.
- Un test comprueba que un capítulo no corregible **no aparece**.
