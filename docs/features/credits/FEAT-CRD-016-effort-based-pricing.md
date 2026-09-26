---
id: FEAT-CRD-016
title: Precio de una corrección según el esfuerzo
context: Credits
concept: Pricing
actors: []
spec_status: APPROVED
impl_status: PARTIAL
priority: P0
sources:
  - conversation:2026-09-23 (rediseño del sistema de créditos)
  - docs/decisions/0006-credit-system.md
endpoints: []
events: [QuestionnaireUpdated, ChapterContentUpdated]
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
| `palabras del capítulo / 1.000` | Leer el texto con atención crítica | `Work`, vía `ChapterContentUpdated` |
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
| `ChapterContentUpdated` | Las palabras de ese capítulo, y su posición en la obra |
| `QuestionnaireUpdated` | Las palabras exigidas por el cuestionario |

`Credits` mantiene con ellos un **read model propio** con el precio vigente de cada capítulo.
No consulta las tablas de `Work`: eso sería acceso directo al modelo de otro contexto.

Ese read model es además lo que permite a `Work` mostrar la insignia de la tarjeta sin
acoplarse ([`FEAT-CRD-013`](FEAT-CRD-013-work-credit-badge.md)).

**Publica**

Ninguno. Calcular no es un hecho de negocio.

## Modelo de datos afectado

`chapter_price`: `chapter_id`, `work_id`, `author_id`, `position`, `word_count`,
`required_words`, `price`, `updated_at`. Un registro por capítulo, actualizado por los dos
eventos.

`position` está ahí porque **el precio de un capítulo depende de si es el último**: es donde
se responden las preguntas de alcance `LAST_CHAPTER`
([`FEAT-WRK-014`](../work/FEAT-WRK-014-configure-questionnaire.md), `W-17`).

`work_questionnaire_demand`: `work_id`, `version`, `required_words`,
`required_words_for_every_chapter`, `updated_at`. Guarda lo que exige el cuestionario vigente
de la obra.

Hace falta porque **los dos hechos que forman un precio llegan por separado y en cualquier
orden**. Sin un sitio donde conservar la exigencia del cuestionario, un capítulo añadido la
semana siguiente se cobraría como si la obra no pidiera nada, y seguiría mal hasta que el
autor volviera a tocar el cuestionario por casualidad.

Las dos son read models: se reconstruyen enteras reprocesando los eventos, y no son la fuente
de verdad de nada. Lo que a un lector se le debe se congela aparte, al empezar (`RN-4`), así
que una fila equivocada aquí es una molestia, no una pérdida de dinero.

## Criterios de aceptación

- [x] Un capítulo más largo cuesta más que uno corto con el mismo cuestionario.
- [x] Un cuestionario más exigente cuesta más sobre el mismo texto.
- [x] El precio nunca baja de 2 ni sube de 20.
- [x] El redondeo es hacia arriba en ambos términos.
- [ ] Lo que se carga al autor y lo que se abona al lector son la misma cifra. *La cifra es
      una sola y así se prueba, pero **todavía no se carga a nadie**: mover créditos es de
      [`FEAT-CRD-006`](FEAT-CRD-006-charge-author-for-received-feedback.md).*
- [ ] Cambiar el cuestionario no altera el precio de una corrección ya retenida. *La
      separación existe —`CorrectionPrice` es otra tabla— pero nadie la escribe hasta
      [`FEAT-CRD-009`](FEAT-CRD-009-balance-check-on-correction-start.md).*
- [x] Las constantes se cambian por configuración, sin tocar código de dominio.
- [x] Una pregunta sin mínimo declarado cuenta como 25 palabras.
- [x] Las palabras se cuentan sobre el **texto plano**, sin marcado ni títulos.
- [ ] Cambiar el texto de un capítulo recalcula su precio **para las correcciones futuras**.
      *El consumidor ya lo hace; falta quien publique el hecho al editar, porque editar un
      capítulo no existe todavía (`FEAT-WRK-005`, sin ficha).*
- [x] Un cuestionario sin ningún mínimo no hace que el precio caiga al suelo absoluto.
- [x] El precio se obtiene sin consultar tablas de `Work`.

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

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `PARTIAL` (2026-09-23). **El precio ya existe y se mantiene solo.**

### Hecho

- `Credits` consume `ChapterContentUpdated` y `QuestionnaireUpdated` y mantiene con ellos
  `chapter_price` y `work_questionnaire_demand`. No hay una sola consulta a tablas de `Work`,
  ni una clase compartida: cada lado declara la suya
  ([`decision:0013`](../../decisions/0013-integration-events-travel-without-class-names.md)).
- El precio de un capítulo depende de **si es el último**, que es lo que da sentido a las dos
  cifras de palabras exigidas del cuestionario.
- Las cinco palancas —1.000, 100, el suelo de 25, y el mínimo y el máximo— son parámetros de
  `config/services.yaml` (`RN-7`). Una calibración imposible —un divisor a cero, un rango
  vacío— falla al construir el servicio, no semanas después con una cifra inexplicable.
- Idempotencia por `(eventId, consumer)`, y además **la versión decide**: una
  `QuestionnaireUpdated` vieja que llega tarde no rebaja un precio que el autor ya subió.

### La renombrada: `WorkContentUpdated` pasa a ser `ChapterContentUpdated`

La ficha pedía un hecho de obra con un `wordCount` de obra, y eso no alimenta lo que esta
misma ficha describe: `RN-2` dice que **la unidad es el capítulo**, y el read model tiene una
fila por capítulo. Saber que una novela tiene 80.000 palabras no dice nada sobre lo que vale
corregir su tercer capítulo, y un payload plano —la única forma que admite un evento de
integración— no puede llevar la lista de capítulos con sus longitudes.

Así que el hecho que se publica es el que de verdad ocurre: **un capítulo tiene otro texto**.
Lleva `chapterId`, `workId`, `authorId`, `position` y `wordCount`.

`position` viaja con él porque el último capítulo es el que responde las preguntas de alcance
`LAST_CHAPTER`, y `Credits` no puede preguntarle a `Work` cuál es sin meterse en su modelo.

### Falta

- **Cobrar** no es de aquí. Esto pone precio; mover créditos es
  [`FEAT-CRD-006`](FEAT-CRD-006-charge-author-for-received-feedback.md), y congelar el precio al empezar,
  [`FEAT-CRD-009`](FEAT-CRD-009-balance-check-on-correction-start.md). ~~`CorrectionPrice` ya
  existe como tabla y nadie la escribe.~~ — **caducado** (revisado el 2026-09-26): hay un
  repositorio Doctrine que la escribe, y `QuoteCorrectionOnStart` la usa.
- ~~**El aviso al autor** cuando ampliar un capítulo lo encarece (`RN-9`, `C-15`)~~ — **hecho**
  (2026-09-26), y por el camino apareció un defecto que nadie había visto: **`Credits` no
  publicaba `ChapterPriceChanged` al editar un capítulo**. `ChapterPrice::updateContent()` ya
  repreciaba la fila, así que `WorkPricing::reprice()` comparaba el precio nuevo consigo mismo
  y nunca detectaba un cambio. La insignia del catálogo (`FEAT-CRD-013`) se quedaba con la
  cifra vieja hasta que otra cosa repreciase la obra.

  Arreglado tomando una foto de los precios **antes** de tocar nada, que es además lo que
  permite que el hecho lleve `previousCredits` y que el aviso salga **solo cuando sube**: un
  capítulo que se abarata no interrumpe a nadie.
- **La estimación mientras se configura** (`RN-6`): el autor no ve el precio hasta que guarda.
  Necesita el contrato de consulta de solo lectura que
  [`FEAT-WRK-014`](../work/FEAT-WRK-014-configure-questionnaire.md) `W-12` describe, y que
  [`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md) permite.
  Es lo primero que conviene añadir aquí.
- **Los eventos que espera el catálogo** —`CreditBalanceChanged` y
  `ChapterCorrectabilityChanged`— siguen sin existir, así que
  [`FEAT-WRK-012`](../work/FEAT-WRK-012-browse-catalogue.md) sigue bloqueado. Esta ficha no
  los pide («Publica: ninguno»), pero es donde tendrán que nacer.
