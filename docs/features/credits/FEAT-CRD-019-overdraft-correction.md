---
id: FEAT-CRD-019
title: Corrección en descubierto como gancho de reactivación
context: Credits
concept: Reservation
actors: []
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - conversation:2026-09-23 (rediseño del sistema de créditos)
  - docs/decisions/0006-credit-system.md
endpoints: []
events: [OverdraftCorrectionGranted, ReactivationOfferChoiceChanged]
depends_on: [FEAT-CRD-009, FEAT-CRD-018]
updated: 2026-09-25
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
| `OverdraftCorrectionGranted` | Alguien **ha corregido** un capítulo que su autor no podía pagar | `Notification` (el correo gancho) |

**Dice que se ha usado, no que se haya concedido.** Conceder la elegibilidad no mueve un
crédito, y anunciarlo produciría un correo por algo que puede no llegar a ocurrir nunca: la
mayoría de las veces nadie corregirá a ese autor esa semana.

Dos hechos que la ficha proponía y que **no existen**, cada uno por la misma razón:

| Propuesto | Por qué no está |
|---|---|
| `Feedback` consumiendo `OverdraftCorrectionGranted` para bloquear | El bloqueo ya lo dispara `CreditBalanceWentNegative` (`FEAT-CRD-018`). Dos hechos que bloquean la misma corrección son dos verdades sobre lo mismo, y el día que una se retrase la corrección quedará medio bloqueada |
| `CorrectionUnlocked` | `CreditDebtCleared` ya desbloquea, y por lo mismo |

Esto es lo que la ficha prometía en su resumen —«no añade mecánica»— llevado hasta el final:
lo único que hacía falta publicar era lo que nadie sabía todavía.

**Y uno que no estaba:** `ReactivationOfferChoiceChanged`, que publica `User` cuando alguien
acepta o rechaza el mecanismo. `Credits` no puede preguntárselo a nadie
([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md)), así que la
renuncia tiene que llegarle por la cola y quedarse en una proyección suya.

**Quién bloquea el contenido es `Feedback`, no `Credits`.** `Credits` publica el hecho
económico; `Feedback`, que es quien posee la corrección, decide qué enseña. Si `Credits`
controlara la visibilidad del texto, estaría gobernando el modelo de otro contexto.

## Criterios de aceptación

- [x] Solo se concede a autores que cumplen las tres condiciones.
- [x] Nunca hay dos correcciones en descubierto del mismo autor.
- [x] El corrector cobra íntegro y no ve nada distinto.
- [x] El autor ve metadatos y no el contenido.
- [x] Al reponer saldo, la corrección se desbloquea sin intervención.
- [x] Una corrección bloqueada no se borra aunque pasen meses.
- [x] No se selecciona a quien tiene las notificaciones desactivadas.
- [x] El mecanismo se desactiva globalmente por configuración.
- [x] La tasa de recuperación es consultable.
- [x] En un periodo nunca se conceden más descubiertos que el cupo.
- [x] La elegibilidad no usada no se acumula al periodo siguiente.
- [x] Quien dejó una deuda sin saldar no vuelve a ser seleccionado.
- [x] Poner el cupo a 0 desactiva el mecanismo sin efectos colaterales.

Los cinco que empiezan por «al reponer», «una corrección bloqueada», «el corrector cobra» y
«el autor ve» los cubría ya `FEAT-CRD-018`, y esta ficha los hereda sin tocarlos, que era
exactamente la promesa de su resumen. Los demás están en
`tests/Functional/Credits/OverdraftReactivationTest.php`.

## Cómo se reparte el cupo

Un comando, `lectoresbeta:credits:grant-overdrafts`, que se programa con la periodicidad del
cupo. Con qué se programa es decisión de operación, igual que la purga de alias
(`FEAT-USR-036` `N-15`): atarlo al código significaría que cambiar el día es un despliegue.

Admite `--dry-run`, que enseña a quién elegiría sin conceder nada. No es una cortesía: este
comando genera deuda, y mirar antes de tocar la primera vez es lo mínimo.

La consulta que elige vive en `Credits` y **no pregunta a ningún otro contexto**. Todo lo que
hace falta ya estaba aquí: el saldo, el precio de cada capítulo y el historial de movimientos.
Lo único que llega de fuera es la renuncia, y llega por la cola.

## Decisiones tomadas al implementar

| Decisión | Por qué |
|---|---|
| Elegible y usado son **dos estados distintos** de la misma fila | Es lo que sostiene la ficha entera: el cupo limita a cuánta gente se le abre la puerta, no cuánta deuda aparece. Y la tasa de recuperación solo cuenta lo usado, porque una elegibilidad que nadie aprovechó no emitió un solo crédito |
| Un descubierto **por persona, para siempre** | `RN-1` y `RN-6b` juntas. Más estricto que la lectura literal de la ficha —que permitiría otro a quien sí volvió— y a cambio no hay forma de que el mecanismo insista con la misma persona semana tras semana. Queda anotado como `C-50` |
| El tope de tres correcciones simultáneas **sigue aplicándose** sobre un capítulo con descubierto | Sin él, tres lectores llegando a la vez multiplicarían por tres la deuda que el cupo acotaba. La excepción es «puede pagar», no «no hay límite» |
| Renunciar es apagar el aviso `REACTIVATION_OFFER`, y solo existe por correo | `RN-2d` y `RN-8` son la misma decisión vista dos veces: sin aviso no hay gancho, solo deuda. Un único canal hace además que apagarlo sea inequívoco, que es lo que `Credits` necesita para dejar de seleccionar |
| El uso y el saldado se detectan junto al aviso de movimientos | Es el único sitio que sabe el saldo de antes y el de después. Detectar los cruces en otro lado sería escribir dos veces la misma comparación |
| El tercer criterio de orden —el interés que despierta la obra— **no se aplica** | Se mide en lecturas y seguidores, que viven en `Community`. Traerlo costaría una dependencia entre contextos que este mecanismo no justifica. Se ordena por correcciones dadas y por lo reciente de la última actividad |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| C-28 | ¿Se avisa al autor **en el momento** de dejar la obra abierta, o basta con las condiciones generales? | Decide si es un trato aceptado o una sorpresa. Sigue abierta, y es la única de las tres condiciones obligatorias que el backend no puede garantizar solo |
| C-50 | ¿Debería poder concederse un segundo descubierto a quien sí volvió con el primero? | Hoy no: uno por persona. Cuando haya datos de recuperación se sabrá si merece la pena |
| C-51 | ¿Debería pesar el interés que despierta la obra en el orden de los candidatos? | Lo pide la ficha y no se aplica: vive en `Community`. Haría falta un hecho que `Credits` pudiera proyectar |

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

**Implementación:** `DONE` (2026-09-25). Nace **encendido con cupo 3**
(`app.overdraft.weekly_quota`), pero no concede nada hasta que exista gente que lleve entre 30
y 180 días sin mover un crédito, que hoy no existe: era lo último que convenía construir
precisamente por eso.

`C-28` sigue abierta y conviene no perderla de vista: es la única de las tres condiciones que
separan esto de un patrón oscuro que el backend **no puede garantizar por su cuenta**. Las
otras dos —que el texto siguiera abierto y que la persona haya corregido antes— están en la
consulta que elige.
