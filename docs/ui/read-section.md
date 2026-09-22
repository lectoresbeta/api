---
screen: Sección «Leer» — catálogo de obras
figma: (capturas aportadas en conversación, 2026-09-22)
features: [FEAT-WRK-012, FEAT-COM-017, FEAT-CRD-013]
actors: [User]
updated: 2026-09-22
---

# Sección «Leer» — catálogo de obras

Pantalla de descubrimiento: **«¿Qué te apetece leer hoy?»**. Es la puerta de entrada al ciclo
del producto, porque de aquí sale lo que el usuario leerá y corregirá.

Una versión reducida de este mismo bloque aparece también en la [Home](home.md), bajo el
carrusel de recomendaciones.

## Estructura

```text
¿Qué te apetece leer hoy?
Descubre nuevas lecturas filtrando según tus intereses

[ 2 Filtros ] [ Temática ⌄ ] [ Tiempo de lectura ⌄ ] [ Estado ⌄ ]   Restablecer filtros
( Ficción ✕ ) ( YoungAdult ✕ ) …

948 historias                                    Organizar según: relevancia ⌄

┌── tarjeta ──┐  ┌── tarjeta ──┐
└─────────────┘  └─────────────┘
        ‹ 1 2 3 4 5 ›
```

## Filtros

| Filtro | Comportamiento |
|---|---|
| **Temática** | Desplegable con **casillas: multiselección** |
| **Tiempo de lectura** | Desplegable. Rangos sin definir (`L-1`) |
| **Estado** | Desplegable. Se corresponde con `WorkStatus` (`FEAT-WRK-016`) |

Sobre la barra:

- El botón **«Filtros»** lleva un contador con cuántos hay activos.
- Los filtros aplicados se muestran como **chips con «✕»** bajo la barra.
- **«Restablecer filtros»** los quita todos de golpe.
- El recuento —**«948 historias»**— se recalcula con cada cambio.

> En la Home, la temática se elige con chips en línea en lugar de con un desplegable, y el
> subtítulo dice «filtrando por temática» en vez de «según tus intereses». Son dos variantes
> del mismo filtro.

### «Estado» como filtro público

Que el estado sea filtrable confirma que `VISIBLE` e `IN_CORRECTION` son distinciones
**visibles para el lector**, no solo de gestión del autor.

Tiene sentido: a quien quiere ganar créditos le interesa filtrar por obras **en corrección**,
que son las únicas que puede corregir. `DRAFT` no puede aparecer nunca entre las opciones
(`FEAT-WRK-016` `RN-4`).

## Ordenación

«Organizar según: **relevancia**». Es el cuarto sitio donde aparece una relevancia sin
fórmula, junto al muro, los comentarios y los rankings. Ver `CM-4`.

El resto de opciones no se ve desplegado (`L-2`).

## Tarjeta de obra

| Dato | Ejemplo |
|---|---|
| Portada | Imagen |
| Título | «El secreto de Teresa» |
| Métricas | **📋 1 / 1** · **♡ 0** · **👁 2** |
| Sinopsis | Truncada |
| Géneros | `YoungAdult` `Ficción` |
| Tiempo de lectura | «8 min lectura» |
| Créditos | «6 Créditos» o **«0 créditos»** |

La tarjeta destacada de la Home añade el **autor** con su avatar («PEDRO MARTÍNEZ») y etiqueta
el contador como **«1/3 Partes»**.

> «Partes» es la palabra que el producto usa para lo que el modelo llama `Chapter`. Conviene
> anotarlo en el glosario, aunque el identificador siga siendo `Chapter`.

### Hay obras que valen 0 créditos

Varias tarjetas muestran **«0 créditos»** en gris, frente a las «6 Créditos» u «8 Créditos»
en verde.

La explicación que encaja con todo lo demás: **son obras que no están en corrección**. Se
pueden leer, pero no corregir, así que no hay nada que ganar ni que gastar.

Si es así, la insignia no es un precio fijo de la obra sino **una consecuencia de su estado**,
y el diseño distingue ambos casos con el color. Confirma de paso la lectura de `FEAT-CRD-013`:
la cifra es **lo que gana el lector**, no lo que paga el autor. Ver `L-3`.

## Paginación

Numerada —`‹ 1 2 3 4 5 ›`— y no infinita.

Eso contradice la convención de
[`pagination.md`](../api/conventions/pagination.md), que fija **cursor** por defecto y
reserva las páginas numeradas para los rankings. Aquí hacen falta páginas porque se muestra
el total y el usuario navega saltando.

Es un catálogo filtrado, no un flujo cronológico, así que la paginación por página es
razonable. Hay que añadir la excepción a la convención (`L-4`).

## Hay pie de página

La pantalla lleva **footer**: «© Lectores beta», «Política de privacidad», «Política de
cookies», «Aviso legal» e iconos de redes sociales.

Contradice la nota de diseño que decía **«SIN FOOTER»** y que está recogida en
[app-layout-and-navigation.md](app-layout-and-navigation.md). Ver `L-5`.

Los tres enlaces legales importan: hasta ahora solo constaba «Terms & Conditions» en el menú
lateral, y aquí aparecen **tres documentos distintos**, uno de ellos de cookies. Afecta a
`FEAT-USR-024`.

## Requisitos de backend derivados

| # | Requisito | Funcionalidad |
|---|---|---|
| 1 | Catálogo paginado de obras | `FEAT-WRK-012` |
| 2 | Filtro por temática, multiselección | `FEAT-WRK-012` |
| 3 | Filtro por tiempo de lectura | `FEAT-WRK-012`, `L-1` |
| 4 | Filtro por estado de la obra | `FEAT-WRK-012`, `FEAT-WRK-016` |
| 5 | Recuento total de resultados con los filtros aplicados | `FEAT-WRK-012` |
| 6 | Ordenación, incluida «relevancia» | `FEAT-WRK-012`, `CM-4` |
| 7 | Paginación **numerada**, con total de páginas | `FEAT-WRK-012`, `L-4` |
| 8 | Créditos por obra, y su ausencia cuando no está en corrección | `FEAT-CRD-013` |
| 9 | Documentos legales: privacidad, cookies y aviso legal | `FEAT-USR-024` |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| L-1 | ¿Qué rangos tiene «Tiempo de lectura»? | Define el filtro |
| L-2 | ¿Qué otras opciones tiene «Organizar según»? | Solo se ve «relevancia» |
| **L-3** | ¿«0 créditos» significa «no está en corrección»? | Confirma que la insignia es la recompensa del lector |
| L-4 | ¿Se acepta paginación numerada aquí, frente al cursor por defecto? | Excepción a la convención |
| L-5 | ¿Hay footer o no? | La nota de diseño decía «SIN FOOTER» |
| L-6 | ¿El catálogo excluye las obras propias del usuario? | Corregirse a uno mismo no tiene sentido |
| L-7 | ¿Se pueden combinar varios géneros y cómo? ¿`Y` o `O`? | Los chips activos muestran varios a la vez |
| CM-4 | ¿Cómo se calcula «relevancia»? | Cuarto sitio que la necesita |

## Anomalías

| # | Qué pasa | Resolución |
|---|---|---|
| A-1 | Los chips de filtro activos repiten «YoungAdult» cinco veces | Datos de maqueta |
| A-2 | El desplegable de temática muestra «1st menu item», «2nd menu item»… | Maqueta sin rellenar |
| A-3 | Todas las tarjetas comparten la misma sinopsis | Texto de maqueta |
| A-4 | Sigue apareciendo el género «Poeta» | Se corrigió a «Poesía» (`OB-5`) |
