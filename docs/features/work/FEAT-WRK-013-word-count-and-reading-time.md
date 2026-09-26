---
id: FEAT-WRK-013
title: Recuento de palabras y tiempo de lectura
context: Work
concept: Chapter
actors: []
spec_status: APPROVED
impl_status: DONE
priority: P0
sources:
  - _sources/use-cases.pdf#p4
  - docs/ui/home.md
  - decision:0006
  - conversation:2026-09-25
endpoints: []
events: []
depends_on: [FEAT-WRK-001]
updated: 2026-09-25
---

# FEAT-WRK-013 — Recuento de palabras y tiempo de lectura

## Resumen

Cuántas palabras tiene un texto, y cuánto se tarda en leerlo.

El recuento **es el término de lectura del precio de toda corrección**
([`decision:0006`](../../decisions/0006-credit-system.md)), así que no es un adorno del
catálogo: es una cifra de la que depende dinero.

## El nivel de extensión está derogado

La ficha se llamaba «Calcular el número de palabras **y el nivel de extensión**», y esa
segunda mitad ya no existe.

[`decision:0006`](../../decisions/0006-credit-system.md) sustituyó los tramos por una
**fórmula continua** sobre el número de palabras, y el glosario lo recoge: `TextTier`
derogado, «el recuento de palabras sigue existiendo; la clasificación en niveles, no».

Con tramos, un texto de 999 palabras y otro de 1.001 caían en categorías distintas y costaban
cosas muy distintas, lo que premiaba escribir justo por debajo de un umbral. La fórmula
continua no tiene bordes que explotar.

Así que esta ficha cubre **el recuento y el tiempo de lectura**, y el nivel queda como lo que
es: una idea descartada con su razón escrita.

## Cómo se cuentan las palabras (`C-13`, resuelta)

La pregunta llevaba abierta desde el principio: **qué cuenta como palabra en un texto con
formato**. La respuesta tiene tres partes y las tres importan.

### Se cuenta sobre el texto plano, derivado del HTML ya saneado

No sobre lo que escribió el autor, sino sobre **lo que se guarda**. Contar sobre la entrada
haría que el número dependiera de lo que el saneador quite, y un cambio inocente en la lista
de etiquetas permitidas repreciaría en silencio todos los capítulos de la plataforma.

Las dos formas —el HTML y su texto plano— se **guardan las dos**, por lo mismo. Derivar el
texto al vuelo ataría la cifra al código de hoy.

### Las etiquetas de bloque cuentan como separación

`<p>uno</p><p>dos</p>` son **dos palabras**, no «unodos». Lo mismo con `<br>`, los títulos,
las citas y las líneas horizontales.

Parece evidente y no lo es: la forma perezosa de quitar marcado —`strip_tags` a secas— pega
la última palabra de un párrafo con la primera del siguiente, y en un capítulo de cuarenta
párrafos eso son cuarenta palabras menos, que en el precio se notan.

### Una palabra es lo que separa un espacio

Sin diccionario, sin partir por guiones y sin descontar signos.

| Texto | Cuenta | Por qué |
|---|---|---|
| `bien-parecido` | 1 | Es una palabra con guion, y partirla contaría dos donde el lector ve una |
| `—Hola, dijo ella` | 3 | La raya de diálogo va pegada a la palabra y no es una palabra |
| `...` suelto | 1 | Raro, y no merece una regla propia |
| `1.500` | 1 | Un número es una palabra |

El criterio es **lo que el lector contaría**, porque el número se le enseña a él y porque
sobre él se calcula lo que paga. Cualquier refinamiento —descontar signos, tratar los
diálogos aparte— acercaría la cifra a una definición técnica y la alejaría de la que
cualquiera puede comprobar mirando.

## El tiempo de lectura

La Home lleva enseñando «8 min lectura» desde el primer diseño, derivado del número de
palabras, y **no lo calculaba nadie**.

| Decisión | Valor | Por qué |
|---|---|---|
| Velocidad | **200 palabras por minuto** | Es el extremo prudente del rango de lectura silenciosa en prosa. Quien reserva ocho minutos y necesita doce se siente engañado; al revés, no |
| Redondeo | Hacia arriba | «0 min lectura» no informa de nada |
| Mínimo | 1 minuto | Por lo mismo |
| Dónde vive | `Work`, junto al recuento | Es una función del número de palabras y de nada más. Calcularla en el cliente la repetiría en cada pantalla, y el día que cambie la velocidad habría que buscarlas |

**No se guarda**: se deriva al servir. Una cifra derivada que se almacena es una cifra que se
queda vieja, y esta cambia cada vez que el texto cambia.

## Reglas de negocio

- `RN-1` Las palabras se cuentan sobre el **texto plano derivado del HTML saneado**.
- `RN-2` Las etiquetas de bloque separan palabras.
- `RN-3` Una palabra es una secuencia entre espacios. Sin diccionario ni excepciones.
- `RN-4` El recuento de una obra es la **suma de los de sus capítulos**, y se rehace al
  añadir, editar o quitar uno.
- `RN-5` El tiempo de lectura se **deriva** del recuento a 200 palabras por minuto,
  redondeando hacia arriba, con un mínimo de un minuto y **cero para un texto vacío**.
- `RN-6` El tiempo de lectura no se guarda.
- `RN-7` La cifra que se enseña y la que usa el precio son **la misma**. Dos recuentos
  distintos sobre el mismo texto es la forma más rápida de que alguien deje de fiarse de lo
  que paga.

## Contrato de API

Ninguna ruta nueva. `readingMinutes` acompaña a `wordCount` donde ya viaja:

| Recurso | Dónde |
|---|---|
| Obra | `GET /api/v1/works/{workId}` |
| Capítulo de esa obra | Lo mismo, en cada elemento |
| Mis relatos | `GET /api/v1/me/works` |
| Capítulo por enlace público | `GET /api/v1/public/chapters/{token}` |

## Modelo de datos afectado

Ninguno. El recuento ya se guarda y el tiempo se deriva.

## Criterios de aceptación

- [x] `<p>uno</p><p>dos</p>` cuenta dos palabras.
- [x] Una palabra con guion cuenta una.
- [x] Una raya de diálogo pegada a una palabra no cuenta aparte.
- [x] El recuento de la obra es la suma del de sus capítulos, y se rehace al editar.
- [x] El marcado no cuenta: añadir negritas a un texto no cambia su recuento.
- [x] Un texto vacío son cero palabras y cero minutos.
- [x] Mil palabras son cinco minutos.
- [x] Una sola palabra es un minuto, no cero.
- [x] El tiempo de lectura viaja junto al recuento en todos los recursos que lo llevan.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| W-18 | ¿La velocidad de lectura debería depender del idioma? | Enlaza con `V-3`, si la plataforma deja de ser solo en español |
| W-19 | ¿Se enseña el tiempo de lectura de la obra entera, o solo el del capítulo? | Hoy viajan los dos y lo decide quien pinta |

## Estado

**Especificación:** `APPROVED` (2026-09-25). Redactada a posteriori: el recuento llevaba
funcionando desde `FEAT-WRK-001` sin ficha que lo describiera, y `C-13` seguía abierta con la
respuesta ya escrita en el código.

**Implementación:** `DONE` (2026-09-25).
