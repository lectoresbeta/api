# 0006 — Sistema de créditos

- **Estado:** Aceptada
- **Fecha:** 2026-09-23
- **Afecta a:** `Credits`, `Feedback`, `Work`, `Reading`, `User`, `Notification`
- **Sustituye parcialmente a:** [`0004`](0004-credit-reservation-on-access-grant.md)

## Contexto

El sistema de créditos es el motor del producto: regula el intercambio entre recibir
feedback y darlo. El modelo heredado del material de partida —tramos por extensión del texto,
recargo por preguntas adicionales, bonificaciones sueltas— se fue complicando a medida que se
documentaban las pantallas, y llegó a incluir un margen de ajuste dinámico entre lo que paga
el autor y lo que cobra el lector, con la maquinaria que eso arrastra.

Se rediseña desde cero con tres objetivos explícitos:

1. **lo más simple posible**, sin dejar de ser justo;
2. **que el esfuerzo se pague**: no vale lo mismo corregir un texto largo que uno corto, ni
   responder un cuestionario exigente que uno sencillo;
3. **que la economía sea estable**: ni tantos créditos que nadie tenga aliciente para
   corregir, ni tan pocos que no haya textos que corregir.

### El hallazgo que ordena todo lo demás

**Si el autor paga exactamente lo que cobra el lector, una corrección no crea ni destruye
créditos: los mueve.** La masa total del sistema no depende de la fórmula de precios.

```text
total de créditos = grifos − desagües
```

De ahí se sigue algo que cambia el enfoque por completo:

> El promedio de créditos por usuario es **siempre** el tamaño del regalo de bienvenida.

Que «todos tengan demasiados créditos» es imposible por construcción: si alguien tiene 100,
es porque otros diez tienen 0. **El riesgo real no es la inflación, es la concentración.**

Y la consecuencia práctica: la fórmula de precios se puede ajustar sin poner en riesgo la
economía. Solo cambia la velocidad de circulación y el precio relativo de lo largo frente a
lo corto.

## Decisión

Se adopta un sistema de **transferencia pura** con un único grifo ordinario, precio
proporcional al esfuerzo, **sin retener nunca créditos del autor**: se comprueba el saldo al
empezar la corrección y se cobra al entregarla, admitiendo saldo negativo como red.

### 1. El precio mide esfuerzo, y se calcula por capítulo

```text
precio = techo(palabras del capítulo / 1.000) + techo(palabras exigidas / 100)
```

- El primer término es **leer**.
- El segundo es **escribir**: la suma de los mínimos de palabras que el autor exige en las
  preguntas de su cuestionario.

Mínimo **2** créditos, máximo **20**.

**Suelo por pregunta:** una pregunta sin mínimo declarado cuenta como **10 palabras**. Sin ese
suelo, un cuestionario sin exigencias valdría 0 en el segundo término y **una novela entera se
corregiría por 2 créditos**.

Ese segundo término es la pieza que más simplifica el modelo: **un solo número captura toda
la exigencia del cuestionario**. No hace falta pesar el número de preguntas, su tipo ni su
longitud por separado, porque el autor, al configurar el formulario, ya está declarando
cuánto trabajo pide.

| Texto | Palabras | Cuestionario | Precio |
|---|---|---|---|
| Microrrelato | 800 | 1 pregunta, 50 palabras | 1 + 1 = **2** |
| Relato breve | 3.000 | 3 × 100 palabras | 3 + 3 = **6** |
| Relato largo | 6.000 | 3 × 100 palabras | 6 + 3 = **9** |
| Capítulo de novela | 4.000 | 5 × 150 palabras | 4 + 8 = **12** |
| Capítulo, cuestionario mínimo | 4.000 | 1 campo libre, 100 | 4 + 1 = **5** |

Las dos constantes están calibradas para que ninguno de los dos términos domine: leer 1.000
palabras con atención crítica y escribir 100 de crítica cuestan aproximadamente lo mismo.
Ajustarlas es la forma de corregir el equilibrio entre leer y escribir sin tocar nada más.

### 2. Coste = recompensa, exactamente

El autor paga lo mismo que cobra el lector. Sin margen, sin comisión, sin cuenta de sistema.

Se explica en una frase: **pagas exactamente lo que gana quien te corrige**.

### 3. No se retiene nada: se comprueba el saldo al empezar y se cobra al entregar

**No se bloquean créditos del autor.** Un capítulo admite correcciones mientras el saldo del
autor cubra su precio, y el cargo ocurre cuando la corrección se entrega.

Cuando el lector pulsa «Empezar corrección»:

1. se comprueba que el saldo del autor **cubre el precio** de ese capítulo;
2. se **anota el precio** de esa corrección, que queda fijado;
3. el lector escribe, sin plazo;
4. al entregar, se carga al autor y se abona al lector **ese importe anotado**.

Anotar el precio no es retener: **no bloquea nada**. Sirve para que el lector sepa de antemano
lo que va a ganar y para que el autor no pueda cambiárselo a mitad de trabajo.

El autor **no declara nada por adelantado** y **nunca ve créditos apartados**: su saldo es el
que es hasta que alguien le entrega una corrección.

**La comprobación es orientativa, no una garantía.** Dos lectores pueden empezar a la vez
sobre un saldo que solo cubre a uno, y los dos cobrarán. Es una consecuencia aceptada, y la
regla 4 dice qué pasa entonces.

### 4. El corrector cobra siempre; el saldo puede quedar en negativo y la corrección se bloquea

Si el autor no llega, **el corrector cobra íntegro igualmente** y el autor queda en negativo.

Y aquí está la regla que lo cierra todo:

> **Una corrección que deja el saldo en negativo se entrega bloqueada: el autor ve que existe,
> pero no su contenido, hasta que reponga saldo.**

Con saldo negativo **no se pueden abrir correcciones nuevas**, pero **sí se puede corregir**.
Es como se sale del descubierto, y convierte la deuda en algo sano: devuelves a la comunidad
exactamente lo que le debes.

Esta regla unifica dos cosas que parecían mecanismos distintos: el desenlace de una carrera
entre lectores y el gancho de reactivación de la regla 9 **son el mismo comportamiento**. El
gancho no añade lógica: solo provoca a propósito una situación que el sistema ya sabe
manejar.

### 5. Un grifo ordinario: la bienvenida

**10 créditos**, una vez, al activar la cuenta. El mensaje es deliberado: *te damos para
empezar; lo demás se gana corrigiendo*.

### 6. Invitación: +5 cuando el invitado entrega su primera corrección

El momento del abono es la decisión de diseño, no el importe:

| Momento | Qué cuesta falsificarlo |
|---|---|
| Al registrarse el invitado | 30 segundos |
| Al activar el correo | Un correo desechable |
| **Al entregar su primera corrección** | **Una corrección real** |

Con el tercero, el fraude es **peor que el comportamiento honesto**: escribir esa corrección
desde la cuenta falsa da 5 créditos al invitador, mientras que escribirla desde la cuenta real
habría dado 6 utilizables.

Tope de **10 invitaciones premiadas por usuario**, no por la masa —que ya está acotada— sino
para que nadie construya su saldo reclutando en lugar de corrigiendo.

El efecto sobre la economía está acotado: cada usuario nuevo genera la bonificación **una sola
vez**, así que el total de bonificaciones nunca supera `5 × usuarios`. En el peor caso
teórico el sistema se comporta como si el regalo de bienvenida fuese 15 en vez de 10.

### 7. Propina: el autor premia una buena corrección de su propio saldo

Opcional, del saldo del autor, para una corrección que le ha servido.

Es una **transferencia**, así que se conserva; es inmune a la colusión, porque si dos cuentas
se propinan mutuamente el neto es cero; y significa algo precisamente porque a quien la da le
cuesta.

Sustituye a la bonificación por «feedback valorado positivamente», que era un grifo abierto a
que dos cuentas se valorasen en bucle.

### 8. El enlace público queda fuera de la economía

El autor puede generar una **URL pública** de su texto con el formulario y repartirla fuera
de la plataforma. Quien la recibe corrige **sin registrarse**, sin coste para el autor y sin
recompensa para el corrector.

Tiene tres virtudes:

- **Impacto nulo** sobre la masa de créditos: nada se crea, nada se destruye.
- Es una **válvula de seguridad**: un autor a cero siempre tiene una salida que no depende de
  nadie más que de su agenda, así que la economía nunca puede bloquearse del todo.
- Es el mejor canal de captación posible, porque la persona **ya ha hecho el trabajo** antes
  de registrarse.

Al terminar se le muestra qué habría ganado y se le ofrece el regalo de bienvenida ordinario.
**No se le abonan los créditos de esa corrección**: ver alternativas.

Regla imprescindible: **un usuario con sesión iniciada que abre un enlace público va al flujo
normal, con créditos**. Si no, el enlace sería un atajo para que el autor se ahorrase el pago
y los lectores registrados perdiesen el suyo.

### 9. Corrección en descubierto como gancho de reactivación

A usuarios **seleccionados** que han dejado de participar se les permite recibir una
corrección sin tener saldo. Lo que ocurre después ya lo describe la regla 4: el corrector
cobra, el autor queda en negativo y la corrección llega bloqueada.

**La selección se hace por cupo, no por antigüedad.** En vez de activarlo para cada usuario al
cumplir `N` días inactivo, se fija un **presupuesto periódico** —por ejemplo tres correcciones
en descubierto por semana— y se eligen los mejores candidatos.

| | Por plazo de cada usuario | **Por cupo periódico** |
|---|---|---|
| Deuda que se puede generar | Depende de cuánta gente cruce el umbral | **Acotada de antemano**: cupo × precio máximo |
| Control | Indirecto, ajustando el umbral | **Directo**: un número |
| A quién alcanza | A quien cumpla el plazo | **A los mejores candidatos** |
| Apagarlo | Subir el umbral y esperar | Poner el cupo a 0 |

Con tres por semana y un precio máximo de 20, la emisión no puede superar **60 créditos
semanales**, se corrija o no a esos autores. Es la diferencia entre un mecanismo con
presupuesto y uno con disparador.

El cupo es un **techo de elegibilidad**, no de deuda realizada: la deuda solo aparece si
alguien decide corregir a esos autores. Y la elegibilidad **caduca** al terminar el periodo;
si no se usó, no se acumula.

Condiciones que debe cumplir un candidato, y las tres primeras son obligatorias:

- **Solo si el texto seguía abierto a corrección.** Esa corrección no es mercancía no
  solicitada: es el cumplimiento de una petición que el autor hizo y luego abandonó. Si había
  cerrado sus textos, no hay nada que cobrar.
- **Solo si se le avisó antes**, al dejar la obra abierta. Con el aviso es un trato aceptado;
  sin él, una sorpresa desagradable.
- **Solo a quien ha corregido antes.** Se extiende crédito a quien ha demostrado que sabe
  devolverlo.

Entre los que cumplen, se prefiere a quien **más ha corregido** —mayor capacidad de
devolver—, a quien lleva **menos tiempo** inactivo —más probable que vuelva— y se excluye a
quien **ya dejó una deuda sin saldar**.

**Máximo una corrección en descubierto por autor**, lo que sale gratis de la regla 4.

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| **Margen dinámico** entre coste y recompensa | Permite regular la masa | Exige cuenta de sistema, versionado de la regla en cada movimiento y monitorización como requisito previo | Resuelve un problema —la inflación— que **no existe** en una transferencia pura |
| **Tramos por `TextTier`** con recargo por preguntas | Es el modelo del material de partida | Dos mecanismos distintos para medir lo mismo; los tramos crean saltos injustos en los bordes | La fórmula continua es más simple **y** más justa |
| **Retener al conceder el acceso** ([`0004`](0004-credit-reservation-on-access-grant.md)) | Garantiza el pago antes de leer | Retiene por lectores que quizá no corrijan; obliga a compensación entre `Reading` y `Credits` | Retener acota mejor que reservar plazas, pero sigue apartando créditos del autor |
| **Retener al empezar la corrección** | Evita casi todo descubierto | Le quita créditos al autor **antes** de que exista la corrección, y obliga a estados `HELD`/`CONFIRMED`/`RELEASED`, plazos de caducidad, liberación y un segundo saldo que explicar | **El descubierto es preferible a apartar créditos que aún no se deben.** Ver abajo |
| **Plazas declaradas por el autor** | La insignia refleja huecos pagados | Concepto nuevo que el autor debe entender y gestionar; encaja mal con la corrección por capítulo | No se entendía a la primera. Si hace falta explicarlo, sobra |
| **Un tope de plazos** en vez de cupo para el descubierto | Se aplica solo a cada usuario | La deuda total depende de cuánta gente cruce el umbral | El cupo la acota de antemano |
| **Abonar créditos al corrector anónimo que se registra** | Captación más potente | El enlace público es gratis para el autor y el precio lo fija su propio cuestionario: texto con cuestionario máximo + autocorrección en incógnito + alta = créditos de la nada | Además convertiría un flujo sin apuestas en uno con dinero, arrastrando el control antifraude a donde hoy no hace falta |
| **Bonificación por feedback valorado** | Premia la calidad | Grifo abierto a que dos cuentas se valoren en bucle | La propina lo hace mejor y se conserva |
| **Tope de saldo** | Ataca la concentración | Castiga precisamente a los mejores correctores | Se reserva como freno de emergencia, no como diseño de salida |

## Consecuencias

**Positivas**

- El modelo entero cabe en nueve reglas y se explica sin diagramas.
- La masa de créditos está acotada y es predecible: `10 × usuarios` más, como mucho,
  `5 × usuarios` de invitaciones, más el descubierto no recuperado, que tiene presupuesto.
- Ajustar el precio **no pone en riesgo la economía**, porque el precio es una transferencia.
- **Un solo saldo.** Al no retener nada, no hay «saldo disponible» que explicar ni dos cifras
  que cuadrar en cada pantalla.
- **Ningún estado intermedio.** Sin retenciones no hay `HELD`/`CONFIRMED`/`RELEASED`, ni
  plazos de caducidad, ni liberaciones, ni retenciones huérfanas que reconciliar.
- **Ningún contexto espera a `Credits` para dejar trabajar.** Como no hay nada que reservar,
  la comprobación de saldo puede resolverse contra una proyección: el panel de corrección se
  abre al instante.
- Desaparece la compensación entre `Reading` y `Credits`.
- Un lector nunca trabaja sin cobrar, que es la condición para que vuelva.
- La economía no puede bloquearse: el enlace público siempre deja una salida.
- El descubierto por carrera y el gancho de reactivación son **el mismo comportamiento**, así
  que el segundo no añade lógica.

**Negativas**

- **El descubierto por carrera será habitual, no excepcional.** Escribir una corrección lleva
  horas o días, así que la ventana en la que dos lectores coinciden sobre el mismo capítulo es
  larga. Un capítulo popular con saldo justo para una corrección puede recibir tres en una
  semana y dejar al autor en −24.
- Por tanto, **los autores verán correcciones bloqueadas con cierta frecuencia**, y la
  interfaz tiene que explicarlo bien o se percibirá como un castigo arbitrario.
- El descubierto **emite crédito sin respaldo** cuando la deuda no se recupera. Con cupo está
  presupuestado; por carrera, no.
- Con transferencia pura no hay palanca fina sobre la masa: las únicas son el regalo de
  bienvenida y la bonificación por invitación, y ambas afectan solo a usuarios nuevos.
- El fraude —correcciones vacías— **redistribuye injustamente** aunque no infle la economía.
  Sigue haciendo falta el control antifraude
  ([`FEAT-FBK-012`](../features/feedback/FEAT-FBK-012-correction-fraud-control.md)).
- La concentración de saldo en pocos correctores no tiene freno automático.

**Por qué se prefiere el descubierto a la retención**

Es la decisión menos obvia del ADR y conviene dejarla razonada. Retener es más preciso: casi
elimina el descubierto. Pero **le quita créditos al autor antes de que exista aquello por lo
que paga**, y eso tiene tres costes que la precisión no compensa:

1. **Para el autor**, ver saldo apartado por una corrección que quizá nunca llegue es peor que
   ver un saldo real que a veces baja de cero.
2. **Para el sistema**, una retención es un estado que hay que crear, caducar, liberar y
   reconciliar. Es la pieza con más casos límite de todo el modelo.
3. **Para la arquitectura**, obliga a que `Feedback` espere una respuesta de `Credits` antes
   de dejar escribir, que era el único punto donde la asincronía tenía coste visible.

Lo que se paga a cambio es el descubierto por carrera. Es un coste real, pero acotado, visible
y con un remedio que el usuario entiende: corrige y lo saldas.

**Qué coste tendría revertirla**

Alto para la fórmula y bajo para las constantes. Cambiar `1.000` y `100` es configuración;
volver a tramos, o introducir un margen, obliga a rehacer el registro de movimientos y a
decidir qué pasa con los saldos ya formados. Las constantes son la palanca; la estructura,
no.

## Cumplimiento

**Invariante contable.** La suma de todos los saldos —incluidos los negativos— debe ser igual
a la suma de los grifos menos lo emitido en descubierto no recuperado. Es un test, no una
aspiración: si deja de cumplirse, hay un movimiento que no es una transferencia.

**Ninguna cifra fuera de `Credits`.** Ni `Work` ni `Feedback` ni `Reading` calculan, proponen
o transportan importes ([`0002`](0002-credits-as-isolated-bounded-context.md)). Verificable
con Deptrac y revisando que ningún evento de otro contexto lleve un campo de importe.

**El precio se fija al empezar la corrección**, no al entregar. Test: cambiar el cuestionario
o el texto con una corrección en curso no altera lo que cobra ese lector.

**Ningún crédito se aparta.** Test: en ningún momento la suma de los saldos difiere de la suma
de los movimientos. Si aparece un segundo saldo, alguien ha reintroducido la retención.

**Métricas a vigilar** ([`FEAT-CRD-012`](../features/credits/FEAT-CRD-012-economy-health.md)):

| Métrica | Qué detecta |
|---|---|
| Capítulos corregibles por corrector activo | Si cae, los lectores no tienen dónde ganar |
| % de usuarios a cero o en negativo | Concentración |
| Tiempo hasta la primera corrección recibida | Es lo que el usuario siente |
| **Tasa de recuperación del descubierto** | Si es baja, el gancho de reactivación es emisión pura |
| **Descubierto por carrera** (correcciones bloqueadas que nadie provocó a propósito) | Si es alto, el saldo típico es demasiado ajustado y conviene subir la bienvenida |

Palancas, por orden de preferencia:

1. el **regalo de bienvenida**, que solo afecta a los nuevos y es reversible;
2. el **cupo de descubierto**, que es un presupuesto directo sobre la emisión;
3. las **dos constantes del precio**, que no mueven la masa;
4. un **tope de saldo**, como freno de emergencia.
