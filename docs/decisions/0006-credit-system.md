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
proporcional al esfuerzo y retención en el momento en que el lector empieza a trabajar.

### 1. El precio mide esfuerzo, y se calcula por capítulo

```text
precio = techo(palabras del capítulo / 1.000) + techo(palabras exigidas / 100)
```

- El primer término es **leer**.
- El segundo es **escribir**: la suma de los mínimos de palabras que el autor exige en las
  preguntas de su cuestionario.

Mínimo **2** créditos, máximo **20**.

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

### 3. La retención ocurre al empezar la corrección

Cuando el lector pulsa «Empezar corrección»:

1. se comprueba que el autor tiene **disponible** el precio de ese capítulo;
2. se **retiene** ese importe —el saldo no baja, deja de estar disponible—;
3. el lector dispone de un **plazo** para entregar;
4. al entregar, la retención se convierte en cargo y el lector cobra;
5. si no entrega, se libera y el capítulo vuelve a estar disponible.

El autor **no declara nada por adelantado**: abre su texto a corrección y, mientras tenga
saldo disponible, se puede corregir.

### 4. El corrector cobra siempre; el saldo puede quedar en negativo

Si por una carrera entre lectores o por un gasto simultáneo el autor no llega, **el corrector
cobra íntegro igualmente** y el autor queda en negativo.

Con saldo negativo **no se reciben más correcciones**, pero **sí se puede corregir**. Es como
se sale del descubierto, y convierte la deuda en algo sano: devuelves a la comunidad
exactamente lo que le debes.

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
corrección sin tener saldo. El corrector cobra con normalidad; el autor queda en negativo y
**ve que la corrección existe, pero no su contenido**, hasta que reponga saldo.

Condiciones, y las tres importan:

- **Solo si el texto seguía abierto a corrección.** Esa corrección no es mercancía no
  solicitada: es el cumplimiento de una petición que el autor hizo y luego abandonó. Si había
  cerrado sus textos, no hay nada que cobrar.
- **Solo si se le avisó antes**, al dejar la obra abierta. Con el aviso es un trato aceptado;
  sin él, una sorpresa desagradable.
- **Solo a quien ha corregido antes.** Se extiende crédito a quien ha demostrado que sabe
  devolverlo.

**Máximo una corrección en descubierto por autor**, lo que sale gratis de la regla 4.

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| **Margen dinámico** entre coste y recompensa | Permite regular la masa | Exige cuenta de sistema, versionado de la regla en cada movimiento y monitorización como requisito previo | Resuelve un problema —la inflación— que **no existe** en una transferencia pura |
| **Tramos por `TextTier`** con recargo por preguntas | Es el modelo del material de partida | Dos mecanismos distintos para medir lo mismo; los tramos crean saltos injustos en los bordes | La fórmula continua es más simple **y** más justa |
| **Retener al conceder el acceso** ([`0004`](0004-credit-reservation-on-access-grant.md)) | Garantiza el pago antes de leer | Retiene por lectores que quizá no corrijan; obliga a compensación entre `Reading` y `Credits` | Retener al empezar a corregir acota mejor y elimina la compensación |
| **Plazas declaradas por el autor** | La insignia refleja huecos pagados | Concepto nuevo que el autor debe entender y gestionar; encaja mal con la corrección por capítulo | No se entendía a la primera. Si hace falta explicarlo, sobra |
| **No retener nada** y cargar al entregar | Lo más simple de todo | Varios lectores simultáneos hunden al autor en negativo sin aviso | El descubierto debe ser excepción, no mecánica habitual |
| **Abonar créditos al corrector anónimo que se registra** | Captación más potente | El enlace público es gratis para el autor y el precio lo fija su propio cuestionario: texto con cuestionario máximo + autocorrección en incógnito + alta = créditos de la nada | Además convertiría un flujo sin apuestas en uno con dinero, arrastrando el control antifraude a donde hoy no hace falta |
| **Bonificación por feedback valorado** | Premia la calidad | Grifo abierto a que dos cuentas se valoren en bucle | La propina lo hace mejor y se conserva |
| **Tope de saldo** | Ataca la concentración | Castiga precisamente a los mejores correctores | Se reserva como freno de emergencia, no como diseño de salida |

## Consecuencias

**Positivas**

- El modelo entero cabe en nueve reglas y se explica sin diagramas.
- La masa de créditos está acotada y es predecible: `10 × usuarios` más, como mucho,
  `5 × usuarios` de invitaciones.
- Ajustar el precio **no pone en riesgo la economía**, porque el precio es una transferencia.
- Desaparece la compensación entre `Reading` y `Credits`: la retención vive entera en
  `Credits` y se dispara con un hecho de `Feedback`.
- Un lector nunca trabaja sin cobrar, que es la condición para que vuelva.
- La economía no puede bloquearse: el enlace público siempre deja una salida.

**Negativas**

- El descubierto **emite crédito sin respaldo** cuando la deuda no se recupera. Es el coste de
  captación del gancho de reactivación y hay que medirlo (ver **Cumplimiento**).
- Con transferencia pura no hay palanca fina sobre la masa: las únicas son el regalo de
  bienvenida y la bonificación por invitación, y ambas afectan solo a usuarios nuevos.
- El fraude —correcciones vacías— **redistribuye injustamente** aunque no infle la economía.
  Sigue haciendo falta el control antifraude
  ([`FEAT-FBK-012`](../features/feedback/FEAT-FBK-012-correction-fraud-control.md)).
- La concentración de saldo en pocos correctores no tiene freno automático.

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

**El precio se fija al retener**, no al entregar. Test: cambiar el cuestionario con una
corrección en curso no altera lo que cobra ese lector.

**Métricas a vigilar** ([`FEAT-CRD-012`](../features/credits/FEAT-CRD-012-economy-health.md)):

| Métrica | Qué detecta |
|---|---|
| Capítulos corregibles por corrector activo | Si cae, los lectores no tienen dónde ganar |
| % de usuarios a cero o en negativo | Concentración |
| Tiempo hasta la primera corrección recibida | Es lo que el usuario siente |
| **Tasa de recuperación del descubierto** | Si es baja, el gancho de reactivación es emisión pura |

Palancas, por orden de preferencia:

1. el **regalo de bienvenida**, que solo afecta a los nuevos y es reversible;
2. las **dos constantes del precio**, que no mueven la masa;
3. un **tope de saldo**, como freno de emergencia.
