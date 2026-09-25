# Endpoints — `Credits`

> Convenciones transversales en [`../conventions/`](../conventions/). Esquemas en `openapi/`.
>
> Estado: **tres operaciones especificadas**. El resto del contexto trabaja por eventos, no
> por HTTP.

## Resumen

| Método y ruta | `operationId` | Propósito | Funcionalidad | Estado |
|---|---|---|---|---|
| `GET /api/v1/credits/balance` | `getCreditBalance` | Saldo del usuario autenticado | FEAT-CRD-001 | **Implementado** |
| `GET /api/v1/credits/scoring` | `getCreditScoring` | Cómo se gana y cómo se gasta, **público** | FEAT-CRD-015 | **Implementado** |
| `GET /credits/transactions` | `listCreditTransactions` | Historial de movimientos | FEAT-CRD-008 | PENDING |
| `POST /api/v1/corrections/{correctionId}/tip` | `tipCorrection` | Propina a una corrección | FEAT-CRD-017 | **Implementado** |
| `GET /api/v1/admin/credits/health` | `getEconomyHealth` | Estado agregado de la economía | FEAT-CRD-012 | **Implementado** |

**La mayor parte de este contexto no tiene API.** Los créditos se mueven al recibir eventos de
integración, nunca por una llamada HTTP de otro contexto
([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md)). No existe —ni
debe existir— un endpoint para sumar o restar créditos.

---

## `GET /api/v1/credits/balance`

**`operationId`:** `getCreditBalance` · **Funcionalidad:** [`FEAT-CRD-001`](../../features/credits/FEAT-CRD-001-check-credit-balance.md)

### Propósito

Devolver el saldo de créditos del usuario autenticado.

### Autorización

Cualquier usuario autenticado, **solo su propio saldo**. La ruta no lleva identificador: el
usuario sale del token, de modo que no hay ninguna comprobación de pertenencia que se pueda
olvidar.

No existe variante para consultar el saldo ajeno. La vista agregada de la economía es
[`FEAT-CRD-012`](../../features/credits/FEAT-CRD-012-economy-health.md) y trabaja con totales.

**Una cuenta sin activar puede llamarla.** La restricción de
[`FEAT-USR-025`](../../features/user/FEAT-USR-025-block-writes-until-activation.md) afecta a
las escrituras; esto es una lectura, y la cabecera de la aplicación la necesita desde el primer
momento.

### Reglas aplicadas

`RN-1` a `RN-4` de [`FEAT-CRD-001`](../../features/credits/FEAT-CRD-001-check-credit-balance.md).

### Respuesta

`200 OK`.

```json
{ "balance": 10 }
```

Un **único** entero con signo. No hay «saldo disponible» frente a «saldo total» porque el
sistema no retiene créditos ([`decision:0006`](../../decisions/0006-credit-system.md) §3).

Dos consecuencias que el cliente debe respetar:

- **`balance` puede ser negativo** ([`FEAT-CRD-018`](../../features/credits/FEAT-CRD-018-negative-balance.md)).
  Un cliente que lo trate como natural mostrará cifras equivocadas justo en el caso en que el
  usuario más necesita entender lo que pasa.
- **Un usuario sin movimientos devuelve `0`, no `404`.** Es el caso normal de una cuenta
  recién registrada y aún sin activar.

`Cache-Control: no-store`. El saldo cambia por hechos ajenos a quien lo consulta —alguien
entrega una corrección sobre su obra— así que una respuesta cacheada es una respuesta falsa.

### Errores específicos

Ninguno propio. `401` sin autenticar, según [`../conventions/errors.md`](../conventions/errors.md).

**No hay `404`.**

### Efectos

Ninguno. Es una lectura: no publica eventos ni mueve créditos.

---

## `POST /api/v1/corrections/{correctionId}/tip`

**`operationId`:** `tipCorrection` · **Funcionalidad:** [`FEAT-CRD-017`](../../features/credits/FEAT-CRD-017-author-tip.md)

### Propósito

Que el autor dé créditos **de su propio saldo** a una corrección que le ha servido.

Sustituye a la bonificación automática por «feedback valorado positivamente» y la mejora en
dos frentes: **no crea créditos** —lo que sale del autor entra en el corrector, masa
constante— y **cuesta algo a quien lo da**, que es lo que la convierte en una señal de
calidad. Dos cuentas que se valoran mutuamente no ganan nada, así que el fraude no tiene
premio que repartir.

### Por qué esta operación vive aquí

Es **la única llamada HTTP que mueve créditos**, y conviene decir por qué no contradice
[`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md): lo que esa
decisión prohíbe es que **otro contexto** pida a `Credits` que sume o reste. Aquí quien atiende
la petición es `Credits`, y decide con lo que ya tiene apuntado. Nadie le dice cuánto mover.

La alternativa —el endpoint en `Feedback`, que es donde vive la corrección— obligaría a
preguntar el saldo disponible a `Credits` antes de aceptar, y este contexto no publica
contratos. Aceptar la intención y aplicarla después por un evento obligaría a compensar cuando
el saldo no llegara, que es mucha maquinaria para un gesto voluntario.

No hace falta preguntar nada porque **`Credits` ya sabe de quién es cada corrección**: tiene
apuntado quién pagó (`CORRECTION_CHARGED`) y quién cobró (`CORRECTION_EARNED`). El autor es el
del cargo; el corrector, el del abono.

### Autorización

El **autor de la obra**, y solo sobre correcciones que ha recibido y pagado (`RN-2`).

La pertenencia no se comprueba preguntando a `Feedback`: se deduce del cargo. Si quien llama no
es quien pagó, la respuesta es la misma que si la corrección no existiera.

### Petición

```json
{ "amount": 3 }
```

`amount` es un entero **entre 1 y 5** (`RN-3`). Por encima de cinco la propina se parece al
precio de una corrección y deja de leerse como un extra.

Admite `Idempotency-Key`. Es un movimiento de créditos, y un reintento de red no debe leerse
como un segundo gesto: repetir la petición con la misma llave devuelve `200` sin mover nada.
Repetirla sin llave, o con otra, es una segunda propina y se rechaza.

La llave se guarda **en el propio movimiento**, no en una tabla de respuestas: basta porque una
corrección admite una sola propina, así que el movimiento duplicado no puede existir y lo único
que la llave decide es cómo se lee la repetición.

### Respuesta

`200 OK`.

```json
{ "balance": 5 }
```

El saldo que le queda al autor. Quien acaba de dar créditos quiere verlo, y pedirlo aparte
sería una segunda llamada para un dato que esta ya conoce.

### Reglas aplicadas

`RN-1` a `RN-7` de [`FEAT-CRD-017`](../../features/credits/FEAT-CRD-017-author-tip.md).

La que más manda es `RN-1`: **la propina sale del disponible y no genera descubierto**. El
descubierto de [`FEAT-CRD-018`](../../features/credits/FEAT-CRD-018-negative-balance.md) existe
para que un lector nunca trabaje sin cobrar; endeudarse por ser generoso sería una trampa.

### Errores específicos

| Código HTTP | `code` | Cuándo |
|---|---|---|
| `404` | `CORRECTION_NOT_FOUND` | No existe, no es suya, o no se ha cobrado |
| `409` | `INSUFFICIENT_CREDITS` | No llega el disponible. Lleva `balance` |
| `409` | `ALREADY_TIPPED` | Una por corrección (`RN-4`), y es irrevocable (`RN-5`) |
| `422` | `TIP_OUT_OF_RANGE` | Fuera de 1–5. Lleva `minimumTip` y `maximumTip` |

**El `404` responde a tres situaciones distintas a propósito.** Distinguir «no existe» de «no es
tuya» diría algo sobre las correcciones de otra persona. La ficha preveía `403` para el tercer
caso; se unificó por eso.

Ahí cae también la corrección **por enlace público** (`RN-6`): no costó nada y no pagó a nadie,
así que `Credits` no tiene ningún cargo apuntado sobre ella y responde como ante una ajena. La
regla se cumple sin necesidad de un código propio que la nombre.

### Efectos

Dos movimientos del mismo importe, `TIP_SENT` al autor y `TIP_RECEIVED` al corrector, ambos con
el `correctionId` en sus metadatos. Masa constante.

Publica `CorrectionTipped`, que consumen `Feedback` —para que la corrección muestre que fue
propinada— y `Community`, para la reputación del corrector. Es lo que permite que la propina
alimente un ranking **sin que `Credits` sepa nada de rankings**.

---

## `GET /api/v1/admin/credits/health`

**`operationId`:** `getEconomyHealth` · **Funcionalidad:** [`FEAT-CRD-012`](../../features/credits/FEAT-CRD-012-economy-health.md)

### Propósito

Decir si el sistema de créditos está funcionando y cuál de las palancas hay que mover.

Un sistema de créditos sin medición se descubre roto por las quejas, y las dos formas de
morir —nadie tiene créditos, o los créditos no valen nada— tardan semanas en manifestarse y
son caras de revertir, porque para entonces los saldos ya están formados.

### No confundir con `GET /health`

Aquel es público, lo consultan sondas sin credenciales y responde en milisegundos. Este suma
sobre todo el histórico de movimientos y cuenta cómo está repartida la economía.

Las dos diferencias importan: **una sonda no debe contarle a un desconocido que el sistema de
créditos está roto**, y una consulta que recorre todos los movimientos no es lo que se pregunta
cada diez segundos.

### Autorización

El backoffice, por la regla general de `^/api/v1/admin`.

**Nada identifica a nadie** (`RN-2`): son cifras agregadas. El saldo más bajo del sistema es
un número, no una persona.

### La invariante

```text
lo que emitieron los grifos == la suma de todos los movimientos == la suma de los saldos
```

**Es un test, no una aspiración.** Si deja de cumplirse, hay un movimiento que no es una
transferencia, y eso significa que alguien tiene créditos que nadie pagó.

Son tres cifras y no dos, y la tercera es la que vale:

| Qué falla | Qué significa |
|---|---|
| `issued` ≠ `moved` | Hay un movimiento que cobra sin pagar, o paga sin cobrar |
| `moved` ≠ `balances` | Un saldo se ha desviado de su propia historia |

La segunda comparación solo dice algo porque **las dos cifras llegan por caminos
independientes**: el saldo se guarda, no se calcula sumando los movimientos. Si se calculase,
coincidiría siempre y no comprobaría nada.

**El descubierto no aparece en la fórmula**, al contrario de lo que decía la ficha. Los saldos
negativos ya están dentro de la suma; restarlos otra vez haría fallar la igualdad justo cuando
alguien está endeudado, que es un estado normal y previsto.

### Además del endpoint

`php bin/console credits:check-invariant` hace la misma comprobación desde consola, registra a
nivel `error` y sale con código distinto de cero si falla. Son las dos formas de que el fallo
se vea sin que nadie esté mirando: la alerta del sistema de monitorización y el trabajo
programado en rojo.

**Cada cuánto se ejecuta es decisión de operación.** Programarlo desde el código ataría la
frecuencia a un despliegue.

### Respuesta

`200 OK`, con `Cache-Control: no-store`: es una foto de ahora mismo, y quien la lee está
mirando si algo acaba de cambiar.

`from` y `to` acotan **solo las cifras de periodo**, que hoy son los ajustes manuales. La
invariante y el reparto de saldos son del instante.

`alerts` dice qué umbral se ha cruzado. Los umbrales viven en `config/services.yaml` porque son
lo primero que habrá que mover en cuanto haya datos reales, y mover un umbral no debería ser un
despliegue. **Sin umbral, una métrica es decorativa**: dice una cifra y no dice si está bien.

`overdraft.recoveryRate` es **nulo** mientras no se haya concedido ningún descubierto. Un cero
diría que ninguno se recupera, que es una afirmación sobre datos que no existen.

### Errores específicos

Ninguno propio. `401` sin autenticar y `403` sin el rol.

### Efectos

Ninguno. Es una lectura: no publica eventos ni mueve créditos.

---

## `GET /api/v1/credits/scoring`

**`operationId`:** `getCreditScoring` · **Funcionalidad:** [`FEAT-CRD-015`](../../features/credits/FEAT-CRD-015-credit-scoring-screen.md)

### Propósito

Las reglas de la economía de créditos, con las cifras vigentes. Es el destino del botón «Ver
puntuación de créditos» del modal de
[`FEAT-CRD-014`](../../features/credits/FEAT-CRD-014-credits-info-modal.md).

### Autorización

**Pública.** Es de los pocos sitios de esta API donde eso es correcto: no hay nada de nadie
aquí. Son las reglas de la casa, y quien se está planteando registrarse tiene derecho a
leerlas antes. Se cachea en público.

### Semántica

**Las cifras salen de las mismas palancas de configuración que usa el motor de precios**, por
inyección, y el ejemplo lo calcula `ChapterPricing`, la misma clase que cobra. Escribirlas a
mano habría sido más corto y habría garantizado que un día la pantalla y el cobro dijeran
cosas distintas — y quien lo descubre es alguien que esperaba cobrar otra cosa.

`chargedOn: FEEDBACK_DELIVERED` resuelve la contradicción que `FEAT-CRD-014` había registrado:
la maqueta sugería que los créditos se gastan al poner la obra en corrección, y no es así.

`correctionPaysWhatItCosts` declara una **ausencia**: no hay una cifra de «lo que se gana
corrigiendo» porque una corrección mueve créditos en vez de crearlos. El autor paga
exactamente lo que el corrector cobra.

No consulta el saldo de nadie: es la explicación de las reglas, no un estado.
