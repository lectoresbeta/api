---
id: FEAT-CRD-019
title: Corrección en descubierto como gancho de reactivación
context: Credits
concept: Reservation
actors: []
spec_status: APPROVED
impl_status: TODO
priority: P2
sources:
  - conversation:2026-09-23 (rediseño del sistema de créditos)
  - docs/decisions/0006-credit-system.md
endpoints: []
events: [OverdraftCorrectionGranted, CorrectionUnlocked]
depends_on: [FEAT-CRD-009, FEAT-CRD-018]
updated: 2026-09-24
---

# FEAT-CRD-019 — Corrección en descubierto

## Resumen

A usuarios **seleccionados** que han dejado de participar se les permite recibir **una**
corrección sin tener saldo.

Lo que ocurre después no es nuevo: es exactamente lo que
[`FEAT-CRD-018`](FEAT-CRD-018-negative-balance.md) ya describe —el corrector cobra, el autor
queda en negativo y la corrección llega bloqueada—. **Esta ficha no añade mecánica; solo
provoca a propósito una situación que el sistema ya sabe manejar**, y le pone un presupuesto.

El autor recibe un correo avisándole.

El mensaje se escribe solo, porque es la tesis de la plataforma en miniatura:

> **Para leer una corrección, haz una corrección.**

No es «paga para ver»: es reponer tu saldo haciendo por otro exactamente lo que alguien acaba
de hacer por ti. Que el precio de leerla sea aproximadamente el trabajo que costó escribirla
es lo más justo que este sistema puede ofrecer.

## Qué separa esto de un patrón oscuro

Cobrar a alguien por algo que no pidió y ocultárselo hasta que pague tiene mala pinta. Tres
condiciones lo cambian por completo, y **las tres son obligatorias**:

| Condición | Por qué |
|---|---|
| **El texto seguía abierto a corrección** | Esa corrección no es mercancía no solicitada: es el cumplimiento de una petición que el autor hizo y luego abandonó |
| **Se le avisó antes**, al dejar la obra abierta | Con el aviso es un trato aceptado; sin él, una sorpresa el día que vuelve |
| **Ha corregido antes** | Se extiende crédito a quien ha demostrado que sabe devolverlo |

Si el autor había cerrado sus textos, no hay nada que cobrar y no hay gancho. Esa es la línea.

## La selección se hace por cupo, no por antigüedad

**Es la decisión que hace controlable este mecanismo.** En vez de activarlo para cada usuario
al cumplir `N` días inactivo, se fija un **presupuesto periódico** —por ejemplo tres
correcciones en descubierto por semana— y se eligen los mejores candidatos.

| | Por plazo de cada usuario | **Por cupo periódico** |
|---|---|---|
| Deuda que se puede generar | Depende de cuánta gente cruce el umbral | **Acotada de antemano**: cupo × precio máximo |
| Control | Indirecto, ajustando el umbral | **Directo**: un número |
| A quién alcanza | A quien cumpla el plazo | **A los mejores candidatos** |
| Apagarlo | Subir el umbral y esperar | Poner el cupo a 0 |

Con tres por semana y un precio máximo de 20, la emisión no puede superar **60 créditos
semanales**. Es la diferencia entre un mecanismo con presupuesto y uno con disparador: el
segundo se dispara cuando le toca, el primero cuando tú decides y hasta donde tú decides.

El cupo es un **techo de elegibilidad, no de deuda realizada**: la deuda solo aparece si
alguien decide corregir a esos autores. Y la elegibilidad **caduca** al terminar el periodo:
si no se usó, no se acumula.

### Cómo se ordenan los candidatos

Entre los que cumplen las condiciones obligatorias, se prefiere:

| Criterio | Por qué |
|---|---|
| **Más correcciones dadas** | Mayor capacidad de devolver la deuda |
| **Menos tiempo inactivo** | Más probable que vuelva. Quien lleva dos años fuera no vuelve por un correo |
| **Obra con más interés** (lecturas, seguidores) | Más probable que alguien la corrija y el gancho llegue a existir |

Y se excluye a quien **ya dejó una deuda sin saldar**: no se presta dos veces a quien no
devolvió.

## Reglas de negocio

- `RN-1` **Máximo una corrección en descubierto por autor.** Sale gratis de `RN-2` de
  [`FEAT-CRD-018`](FEAT-CRD-018-negative-balance.md): con saldo negativo no se reciben más.
- `RN-2` Los candidatos se seleccionan **por cupo periódico**, no por plazo individual. La
  elegibilidad caduca al terminar el periodo.
- `RN-2b` El cupo por defecto es **3 por semana** (`C-42`). Con un precio máximo de 20, la
  emisión no puede superar 60 créditos semanales.
- `RN-2c` Entran en la lista de candidatos quienes lleven **entre 30 y 180 días** inactivos
  (`C-43`). Por debajo de 30 no están dormidos; por encima de 180 la probabilidad de volver
  cae tanto que el cupo rinde más en gente más reciente.
- `RN-2d` El autor puede **renunciar a este mecanismo** desde Configuración (`C-29`). Quien lo
  desactive no entra en la lista.
- `RN-3` El corrector **cobra íntegro** y no sabe que el autor estaba en descubierto. Para él
  no cambia nada.
- `RN-4` El autor ve **metadatos y un objetivo concreto**, no contenido: quién, cuándo, sobre
  qué capítulo, cuántas palabras y **cuántos créditos le faltan** para desbloquearla (`C-31`).
  Una meta concreta motiva mucho más que «repón saldo».
- `RN-5` Al volver a saldo ≥ 0, la corrección **se desbloquea automáticamente**.
- `RN-6` La corrección bloqueada **no se borra nunca** y **no se desbloquea por el paso del
  tiempo** (`C-30`). Desbloquear al año regalaría lo que no se pagó y enseñaría que esperar
  funciona.
- `RN-6b` A quien no volvió con un descubierto **no se le concede otro**. Si no funcionó con
  uno, no funcionará con dos, y cada intento es una hora de trabajo de un lector que nadie
  leerá.
- `RN-7` El mecanismo **se puede desactivar** globalmente sin afectar a nada más.
- `RN-8` Quien tiene notificaciones desactivadas **no se selecciona**: sin correo no hay
  gancho, solo deuda.

`RN-3` importa: el lector no debe enterarse de la situación económica del autor. Es
información que no le corresponde, y saberlo podría desanimarle a corregir.

`RN-8` evita el peor resultado posible: generar deuda a alguien que nunca va a recibir el
aviso que la justificaba.

## Por qué solo una por autor

Tres motivos que apuntan al mismo sitio:

| | |
|---|---|
| **Económico** | Junto al cupo, acota la emisión por partida doble |
| **De producto** | El gancho ya funciona con una. Una segunda no añade curiosidad, solo deuda |
| **Humano** | Cada corrección bloqueada es una hora de trabajo de un lector que quizá nadie lea. Una es aceptable; cinco es malgastar a tus mejores usuarios |

## Qué se muestra sin mostrar el contenido

Un «tienes una corrección» a secas motiva poco. Los metadatos motivan mucho y no regalan nada:

```text
Marta ha corregido «El último tren» · capítulo 2 · hace 3 días · 480 palabras
                    [ Bloqueada — repón tu saldo para leerla ]
```

**Nada de mostrar un fragmento difuminado.** O regalas parte del valor, o pareces un muro de
pago de periódico.

## El coste económico, dicho claramente

**Una deuda que no se recupera es crédito emitido sin respaldo.** El coste de este mecanismo
es proporcional a su **tasa de fracaso**: si reactiva al 30%, el 70% restante es emisión pura.

No lo invalida —es un coste de captación, como cualquier otro— pero obliga a medirlo. Es una
de las métricas de [`FEAT-CRD-012`](FEAT-CRD-012-economy-health.md), y es la que decide si
esto sigue encendido:

> Si de cada diez deudas vuelven tres, sale a cuenta. Si vuelve una, se está regalando crédito
> para nada y conviene apagarlo o restringir más a quién se le ofrece.

## Eventos

**Publica**

| Evento | Cuándo | Consumidores |
|---|---|---|
| `OverdraftCorrectionGranted` | Se concede la retención en descubierto | `Feedback` (marca la corrección como bloqueada), `Notification` (el correo gancho) |
| `CorrectionUnlocked` | El autor vuelve a saldo ≥ 0 | `Feedback`, `Notification` |

**Quién bloquea el contenido es `Feedback`, no `Credits`.** `Credits` publica el hecho
económico; `Feedback`, que es quien posee la corrección, decide qué enseña. Si `Credits`
controlara la visibilidad del texto, estaría gobernando el modelo de otro contexto.

## Criterios de aceptación

- [ ] Solo se concede a autores que cumplen las tres condiciones.
- [ ] Nunca hay dos correcciones en descubierto del mismo autor.
- [ ] El corrector cobra íntegro y no ve nada distinto.
- [ ] El autor ve metadatos y no el contenido.
- [ ] Al reponer saldo, la corrección se desbloquea sin intervención.
- [ ] Una corrección bloqueada no se borra aunque pasen meses.
- [ ] No se selecciona a quien tiene las notificaciones desactivadas.
- [ ] El mecanismo se desactiva globalmente por configuración.
- [ ] La tasa de recuperación es consultable.
- [ ] En un periodo nunca se conceden más descubiertos que el cupo.
- [ ] La elegibilidad no usada no se acumula al periodo siguiente.
- [ ] Quien dejó una deuda sin saldar no vuelve a ser seleccionado.
- [ ] Poner el cupo a 0 desactiva el mecanismo sin efectos colaterales.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| # | Pregunta | Impacto |
|---|---|---|
| C-28 | ¿Se avisa al autor **en el momento** de dejar la obra abierta, o basta con las condiciones generales? | Decide si es un trato aceptado o una sorpresa |

Resueltas: `C-42` (**3 por semana**), `C-43` (**30 a 180 días** de inactividad), `C-29` (**sí,
renunciable** desde Configuración), `C-30` (**no se desbloquea** por el paso del tiempo) y
`C-31` (se muestran metadatos **y el objetivo concreto**).

`C-30` tenía doble filo y conviene dejar razonado por qué se resuelve así: no desbloquear deja
una corrección escrita que nadie leerá jamás, lo cual es feo. Pero desbloquearla regalaría lo
que no se pagó y enseñaría que esperar funciona. La respuesta al problema de fondo no es
desbloquear, es **no volver a conceder descubierto a quien no volvió** (`RN-6b`): así no se
acumulan correcciones perdidas.

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `TODO`. Es lo último que conviene construir: no aporta nada hasta que haya
usuarios dormidos que reactivar.
