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
events: [QuestionnaireUpdated, FeedbackSubmitted]
depends_on: [FEAT-WRK-014, FEAT-CRD-006]
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
| Coste al autor | `créditos(TextTier) + max(0, preguntas − 3)` | Lo determina la configuración del cuestionario |
| Recompensa al lector | `créditos(TextTier)` | También la determina el cuestionario |
| Relación entre ambas | Independientes, aunque coincidían | **Salen de la misma configuración** |
| Quién decide | La tabla de tramos | **El autor**, al confeccionar el formulario |

`credit-system.pdf` ya apuntaba en esta dirección al cobrar un crédito por pregunta adicional
sobre las tres de base. Lo que cambia es el alcance: el cuestionario deja de ser un
**recargo** sobre el precio del texto y pasa a ser **el principal determinante** del precio,
en las dos direcciones.

## Por qué tiene sentido

Que coste y recompensa salgan de la misma configuración hace explícito el intercambio: el
autor decide cuánto trabajo pide y paga en proporción; el lector ve de antemano cuánto
trabajo se le pide y cuánto gana por él.

Con cifras independientes, nada impediría un cuestionario largo y mal pagado, que es
exactamente lo que vacía una plataforma de correctores.

## Qué falta por definir

**La fórmula.** Es lo que mantiene esta ficha bloqueada.

Preguntas que hay que responder antes de poder implementar nada:

| # | Pregunta |
|---|---|
| `P-1` | ¿Qué atributos del cuestionario pesan: número de preguntas, longitud exigida, tipo de respuesta? |
| `P-2` | ¿Sigue interviniendo el `TextTier`? Leer 50.000 palabras cuesta al lector aunque la pregunta sea una |
| `P-3` | ¿Coste y recompensa son la misma cifra, o el autor paga más de lo que el lector recibe? |
| `P-4` | ¿Hay mínimo y máximo, para que un cuestionario no salga gratis ni desproporcionado? |

`P-2` es el más importante de los cuatro. Si el precio dependiera **solo** del cuestionario,
una novela de 50.000 palabras con una única pregunta se corregiría por lo mismo que un
microcuento con esa misma pregunta, y nadie leería novelas. **El esfuerzo del lector es leer,
no solo responder.**

Lo razonable es que ambos factores intervengan: la extensión del texto y la exigencia del
cuestionario. Pero eso hay que decidirlo, no deducirlo.

`P-3` tiene consecuencia económica directa: si el autor paga exactamente lo que el lector
recibe, la masa de créditos del sistema se conserva; si paga más, se destruye crédito; si
paga menos, se crea. `credit-system.pdf` exige que el sistema sea **sostenible** y no
inflacionario, así que esta decisión no es de detalle.

## Reglas de negocio

- `RN-1` El cálculo lo hace **`Credits`** a partir de los datos del cuestionario que recibe en
  el evento. Ni `Work` ni `Feedback` proponen importes
  ([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md)).
- `RN-2` Coste y recompensa se fijan **en el momento en que se compromete el crédito**, no al
  enviar la corrección. Con la reserva previa, ese momento es la retención
  ([`decision:0004`](../../decisions/0004-credit-reservation-on-access-grant.md)).
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
| `QuestionnaireUpdated` | La configuración del cuestionario, para recalcular las cifras de esa obra |
| `WorkOpenedForCorrection` | El momento de comprometer créditos, si `R-1` se resuelve por obra |
| `FeedbackSubmitted` | Confirma la retención (`FEAT-CRD-006`) |

## Criterios de aceptación

Provisionales hasta que exista fórmula:

- [ ] El importe lo calcula `Credits`, nunca `Work` ni `Feedback`.
- [ ] La recompensa mostrada al lector coincide con la que después se abona.
- [ ] El coste mostrado al autor coincide con el que después se le cobra.
- [ ] Cambiar el cuestionario no altera retenciones ya existentes.
- [ ] Un cuestionario más exigente produce a la vez más coste y más recompensa.
- [ ] La extensión del texto interviene en el cálculo (pendiente de `P-2`).

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **P-1** | ¿Qué atributos del cuestionario entran en la fórmula? | Sin ellos no hay cálculo |
| **P-2** | ¿Sigue interviniendo el `TextTier`? | Si no, nadie corregiría novelas |
| **P-3** | ¿El autor paga lo mismo que recibe el lector? | Decide si el sistema crea o destruye crédito |
| P-4 | ¿Hay mínimo y máximo? | Evita cuestionarios gratis o desorbitados |
| R-2 | ¿La corrección es por capítulo o por obra? | Cambia sobre qué se calcula |
| C-12 | ¿Qué relación hay con la tabla de tramos de `credit-system.pdf`? | ¿La sustituye o la complementa? |

## Estado

**Especificación:** `DRAFT`. El mecanismo está claro; la fórmula no existe.

**Implementación:** `BLOCKED` por `P-1`, `P-2` y `P-3`. Es el corazón económico del producto:
implementarlo con una fórmula provisional produciría saldos que después habría que corregir
a mano.
