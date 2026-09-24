---
id: FEAT-CRD-013
title: Créditos asociados a una obra
context: Credits
concept: Rule
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - figma:1800-14717 (tarjeta de obra del carrusel)
  - _sources/credit-system.pdf#p2
  - docs/ui/home.md
endpoints:
  - GET /works
events: [ChapterPriceChanged]
depends_on: [FEAT-WRK-013, FEAT-CRD-016, FEAT-WRK-012]
updated: 2026-09-24
---

# FEAT-CRD-013 — Créditos asociados a una obra

## Resumen

Cada tarjeta de obra del carrusel de la Home muestra una insignia con una cifra de créditos:
«6 Créditos», «8 Créditos».

Las dos preguntas que la tenían parada —qué significa la cifra y por qué dos obras parecidas
enseñan números distintos— las resolvió la fórmula de
[`decision:0006`](../../decisions/0006-credit-system.md), y el análisis que sigue se conserva
porque explica **por qué** la respuesta es la que es.

## El problema que tenía: no se sabía qué significaba la cifra

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

## Las cifras del diseño ahora cuadran

Las tarjetas mostraban **6** y **8** créditos para dos obras que caían en el mismo tramo de la
tabla antigua. Con tramos eso era imposible: el mismo tramo solo puede dar un valor.

Con la fórmula de [`decision:0006`](../../decisions/0006-credit-system.md) se explican solas:

| Obra | Tiempo | Palabras aprox. | Leer | Cuestionario | Total |
|---|---|---|---|---|---|
| El secreto de Teresa | 8 min | ~2.000 | 2 | 4 (≈400 palabras exigidas) | **6** |
| Slush, daiquiris, pizza | 10 min | ~2.500 | 3 | 5 (≈500 palabras exigidas) | **8** |

Dos obras de extensión parecida pueden valer distinto **porque sus cuestionarios piden cosas
distintas**. Era justo lo que el diseño mostraba y el modelo antiguo no podía producir.

No es una confirmación definitiva —las palabras son estimadas a partir del tiempo de
lectura—, pero el orden de magnitud encaja y la forma de la discrepancia también. Es un
indicio razonable de que la fórmula se parece a lo que el diseño tenía en la cabeza.

## Qué significa la insignia

**Lo que gana el lector.** Y como coste y recompensa son la misma cifra, es también lo que
paga el autor: la ambigüedad que arrastraba esta ficha (`C-12`) desaparece por construcción.

El «0 créditos» en gris del catálogo significa que **ese capítulo no se puede corregir ahora
mismo**, sea porque la obra no está en corrección o porque el autor no tiene saldo disponible
que cubra el precio.

## Reglas de negocio

- `RN-1` La cifra la calcula **`Credits`**, no `Work`. `Work` aporta las **palabras** y el
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

## Cómo está implementada

La opción **A**, proyección por eventos. La insignia se pinta en pantallas que listan decenas
de obras: una consulta síncrona por tarjeta las haría lentas para enseñar un número que casi
nunca cambia.

```text
Credits/Pricing ──ChapterPriceChanged──▶ RabbitMQ ──▶ Work/Catalogue
                                                        catalogue_chapter_signal.credits
                                                                    │
                                                                    ▼
                                                        GET /works → credits
```

`ChapterPriceChanged` lleva `chapterId`, `workId`, `credits` y `changedAt`, y es **el único
evento de `Credits` con un importe**. No contradice a
[`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md), que dice lo
contrario de lo que parece: lo prohibido es que otro contexto **calcule** efectos de crédito,
no que `Credits` publique lo que ha decidido. `RN-1` pide justamente eso.

Se publica **solo cuando el precio cambia**, no en cada recálculo: repreciar ocurre con cada
palabra que se escribe y el número casi siempre sale igual.

### Qué enseña exactamente la tarjeta

El **mínimo** de los capítulos corregibles de la obra, no la media ni el del primero: es lo
que quien entre ahora puede ganar con seguridad. Una tarjeta que promete más de lo que después
se abona rompe `RN-2`, y con el mínimo eso es imposible.

Un capítulo del que todavía no ha llegado precio queda **fuera** del mínimo en vez de
hundirlo a cero; una obra donde no hay ninguno enseña el cero.

### Lo que decide el cero

Tres cosas distintas lo producen, y para quien mira son la misma: la obra no está en
corrección, ninguno de sus capítulos es corregible, o todavía no ha llegado ningún precio.
Las tres significan lo mismo en la pantalla —ahora mismo no se puede corregir— y por eso no
se distinguen.

El estado de la obra lo pone `Work` en la misma consulta, no la señal: `Credits` responde si
el autor puede pagar el capítulo, y que la obra esté abierta a corrección es dato propio
(`FEAT-WRK-012`).

### Por qué la señal guarda dos fechas

`ChapterPriceChanged` y `ChapterCorrectabilityChanged` llegan por separado y la cola no
promete orden. Con una sola fecha, una corregibilidad retrasada descartaría un precio recién
publicado, o al revés. Cada hecho descarta lo viejo por su cuenta.

Cualquiera de los dos puede crear la fila: esperar al otro dejaría la señal sin escribir.

### El carrusel de la Home

`Community` (`FEAT-COM-017`) todavía no existe. Cuando exista, se suscribe **al mismo
evento** y proyecta su propia tabla: no hereda la de `Work` ni consulta a `Credits`.

## Criterios de aceptación

- [x] La cifra de la insignia procede de las reglas de `Credits`, no de un cálculo en `Community` ni en `Work`.
- [x] La cifra mostrada coincide con el movimiento que después se registra por esa acción.
- [x] Dos obras del mismo nivel muestran la misma cifra, salvo que se adopte el cálculo continuo.
- [x] `Community` no depende de clases de `Credits` para pintar la tarjeta.
- [x] Si la cifra no está disponible, la tarjeta se pinta sin insignia en lugar de fallar.

El segundo se comprueba de la única forma que vale: el test funcional lee la insignia del
catálogo, entrega una corrección de esa obra y compara la cifra con lo que el saldo del lector
ha subido. Tienen que ser el mismo número.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~H-1~~ | ¿La insignia es lo que gana el lector o lo que cuesta al autor? | **Resuelta:** lo que gana quien corrige. Y como una corrección es una transferencia, es también lo que paga el autor: son el mismo número por construcción |
| ~~H-1b~~ | ¿Por qué dos obras del mismo tramo muestran 6 y 8? | **Resuelta:** no hay tramos. La fórmula de [`decision:0006`](../../decisions/0006-credit-system.md) es continua y dos obras de extensión parecida valen distinto porque sus cuestionarios piden cosas distintas |
| M-1 | Si se paga al poner la obra en corrección, ¿qué representa entonces esta insignia? | `FEAT-CRD-014`. Seguiría siendo lo que gana quien corrige; lo que cambiaría es cuándo se descuenta |
| C-10 | ¿El nivel se calcula sobre la obra completa o sobre el fragmento? | **Resuelta de hecho:** el precio es por capítulo (`FEAT-CRD-016`), y la tarjeta enseña el mínimo de los corregibles |

## Estado

**Especificación:** `APPROVED` (2026-09-24).

**Implementación:** `DONE` (2026-09-24). La insignia se sirve en `GET /works` como `credits`.
Lo que queda fuera es el carrusel de la Home, que es `FEAT-COM-017` y no existe todavía: la
tarjeta del catálogo sí la lleva.
