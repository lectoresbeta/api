---
id: FEAT-CRD-016
title: Precio de una corrección según el esfuerzo
context: Credits
concept: Pricing
actors: []
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - conversation:2026-09-23 (rediseño del sistema de créditos)
  - docs/decisions/0006-credit-system.md
endpoints: []
events: [QuestionnaireUpdated, WorkContentUpdated]
depends_on: [FEAT-WRK-014]
updated: 2026-09-23
---

# FEAT-CRD-016 — Precio de una corrección según el esfuerzo

## Resumen

```text
precio = techo(palabras del capítulo / 1.000) + techo(palabras exigidas / 100)
```

Dos términos: **leer** y **escribir**. Mínimo 2 créditos, máximo 20.

Es a la vez lo que paga el autor y lo que cobra el lector: una corrección **mueve** créditos,
no los crea ni los destruye ([`decision:0006`](../../decisions/0006-credit-system.md)).

## De dónde sale cada término

| Término | Mide | Fuente del dato |
|---|---|---|
| `palabras del capítulo / 1.000` | Leer el texto con atención crítica | `Work`, vía `WorkContentUpdated` |
| `palabras exigidas / 100` | Escribir la crítica | La suma de los mínimos de palabras de las preguntas del cuestionario, vía `QuestionnaireUpdated`. Una pregunta sin mínimo cuenta como **25 palabras** |

**Las «palabras exigidas» son un solo número que captura toda la exigencia del cuestionario.**
No hace falta pesar el número de preguntas, su tipo ni su longitud por separado: el autor, al
configurar el formulario, ya está declarando cuánto trabajo pide
([`FEAT-WRK-014`](../work/FEAT-WRK-014-configure-questionnaire.md)).

Las dos constantes están calibradas para que ninguno domine: leer 1.000 palabras con atención
y escribir 100 de crítica cuestan aproximadamente lo mismo.

## Ejemplos

| Texto | Palabras | Cuestionario | Precio |
|---|---|---|---|
| Microrrelato | 800 | 1 pregunta, 50 palabras | 1 + 1 = **2** |
| Relato breve | 3.000 | 3 × 100 palabras | 3 + 3 = **6** |
| Relato largo | 6.000 | 3 × 100 palabras | 6 + 3 = **9** |
| Capítulo de novela | 4.000 | 5 × 150 palabras | 4 + 8 = **12** |
| Capítulo, cuestionario mínimo | 4.000 | 1 campo libre, 100 | 4 + 1 = **5** |

## Reglas de negocio

- `RN-1` El precio lo calcula **`Credits`**, a partir de datos que recibe en eventos. Ni
  `Work` ni `Feedback` proponen, calculan ni transportan importes
  ([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md)).
- `RN-2` La unidad es **el capítulo**, no la obra: es lo que se corrige.
- `RN-3` **Lo que paga el autor y lo que cobra el lector son la misma cifra.**
- `RN-4` El precio queda **fijado al empezar la corrección**
  ([`FEAT-CRD-009`](FEAT-CRD-009-balance-check-on-correction-start.md)). Cambiar el
  cuestionario o el texto después no altera lo que cobra quien ya está corrigiendo.
- `RN-4b` Una pregunta **sin mínimo declarado cuenta como 25 palabras** (`C-14` y `C-44`,
  resueltas).
- `RN-5` Mínimo 2, máximo 20. El mínimo evita que nada salga gratis; el máximo evita que un
  capítulo desmesurado resulte incorregible por caro.
- `RN-6` El redondeo es **hacia arriba** en los dos términos. Un capítulo de 1.200 palabras
  cuesta 2, no 1.
- `RN-7` Las constantes —1.000, 100 y el suelo de 25— son **configuración en caliente**
  (`C-3`), no literales repartidos por el código. Son las palancas del sistema: si hay que
  desplegar para moverlas, no son palancas.
- `RN-8` Las palabras se cuentan sobre el **texto plano** (`C-13`): sin marcado, sin títulos
  de capítulo y sin notas. El editor muestra el recuento en vivo para que el autor no se lleve
  sorpresas con el precio.
- `RN-9` Si el autor cambia el texto de un capítulo, su precio **se recalcula para las
  correcciones futuras** y se le avisa (`C-15`). Ampliar un capítulo lo encarece, y es
  correcto que así sea. Lo ya empezado conserva su precio anotado (`RN-4`).

`RN-4` es la que hace el sistema honesto en las dos direcciones: el lector ve antes de empezar
lo que va a ganar, y el autor sabe que lo retenido es lo que pagará.

`RN-7` importa más de lo que parece: son la única palanca fina del sistema. Ajustarlas corrige
el equilibrio entre leer y escribir, y **no mueve la masa de créditos**, porque el precio es
una transferencia.

## Por qué una fórmula continua y no tramos

El modelo de partida clasificaba los textos en tramos por extensión. La fórmula continua es a
la vez más simple y más justa:

| | Tramos | Fórmula continua |
|---|---|---|
| Textos en el borde | Un texto de 3.001 palabras salta de tramo y cuesta bastante más | La diferencia es proporcional |
| Mantenimiento | Una tabla que discutir y revisar | Dos constantes |
| Explicación | «Mira en qué tramo cae» | «Una palabra más, un poco más caro» |

## Eventos

**Consume**

| Evento | Aporta |
|---|---|
| `WorkContentUpdated` | Las palabras de cada capítulo |
| `QuestionnaireUpdated` | Las palabras exigidas por el cuestionario |

`Credits` mantiene con ellos un **read model propio** con el precio vigente de cada capítulo.
No consulta las tablas de `Work`: eso sería acceso directo al modelo de otro contexto.

Ese read model es además lo que permite a `Work` mostrar la insignia de la tarjeta sin
acoplarse ([`FEAT-CRD-013`](FEAT-CRD-013-work-credit-badge.md)).

**Publica**

Ninguno. Calcular no es un hecho de negocio.

## Modelo de datos afectado

`chapter_price`: `chapter_id`, `work_id`, `word_count`, `required_words`, `price`,
`updated_at`. Un registro por capítulo, actualizado por los dos eventos.

Es un read model: se puede reconstruir entero reprocesando los eventos, y no es la fuente de
verdad de nada.

## Criterios de aceptación

- [ ] Un capítulo más largo cuesta más que uno corto con el mismo cuestionario.
- [ ] Un cuestionario más exigente cuesta más sobre el mismo texto.
- [ ] El precio nunca baja de 2 ni sube de 20.
- [ ] El redondeo es hacia arriba en ambos términos.
- [ ] Lo que se carga al autor y lo que se abona al lector son la misma cifra.
- [ ] Cambiar el cuestionario no altera el precio de una corrección ya retenida.
- [ ] Las constantes se cambian por configuración, sin tocar código de dominio.
- [ ] Una pregunta sin mínimo declarado cuenta como 25 palabras.
- [ ] Las palabras se cuentan sobre el **texto plano**, sin marcado ni títulos.
- [ ] Cambiar el texto de un capítulo recalcula su precio **para las correcciones futuras**.
- [ ] Un cuestionario sin ningún mínimo no hace que el precio caiga al suelo absoluto.
- [ ] El precio se obtiene sin consultar tablas de `Work`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|

### El suelo de 25 palabras por pregunta

**`C-14` está resuelta.** Sin suelo, el segundo término valdría 0 y un autor podría pedir
correcciones de una novela por 2 créditos poniendo un cuestionario sin exigencias. Con suelo,
el precio nunca se desploma:

| Cuestionario | Palabras exigidas | Término de escritura |
|---|---|---|
| 1 pregunta sin mínimo | 25 | 1 |
| 3 preguntas sin mínimo | 75 | 1 |
| 5 preguntas sin mínimo | 125 | 2 |
| 10 preguntas sin mínimo | 250 | 3 |
| 3 preguntas de 100 | 300 | 3 |

Con 25 el suelo **sí se nota**: un cuestionario descuidado de diez preguntas cuesta 3 créditos
de escritura. Con el suelo de 10 que se barajó primero, apenas cambiaba nada.

Conviene saber lo que el suelo **no** resuelve: protege el precio de caer a cero, no de estar
mal equilibrado. Un autor puede poner diez preguntas sin mínimo y pagar 1 crédito de
escritura por diez respuestas.

No hace falta defenderse de eso en el precio, porque **el lector ve la recompensa antes de
empezar**: un cuestionario exigente y mal pagado simplemente no lo coge nadie. El mercado lo
corrige mejor que una regla.

**`C-44` resuelta: 25 palabras.**

## Estado

**Especificación:** `DRAFT`. La fórmula está decidida y `C-14` resuelta con el suelo de 10
palabras. Nada bloquea `APPROVED`.

**Implementación:** `TODO`. Deja de estar `BLOCKED`: ya no falta ninguna decisión de producto.
