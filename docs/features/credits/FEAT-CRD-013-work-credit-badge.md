---
id: FEAT-CRD-013
title: Créditos asociados a una obra
context: Credits
concept: Rule
actors: [User]
spec_status: DRAFT
impl_status: DEFERRED
priority: P3
sources:
  - figma:1800-14717 (tarjeta de obra del carrusel)
  - _sources/credit-system.pdf#p2
  - docs/ui/home.md
endpoints: []
events: []
depends_on: [FEAT-WRK-013]
updated: 2026-09-22
---

# FEAT-CRD-013 — Créditos asociados a una obra

## Resumen

Cada tarjeta de obra del carrusel de la Home muestra una insignia con una cifra de créditos:
«6 Créditos», «8 Créditos».

> **Diferida.** Las cifras concretas y su significado se fijarán al documentar el sistema de
> créditos en detalle. Esta ficha conserva el análisis para no repetirlo entonces.

La insignia plantea dos problemas, y ninguno es de maquetación.

## Problema 1: no se sabe qué significa la cifra

En una tarjeta dirigida al **lector**, «6 Créditos» admite dos lecturas opuestas:

| Lectura | Significado | A favor |
|---|---|---|
| **Recompensa** | Lo que el lector gana comentando esta obra | El tour dice «Gana créditos comentando obras de otros autores». Es la tarjeta de un lector buscando qué leer |
| **Coste** | Lo que le cuesta al autor recibir un comentario | Es la cifra que `credit-system.pdf` define por nivel de texto |

En el modelo documentado **ambas cantidades salen de la misma tabla**, así que la ambigüedad
pasa desapercibida. Pero son conceptos distintos y en cuanto una cambie —o se adopte el pago
por adelantado de `M-1`— dejarán de coincidir.

**Recomendación: es la recompensa del lector.** Es la única lectura útil en ese contexto: a
quien busca qué leer le importa lo que gana, no lo que paga otro.

## Problema 2: las cifras no coinciden con la tabla

| Obra | Tiempo de lectura | Palabras aprox. | `TextTier` | Tabla | Diseño |
|---|---|---|---|---|---|
| El secreto de Teresa | 8 min | ~1.600–2.000 | `SHORT_STORY` | 15 | **6** |
| Slush, daiquiris, pizza | 10 min | ~2.000–2.500 | `SHORT_STORY` | 15 | **8** |

Dos observaciones:

1. Ninguna cifra coincide con el tramo que les correspondería.
2. Las dos obras caen en el **mismo tramo** y sin embargo muestran cifras distintas, 6 y 8.

Lo segundo es lo relevante: **una tabla por tramos no puede producir dos valores distintos
para el mismo tramo**. O las cifras son inventadas para la maqueta, o el diseño está
asumiendo el **cálculo continuo** que `credit-system.pdf` planteaba como alternativa y que
está registrado como `FEAT-CRD-010`, hoy `DEFERRED`.

La proporción encaja con esa hipótesis: 8 min → 6 créditos y 10 min → 8 créditos crecen de
forma continua con la extensión.

## Reglas de negocio

Provisionales hasta resolver `H-1`:

- `RN-1` La cifra la calcula **`Credits`**, no `Work`. `Work` aporta el `TextTier` y el
  número de palabras; la traducción a créditos es de `Credits` y de nadie más.
- `RN-2` La cifra mostrada y la que se aplica al registrar el movimiento **deben proceder de
  la misma regla**. Una insignia que prometa 6 y luego abone 15 es peor que no mostrar nada.
- `RN-3` La insignia es informativa: no reserva ni compromete créditos.

`RN-1` importa por la arquitectura: es tentador que `Work` calcule la cifra al tener ya el
número de palabras, y sería exactamente la dependencia que
[`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md) prohíbe.

## Cómo llega la cifra a la tarjeta

El carrusel lo sirve `Community` (`FEAT-COM-017`), que no puede consultar a `Credits`.
Opciones:

| Opción | Cómo | Valoración |
|---|---|---|
| **A. Proyección por eventos** | `Credits` publica el valor por obra y `Community` lo proyecta | Respeta las fronteras. Requiere un evento nuevo |
| **B. Contrato de consulta** | `Credits` expone una consulta explícita por lote de obras | Síncrona, pero con contrato explícito y DTO |
| **C. Cálculo en `Community`** | Replicar la tabla | **Prohibido.** Duplica las reglas de crédito fuera de `Credits` |

**Recomendación: A.** El valor cambia poco y una proyección evita una llamada síncrona en
cada carga de la Home.

## Criterios de aceptación

- [ ] La cifra de la insignia procede de las reglas de `Credits`, no de un cálculo en `Community` ni en `Work`.
- [ ] La cifra mostrada coincide con el movimiento que después se registra por esa acción.
- [ ] Dos obras del mismo nivel muestran la misma cifra, salvo que se adopte el cálculo continuo.
- [ ] `Community` no depende de clases de `Credits` para pintar la tarjeta.
- [ ] Si la cifra no está disponible, la tarjeta se pinta sin insignia en lugar de fallar.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **H-1** | **¿La insignia es lo que gana el lector o lo que cuesta al autor?** | **Bloqueante.** Son conceptos distintos aunque hoy coincidan |
| **H-1b** | **¿Por qué dos obras del mismo tramo muestran 6 y 8?** ¿Se ha adoptado el cálculo continuo (`FEAT-CRD-010`)? | Cambiaría el motor de créditos entero |
| M-1 | Si se paga al poner la obra en corrección, ¿qué representa entonces esta insignia? | `FEAT-CRD-014` |
| C-10 | ¿El nivel se calcula sobre la obra completa o sobre el fragmento? | Con novelas por fragmentos la cifra cambia |

## Estado

**Especificación:** `DRAFT`.

**Implementación:** `DEFERRED`. Se retomará con la documentación detallada del sistema de
créditos, que fijará las cifras y su significado. Implementarla antes produciría una cifra
plausible y equivocada, que es el peor resultado posible en una pantalla sobre dinero
interno.
