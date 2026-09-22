---
id: FEAT-CRD-016
title: Coste y recompensa determinados por el cuestionario
context: Credits
concept: Rule
actors: []
spec_status: DRAFT
impl_status: BLOCKED
priority: P0
sources:
  - conversation:2026-09-22 (formulario de corrección)
  - _sources/credit-system.pdf#p4
  - docs/ui/read-chapter.md
endpoints: []
events: [QuestionnaireUpdated, WorkContentUpdated, FeedbackSubmitted]
depends_on: [FEAT-WRK-014, FEAT-CRD-006, FEAT-CRD-012]
updated: 2026-09-22
---

# FEAT-CRD-016 — Coste y recompensa determinados por el cuestionario

## Resumen

**El autor configura el cuestionario, y esa configuración decide dos cifras a la vez:**

- cuánto le **cuesta** recibir una corrección;
- cuánto **gana** el lector que la escribe.

Un cuestionario puede ir desde un único campo de texto libre hasta varias preguntas
concretas. Cuanto más pide, más cuesta y más recompensa.

## Qué cambia respecto al modelo anterior

| | Modelo documentado | Con esta pantalla |
|---|---|---|
| Coste al autor | `créditos(TextTier) + max(0, preguntas − 3)` | Longitud del capítulo **y** confección del cuestionario |
| Recompensa al lector | `créditos(TextTier)` | Los mismos dos factores, con **margen propio** |
| Relación entre ambas | Independientes, aunque coincidían | Salen de los mismos factores, pero **no tienen por qué coincidir** |
| Unidad de cálculo | La obra | **El capítulo** (`R-2`) |
| Quién decide | La tabla de tramos | El autor fija la exigencia; **el sistema ajusta el margen** |

`credit-system.pdf` ya apuntaba en esta dirección al cobrar un crédito por pregunta adicional
sobre las tres de base. Lo que cambia es el alcance: el cuestionario deja de ser un
**recargo** sobre el precio del texto y pasa a ser **el principal determinante** del precio,
en las dos direcciones.

## Por qué tiene sentido

Que coste y recompensa salgan de la misma configuración hace explícito el intercambio: el
autor decide cuánto trabajo pide y paga en proporción; el lector ve de antemano cuánto
trabajo se le pide y cuánto gana por él.

Con cifras completamente independientes, nada impediría un cuestionario largo y mal pagado,
que es exactamente lo que vacía una plataforma de correctores.

El margen entre ambas cifras (`P-3`) no rompe ese acoplamiento: las dos siguen creciendo con
la exigencia del cuestionario y con la longitud del capítulo. Lo que el margen permite es
**regular la masa total de créditos** sin tocar la relación entre esfuerzo y recompensa.

## Lo decidido

### `P-2` — intervienen los dos factores

**Resuelto.** El precio depende de **la longitud del texto y de la confección del
cuestionario**. El `TextTier` sigue interviniendo.

```text
precio = f( longitud del texto , exigencia del cuestionario )
```

Era la decisión crítica: si el precio dependiera solo del cuestionario, corregir una novela
de 50.000 palabras costaría lo mismo que un microcuento con la misma pregunta, y nadie
leería novelas. **El esfuerzo del lector es leer, no solo responder**, y el precio lo
reconoce.

Con la corrección **por capítulo** (`R-2`), la longitud que pesa es **la del capítulo**, no
la de la obra entera. Un capítulo de 2.000 palabras se paga como lo que es, aunque pertenezca
a una novela de 80.000.

### `P-3` — coste y recompensa no tienen por qué coincidir

**Resuelto.** Lo que paga el autor y lo que cobra el lector son **dos cifras distintas**, y el
sistema puede introducir un **margen** entre ambas para mantener sana la economía de
créditos. El ajuste puede ser **dinámico**.

Es la decisión de mayor alcance técnico de las tres, y conviene ver por qué:

| Consecuencia | Qué obliga a hacer |
|---|---|
| El precio **cambia con el tiempo** | Fijarlo al comprometer el crédito deja de ser una comodidad y pasa a ser obligatorio (`RN-2`) |
| Dos cifras por operación | Un movimiento de cargo y otro de abono, nunca una transferencia entre cuentas |
| El margen tiene que ir a algún sitio | Hace falta una **cuenta de sistema** que absorba la diferencia, o los saldos dejan de cuadrar |
| Hay que saber por qué se cobró lo que se cobró | Cada movimiento guarda **la versión de la regla** que lo calculó |
| El ajuste dinámico necesita señal | Sin medir la masa de créditos no hay con qué ajustar |

Ese último punto tiene nombre y ficha: `FEAT-CRD-012`, «monitorizar la salud de la economía
de créditos», hoy en `DEFERRED`. **Un ajuste dinámico sin monitorización es un ajuste a
ciegas**, así que esa ficha deja de ser opcional: pasa a ser el instrumento de medida del que
depende la política de precios.

### `P-1` — la fórmula exacta se define más adelante

**Aplazado deliberadamente.** No es una pregunta sin responder, es una decisión que producto
toma después.

Eso permite avanzar en todo lo demás, pero con una condición de diseño que hay que respetar
desde el primer día: **la regla de cálculo vive tras una abstracción**, no repartida en
condicionales. Sustituir la fórmula —o ajustarla dinámicamente— no puede obligar a rediseñar
el contexto.

Mientras no exista, nada que dependa de una cifra concreta puede implementarse.

## Reglas de negocio

- `RN-1` El cálculo lo hace **`Credits`** a partir de los datos del cuestionario que recibe en
  el evento. Ni `Work` ni `Feedback` proponen importes
  ([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md)).
- `RN-2` Coste y recompensa se fijan **en el momento en que se compromete el crédito**, no al
  enviar la corrección. Con la reserva previa, ese momento es la retención
  ([`decision:0004`](../../decisions/0004-credit-reservation-on-access-grant.md)). Con
  precios dinámicos (`P-3`) esta regla deja de ser una comodidad: sin ella, el lector podría
  escribir una corrección y cobrar una cifra distinta de la que se le prometió.
- `RN-6` Lo que paga el autor y lo que cobra el lector son **dos importes independientes**.
  La diferencia entre ambos la absorbe una **cuenta de sistema**, nunca un descuadre.
- `RN-7` Cada movimiento registra **la versión de la regla de precio** que lo calculó. Sin
  eso, un precio que cambia con el tiempo es imposible de auditar o de explicar a un usuario.
- `RN-8` La unidad sobre la que se calcula es **el capítulo**, no la obra (`R-2`): pesa la
  longitud del capítulo corregido.
- `RN-3` Cambiar el cuestionario **no altera las retenciones ya hechas**. Quien empezó a
  corregir con unas condiciones las conserva.
- `RN-4` El lector debe **ver la recompensa antes de empezar**. Es la cifra que muestra la
  tarjeta de la obra (`FEAT-CRD-013`).
- `RN-5` El autor debe ver el coste **mientras configura el cuestionario**, no después.

`RN-3` y `RN-5` son las que hacen que el sistema sea honesto: nadie descubre el precio cuando
ya no puede echarse atrás.

## Eventos

| Evento | Aporta |
|---|---|
| `QuestionnaireUpdated` | La configuración del cuestionario, para recalcular las cifras |
| `WorkContentUpdated` | La longitud de cada capítulo, que pesa en el precio (`P-2`) |
| `WorkOpenedForCorrection` | El momento de comprometer créditos, si `R-1` se resuelve por obra |
| `FeedbackSubmitted` | Confirma la retención (`FEAT-CRD-006`) |

## Criterios de aceptación

Provisionales hasta que exista fórmula:

- [ ] El importe lo calcula `Credits`, nunca `Work` ni `Feedback`.
- [ ] La recompensa mostrada al lector coincide con la que después se abona.
- [ ] El coste mostrado al autor coincide con el que después se le cobra.
- [ ] Cambiar el cuestionario no altera retenciones ya existentes.
- [ ] Un cuestionario más exigente produce a la vez más coste y más recompensa.
- [ ] La extensión del capítulo interviene en el cálculo, además del cuestionario.
- [ ] Coste y recompensa pueden diferir, y la diferencia queda registrada en una cuenta de
      sistema: la suma de todos los saldos más esa cuenta es constante.
- [ ] Cada movimiento permite reconstruir con qué versión de la regla se calculó.
- [ ] Cambiar la regla de precio no obliga a tocar más de una clase.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **P-1** | La fórmula exacta | **Aplazada por decisión de producto.** Hasta que exista, nada que dependa de una cifra concreta se implementa |
| **P-5** | ¿Con qué frecuencia y con qué criterio se ajusta el precio dinámicamente? | Un precio que cambia a diario y otro que cambia por trimestre no son el mismo sistema |
| **P-6** | ¿Dónde se acumula la diferencia entre lo que paga el autor y lo que cobra el lector? | Sin cuenta de sistema, los saldos no cuadran |
| P-7 | ¿Se comunica al usuario que el precio ha cambiado? | Ver un coste distinto sin explicación erosiona la confianza |
| P-4 | ¿Hay mínimo y máximo? | Evita correcciones gratis o desorbitadas |
| C-12 | ¿Qué relación hay con la tabla de tramos de `credit-system.pdf`? | ¿La sustituye o la complementa? |

Resueltas: `P-2` (intervienen longitud del texto y cuestionario), `P-3` (coste y recompensa
pueden diferir, con ajuste dinámico) y `R-2` (la corrección es **por capítulo**).

`P-5` y `P-6` son consecuencia directa de `P-3` y no existían antes de tomarla. No bloquean
el diseño, pero sí la primera línea de código que mueva créditos.

## Estado

**Especificación:** `DRAFT`. El mecanismo y los factores están decididos; la fórmula está
**aplazada deliberadamente** (`P-1`).

**Implementación:** `BLOCKED` por `P-1`. No es un bloqueo por falta de información sino por
una decisión que producto toma más adelante.

Lo que **sí** se puede construir mientras tanto, y conviene construir ya:

- el registro de movimientos y su auditabilidad (`RN-7`);
- la cuenta de sistema que absorbe el margen (`RN-6`);
- la abstracción tras la que vivirá la regla de precio;
- la fijación del importe en el momento de la retención (`RN-2`).

Lo único que falta cuando llegue `P-1` es **una implementación de esa abstracción**. Si el
diseño obliga a algo más que eso, el diseño está mal.
