# Bounded context: `Credits`

> Estado: `DRAFT` — Prefijo: `CRD` — Origen: `_sources/credit-system.pdf`
>
> **Contexto con aislamiento reforzado.** `AGENTS.md` lo declara regla dura de arquitectura.

## Responsabilidad

Mantener la economía interna que equilibra dar y recibir feedback: qué acciones generan
créditos, cuáles los consumen, cuánto valen, y cuál es el saldo de cada usuario.

Es el mecanismo central del producto: recibir feedback exige haberlo dado antes.

## Por qué está aislado

El modelo de créditos **va a cambiar mucho**. El propio documento de partida ya plantea dos
formas de calcular el coste (tabla por tramos y fórmula continua) y declara que el sistema
debe revisarse de forma constante. Aislarlo permite cambiar las reglas sin tocar ningún otro
contexto.

Consecuencias operativas, no negociables:

- ningún contexto llama a `Credits` para sumar o restar créditos;
- ningún contexto calcula cuántos créditos vale una acción;
- ningún contexto conoce el modelo interno de `Credits`;
- `Credits` no depende de entidades, repositorios ni servicios de ningún otro contexto;
- la comunicación es **siempre** por eventos de integración asíncronos.

Está explícitamente prohibido:

```text
Feedback  ──▶ CreditsService::addCredits(...)     PROHIBIDO
Reading   ──▶ CreditRepository                    PROHIBIDO
Work      ──▶ Credits\Application\...             PROHIBIDO
```

## Qué posee

- El saldo de créditos de cada usuario.
- El historial inmutable de movimientos.
- Las reglas que traducen hechos de negocio en variaciones de crédito.
- La clasificación de valor por nivel de texto.
- La deduplicación de eventos ya procesados.

## Qué NO posee

| No es suyo | Es de |
|---|---|
| Las obras y su contenido | `Work` |
| El número de palabras y el nivel del texto (los **calcula** `Work`, `Credits` los **recibe**) | `Work` |
| Los comentarios y sus valoraciones | `Feedback` |
| Las invitaciones a la plataforma | `User` |
| Los datos del usuario | `User` |

## Conceptos

| Concepto | Responsabilidad |
|---|---|
| `Account` | Saldo, saldo disponible y movimientos de un usuario |
| `Reservation` | Retenciones: ciclo de vida de los créditos comprometidos y aún no gastados |
| `Rule` | Reglas de valoración: cuánto vale cada hecho de negocio |
| `EventProcessing` | Deduplicación e idempotencia de los eventos recibidos |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `CreditAccount` | `UserId` | El saldo es siempre la suma de sus movimientos. Un movimiento nunca se modifica ni se borra. |
| `CreditReservation` | `CreditReservationId` | Transiciones `HELD → CONFIRMED` o `HELD → RELEASED`, y ninguna otra. Solo se crea si el saldo disponible la cubre por completo. |
| `ProcessedEvent` | `eventId` | Un `eventId` se aplica como máximo una vez. |

### Saldo y saldo disponible

```text
saldo            = suma de los movimientos
retenido         = suma de las retenciones en estado HELD
saldo disponible = saldo − retenido
```

**El saldo disponible es el que gobierna lo que el autor puede hacer** y el que se muestra en
la interfaz. El total permanece para la auditoría. Toda respuesta que devuelva un saldo debe
decir cuál de los dos es: un campo llamado `balance` a secas es una invitación a equivocarse.

### `CreditTransaction`

Registro inmutable. Campos: identificador, `UserId`, importe con signo, motivo
(`CreditTransactionReason`), `eventId` de origen, marca temporal y metadatos del hecho.

El saldo **no es un campo editable de forma independiente**: es la consecuencia de los
movimientos y debe poder recalcularse desde cero.

## El modelo económico

Definido en [`decision:0006`](../decisions/0006-credit-system.md). Nueve reglas.

### 1. El precio mide esfuerzo

```text
precio = techo(palabras del capítulo / 1.000) + techo(palabras exigidas / 100)
```

Dos términos —**leer** y **escribir**—, entre 2 y 20, por capítulo
([`FEAT-CRD-016`](../features/credits/FEAT-CRD-016-effort-based-pricing.md)).

Las «palabras exigidas» son la suma de los mínimos que el autor fija en sus preguntas: **un
solo número que captura toda la exigencia del cuestionario**.

### 2. Coste = recompensa

El autor paga exactamente lo que cobra el lector. **Una corrección mueve créditos; no los
crea ni los destruye.**

De ahí sale lo que gobierna todo este contexto:

```text
suma de todos los saldos (incluidos los negativos) = grifos − descubierto no recuperado
```

El promedio de créditos por usuario es **siempre** el regalo de bienvenida. Que todos tengan
demasiados créditos es imposible por construcción; el riesgo real es la **concentración**.

Y la consecuencia práctica más útil: **ajustar el precio no pone en riesgo la economía**,
porque el precio es una transferencia. Solo cambia la velocidad de circulación.

### 3. Grifos y desagües

| | Cuánto | Cuándo |
|---|---|---|
| **Bienvenida** | +10 | Al activar la cuenta |
| **Invitación** | +5 al invitador | Cuando el invitado **entrega su primera corrección**. Tope 10 por usuario |
| **Descubierto no recuperado** | Emisión | Cuando una deuda no se salda (`FEAT-CRD-019`) |

Y nada más. El total de bonificaciones por invitación nunca supera `5 × usuarios`, porque cada
usuario nuevo solo puede generarla una vez.

El momento del abono de la invitación es la decisión de diseño, no el importe: pagarla contra
**una corrección real entregada** hace que el fraude con cuentas falsas sea *peor* que el
comportamiento honesto.

### 4. Lo que no es un grifo

- La **propina** ([`FEAT-CRD-017`](../features/credits/FEAT-CRD-017-author-tip.md)) sale del
  saldo del autor. Es una transferencia, y por eso es inmune a la colusión: si dos cuentas se
  propinan mutuamente, el neto es cero.
- El **enlace público**
  ([`FEAT-FBK-008`](../features/feedback/FEAT-FBK-008-public-link-correction.md)) está
  **fuera de la economía**: ni cuesta ni recompensa. `Credits` no consume su evento.

### 5. Retención y descubierto

Se retiene **al empezar la corrección**
([`FEAT-CRD-009`](../features/credits/FEAT-CRD-009-hold-credits-on-correction-start.md)), no
al conceder el acceso. El corrector **cobra siempre**; si el autor no llega, queda en negativo
([`FEAT-CRD-018`](../features/credits/FEAT-CRD-018-negative-balance.md)).

Con saldo negativo no se reciben más correcciones, pero **sí se puede corregir**: es como se
sale del descubierto.

## Lo que este rediseño deroga

| Ya no existe | Por qué |
|---|---|
| Tramos por `TextTier` | La fórmula continua es más simple y no crea saltos injustos en los bordes |
| Recargo por preguntas sobre las tres de base | Absorbido por el segundo término del precio |
| Bonificación por feedback valorado | Era un grifo abierto a que dos cuentas se valorasen en bucle. La propina lo hace mejor |
| Margen dinámico entre coste y recompensa | Resolvía un problema —la inflación— que no existe en una transferencia pura |
| Cuenta de sistema que absorbía el margen | Sin margen, no hay nada que absorber |
| Versión de la regla en cada movimiento | Con precio fijado en la retención, basta con guardar el importe |
| Compensación entre `Reading` y `Credits` | La retención vive entera aquí y la dispara un hecho de `Feedback` |

## Eventos consumidos

| Evento | Origen | Efecto |
|---|---|---|
| `UserRegistered` | `User` | Crea `CreditAccount` **con saldo 0**. No abona nada |
| `AccountActivated` | `User` | Abona los **+10** créditos de bienvenida |
| `CorrectionStarted` | `Feedback` | **Retiene** el precio del capítulo, o lo rechaza |
| `CorrectionDraftDiscarded` | `Feedback` | **Libera** la retención |
| `FeedbackSubmitted` | `Feedback` | Confirma: **carga al autor y abona al lector** el importe retenido |
| `CorrectionTipped` | `Feedback` | Transfiere la propina del autor al lector |
| `WorkContentUpdated` | `Work` | Actualiza las palabras de cada capítulo en su read model de precios |
| `QuestionnaireUpdated` | `Work` | Actualiza las palabras exigidas. **No altera retenciones ya hechas** |
| `InvitedUserParticipated` | `User` | Abona +5 al invitador, hasta el tope de 10 |
| `UserDeleted` | `User` | Anonimiza la cuenta de créditos. Los movimientos permanecen (`C-21`) |

**`PublicCorrectionSubmitted` no se consume.** Es la forma más clara de expresar que el enlace
público está fuera de la economía.

`Credits` **decide** el efecto. Los eventos describen hechos, no instrucciones.

## Eventos publicados

| Evento | Cuándo | Posibles consumidores |
|---|---|---|
| `CreditsAdded` | Se abonan créditos | `Notification` |
| `CreditsSpent` | Se confirma una retención | `Notification` |
| `CreditsHeld` | Se retiene al empezar una corrección | **`Feedback`** (abre el panel), `Notification` |
| `CreditHoldRejected` | No hay disponible | **`Feedback`** (no abre el panel), `Notification` |
| `CreditHoldReleased` | Caduca o se descarta el borrador | `Feedback`, `Notification` |
| `CreditBalanceChanged` | Cambia el saldo o el retenido | Read models, `Notification` |
| `CreditBalanceWentNegative` | El saldo cruza a negativo | `Notification` (avisa y **explica la salida**) |
| `CreditDebtCleared` | Vuelve a cero o más | `Feedback`, `Notification` |
| `OverdraftCorrectionGranted` | Se concede un descubierto | `Feedback` (bloquea el contenido), `Notification` |
| `CorrectionUnlocked` | El autor repone saldo | `Feedback`, `Notification` |

**`CreditHoldRejected` es el único del que otro contexto depende para no dejar trabajar.**
`Feedback` debe esperarlo antes de abrir el panel de corrección, así que su publicación exige
Outbox Pattern: perderlo dejaría a un lector escribiendo sin respaldo.

Nótese que **`Credits` nunca oculta ni enseña el texto de una corrección**. En el descubierto
publica el hecho económico; quien decide qué se ve es `Feedback`, que es quien posee la
corrección.

## Idempotencia

Requisito **obligatorio**, no una buena práctica: RabbitMQ no garantiza entrega única y un
crédito aplicado dos veces corrompe la economía.

Mecanismo: antes de aplicar cualquier efecto, `Credits` registra el `eventId` en
`ProcessedEvent` dentro de la misma transacción que el movimiento. Si el `eventId` ya
existe, el evento se descarta sin efecto.

## Persistencia

| Tabla | Contenido |
|---|---|
| `credit_account` | Un registro por usuario: saldo actual y metadatos |
| `credit_transaction` | Movimientos inmutables, indexados por `user_id` y fecha |
| `processed_event` | `event_id` procesados, con marca temporal y política de purga |
| `credit_reservation` | Retenciones con su estado, importe y caducidad |
| `credit_rule` | Reglas vigentes, si se decide hacerlas configurables (`C-3`) |

## Reglas de negocio

- `RN-1` El saldo es siempre la suma de los movimientos de la cuenta.
- `RN-2` Un movimiento nunca se modifica ni se elimina. Una corrección es un movimiento nuevo.
- `RN-3` Un mismo `eventId` no produce efecto más de una vez.
- `RN-4` **Ningún contexto externo determina el importe de un movimiento.**
- `RN-5` Todo movimiento registra el hecho de negocio que lo originó y es auditable.
- `RN-6` **Lo que paga el autor y lo que cobra el lector es la misma cifra.**
- `RN-7` El importe queda fijado **al retener**, no al entregar. Quien empezó a corregir con
  unas condiciones las conserva aunque el autor cambie el cuestionario después.
- `RN-8` Los créditos de bienvenida se abonan al **activar** la cuenta, no al crearla. Una
  cuenta sin verificar nunca tiene saldo
  ([`decision:0003`](../decisions/0003-write-operations-require-activated-account.md)).
- `RN-9` **El corrector cobra siempre.** Si el autor no llega, queda en negativo.
- `RN-10` Con saldo negativo no se reciben correcciones; **sí se pueden dar**.
- `RN-11` Se cumple en todo momento la invariante contable: la suma de todos los saldos es
  igual a los grifos menos el descubierto no recuperado.

`RN-11` no es documentación: es un test que debe ejecutarse periódicamente
([`FEAT-CRD-012`](../features/credits/FEAT-CRD-012-economy-health.md)). Si se rompe, hay un
movimiento en el sistema que no es una transferencia, y eso significa que alguien tiene
créditos que nadie pagó.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~C-1~~ | ¿Qué ocurre si el autor no tiene saldo? | **Resuelta:** reserva previa. Sin saldo disponible no hay acceso. Ver [`decision:0004`](../decisions/0004-credit-reservation-on-access-grant.md) |
| ~~C-2~~ | ¿Se reservan créditos al conceder acceso? | **Resuelta:** sí |
| **C-14** | ¿Obliga el cuestionario a fijar un mínimo de palabras por pregunta? | **Sin mínimos, una novela se corregiría por 2 créditos** (`FEAT-CRD-016`) |
| **C-18** | ¿La comprobación de saldo al empezar es asíncrona o una consulta síncrona con contrato? | Entre pulsar «Empezar corrección» y poder escribir hay un viaje por la cola |
| C-16 | ¿Cuánto dura una retención antes de caducar? Propuesta: 7 días | Corta castiga al lector; larga congela el saldo del autor |
| C-17 | Al caducar la retención, ¿puede el lector recuperarla y entregar igualmente? | Sin ello se pierde trabajo real |
| C-13 | ¿Cómo se cuentan las palabras de un texto con formato enriquecido? | Debe coincidir con lo que ve el lector |
| C-15 | ¿Qué se hace con un `FeedbackSubmitted` sin retención asociada? | `FEAT-CRD-006` |
| C-3 | ¿Las cantidades son configurables en caliente o van en el código? | La bienvenida y las dos constantes del precio son las palancas del sistema |
| C-8 | ¿Existe ajuste manual por parte de la plataforma? | Requiere `MANUAL_ADJUSTMENT` y un actor `Admin` (`V-1`) |
| C-11 | ¿Qué ocurre con el saldo de una cuenta que nunca se activa? | Hoy no tiene: no se abona nada hasta activar |
| C-21 | ¿Qué pasa con una deuda si el usuario elimina su cuenta? | Anonimizar no la cobra: contablemente es emisión |
| C-27 | ¿Cuántos días de inactividad para el descubierto de reactivación? | `FEAT-CRD-019` |
| C-9 | ¿Se retiran créditos si el autor oculta un comentario por abusivo? | Ligado al control antifraude (`FEAT-FBK-012`) |

**Resueltas por [`decision:0006`](../decisions/0006-credit-system.md):**

| # | Cómo |
|---|---|
| `R-1` | La retención es **por lector y por capítulo**, al empezar la corrección |
| `C-4` | Una corrección por lector y capítulo; sí se pueden corregir varios capítulos |
| `C-5` | El enlace público **no genera ni consume** créditos |
| `C-6`, `C-7`, `C-10` | Desaparecen con el `TextTier`: el precio es continuo sobre las palabras **del capítulo** |
| `C-12` | La insignia es **lo que gana el lector**, que es lo mismo que paga el autor |
| `C-13` (antiguo) | «En corrección» es un estado de la obra (`FEAT-WRK-016`) |
| `P-1`, `P-2`, `P-3` | La fórmula existe, y coste y recompensa coinciden |
