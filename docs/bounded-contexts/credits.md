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
| `Account` | Saldo y movimientos de un usuario. **Un solo saldo**, que puede ser negativo |
| `Pricing` | El precio de cada capítulo y el precio anotado de cada corrección en curso |
| `Overdraft` | Cupo periódico de correcciones en descubierto y selección de candidatos. La elegibilidad concedida y su uso son **dos estados distintos**: el cupo limita a cuánta gente se le abre la puerta, no cuánta deuda aparece |
| `Referral` | Quién trajo a quién, y si ya se pagó por ello. **Es una proyección, no una copia de la invitación**: `User` sabe de correos, tokens y reenvíos; aquí solo hace falta el par (`FEAT-CRD-005`) |
| `EventProcessing` | Deduplicación e idempotencia de los eventos recibidos |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `CreditAccount` | `UserId` | El saldo es siempre la suma de sus movimientos. Un movimiento nunca se modifica ni se borra. **Admite valores negativos.** |
| `ProcessedEvent` | `eventId` | Un `eventId` se aplica como máximo una vez. |
| `OverdraftGrant` | `OverdraftGrantId` | Uno por autor. Caduca con su periodo y no se acumula. **Concedido y usado son estados distintos**, y solo lo usado cuenta en la tasa de recuperación |
| `Referral` | `inviteeId` | A cada persona la trae alguien **una sola vez y para siempre**, y se paga como mucho una vez por par. La identidad es el invitado, no la invitación: si fuese la invitación, dos invitaciones a la misma persona darían dos recompensas por un alta |

### Un solo saldo

```text
saldo = suma de los movimientos
```

Y nada más. **No hay «saldo disponible»**, porque el sistema no retiene créditos
([`decision:0006`](../decisions/0006-credit-system.md)): lo que el autor ve es lo que tiene.

Que sea un solo número tiene una consecuencia práctica en toda la API: un campo `balance` ya
no es ambiguo, y ninguna pantalla necesita explicar por qué dos cifras difieren.

**El saldo puede ser negativo**, lo que obliga a un detalle fácil de olvidar: ninguna
restricción de base de datos ni ningún tipo sin signo puede impedirlo.

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

**No se retiene nada.** Un capítulo admite correcciones mientras el saldo del autor cubra su
precio, y el cargo ocurre al entregarse
([`FEAT-CRD-009`](../features/credits/FEAT-CRD-009-balance-check-on-correction-start.md)).

La comprobación es **orientativa**: dos lectores pueden empezar a la vez sobre un saldo que
solo cubre a uno, y los dos cobrarán. El corrector **cobra siempre**; el autor queda en
negativo y la corrección llega **bloqueada** —ve que existe, no su contenido— hasta que
reponga ([`FEAT-CRD-018`](../features/credits/FEAT-CRD-018-negative-balance.md)).

Con saldo negativo no se abren correcciones nuevas, pero **sí se puede corregir**: es como se
sale del descubierto.

Esa misma regla es la que hace que el gancho de reactivación
([`FEAT-CRD-019`](../features/credits/FEAT-CRD-019-overdraft-correction.md)) no añada lógica:
solo provoca a propósito lo que ya ocurre por carrera.

## Lo que este rediseño deroga

| Ya no existe | Por qué |
|---|---|
| Tramos por `TextTier` | La fórmula continua es más simple y no crea saltos injustos en los bordes |
| Recargo por preguntas sobre las tres de base | Absorbido por el segundo término del precio |
| Bonificación por feedback valorado | Era un grifo abierto a que dos cuentas se valorasen en bucle. La propina lo hace mejor |
| Margen dinámico entre coste y recompensa | Resolvía un problema —la inflación— que no existe en una transferencia pura |
| Cuenta de sistema que absorbía el margen | Sin margen, no hay nada que absorber |
| Versión de la regla en cada movimiento | Con el precio anotado al empezar, basta con guardar el importe |
| Compensación entre `Reading` y `Credits` | No hay nada reservado que compensar |
| Retenciones, saldo disponible y estados `HELD`/`CONFIRMED`/`RELEASED` | **No se retiene nada.** Hay un solo saldo y ningún estado intermedio |

## Eventos consumidos

| Evento | Origen | Efecto |
|---|---|---|
| `UserRegistered` | `User` | Crea `CreditAccount` **con saldo 0**. No abona nada |
| `AccountActivated` | `User` | Abona los **+10** créditos de bienvenida |
| `CorrectionStarted` | `Feedback` | **Anota** el precio de esa corrección. No mueve saldo |
| `CorrectionDraftDiscarded` | `Feedback` | Descarta la anotación. Nada que liberar |
| `FeedbackSubmitted` | `Feedback` | **Carga al autor y abona al lector** el importe anotado. El saldo puede quedar negativo. **Y, si quien corrige fue invitado por alguien, abona +5 a quien le invitó**, la primera vez y hasta diez por invitador (`FEAT-CRD-005`) |
| `ChapterContentUpdated` | `Work` | Actualiza las palabras de ese capítulo en su read model de precios |
| `QuestionnaireUpdated` | `Work` | Actualiza las palabras exigidas. **No altera precios ya anotados** |
| `PlatformInvitationConsumed` | `User` | **Apunta el par invitador/invitado y no abona nada.** Pagar aquí valdría lo que cuesta un correo desechable (`FEAT-CRD-005`) |
| `UserDeleted` | `User` | Anonimiza la cuenta de créditos. Los movimientos permanecen (`C-21`) |
| `CreditAdjustmentOrdered` | `Moderation` | Aplica un ajuste manual como `MANUAL_ADJUSTMENT`, que es un **grifo** y no una transferencia (`FEAT-MOD-005` `RN-4`) |

**`PublicCorrectionSubmitted` no se consume.** Es la forma más clara de expresar que el enlace
público está fuera de la economía.

`Credits` **decide** el efecto. Los eventos describen hechos, no instrucciones.

## Eventos publicados

| Evento | Cuándo | Posibles consumidores |
|---|---|---|
| `CreditsAdded` | Se abonan créditos | `Notification` |
| `CreditsSpent` | Se carga al autor una corrección recibida | `Notification` |
| `ChapterCorrectabilityChanged` | Un capítulo pasa a ser corregible o deja de serlo | **`Feedback`**, `Work` (insignia). **Sin importes** |
| `ChapterPriceChanged` | Cambia lo que vale corregir un capítulo | **`Work`** (insignia del catálogo), `Community` |
| `CreditBalanceChanged` | Cambia el saldo | Read models, `Notification` |
| `CreditBalanceWentNegative` | El saldo cruza a negativo | `Notification` (avisa y **explica la salida**) |
| `CreditDebtCleared` | Vuelve a cero o más | `Feedback`, `Notification` |
| `OverdraftCorrectionGranted` | Alguien **ha corregido** un capítulo que su autor no podía pagar | `Notification` (el correo gancho) |
| `CorrectionTipped` | El autor propina una corrección recibida | `Feedback` (la marca como propinada), `Community` (reputación del corrector), `Notification` |

**Ningún contexto espera a `Credits` para dejar trabajar.** Como no hay nada que reservar,
`Feedback` abre el panel de corrección contra su propia proyección de
`ChapterCorrectabilityChanged`. Que vaya ligeramente retrasada no importa: el peor caso es el
descubierto, que ya está aceptado.

Ese evento lleva **un booleano, no un importe**: `Feedback` no debe conocer saldos ajenos.

El único que lleva un importe es `ChapterPriceChanged`, y es un **precio, nunca un saldo**:
la insignia del catálogo (`FEAT-CRD-013`) enseña lo que gana quien corrija, y esa cifra tiene
que salir de aquí porque `RN-1` prohíbe que la calcule nadie más. Publicar lo que `Credits`
ya ha decidido no es lo que `decision:0002` prohíbe; lo prohibido es que otro lo calcule.

Nótese que **`Credits` nunca oculta ni enseña el texto de una corrección**. En el descubierto
publica el hecho económico; quien decide qué se ve es `Feedback`, que es quien posee la
corrección. Y lo hace desde `CreditBalanceWentNegative`, no desde
`OverdraftCorrectionGranted`: dos hechos que bloqueen la misma corrección son dos verdades
sobre lo mismo.

`Credits` **consume** además un hecho de `User`, `ReactivationOfferChoiceChanged`, y es el
único que consume de ese contexto. Es la forma de que la renuncia al gancho de reactivación
llegue aquí sin que este contexto pregunte nada (`FEAT-CRD-019` `RN-2d`, `RN-8`).

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
| `processed_event` | `(event_id, consumer)` procesados, con marca temporal y política de purga |
| `correction_price` | La cotización de cada corrección en curso. **No es una retención**: no participa en el saldo (`FEAT-CRD-009`) |
| `chapter_price` | Read model del precio de cada capítulo, y de si admite correcciones ahora mismo (`FEAT-CRD-016`, `FEAT-CRD-009`). La corregibilidad se guarda para poder publicar **solo los cambios** |
| `work_questionnaire_demand` | Read model de lo que exige el cuestionario vigente de cada obra. Los dos hechos que forman un precio llegan por separado y hay que conservar el primero (`FEAT-CRD-016`) |
| `overdraft_grant` | Descubiertos concedidos y su cupo semanal (`FEAT-CRD-019`) |
| `referral` | El par invitador/invitado y si ya se cobró (`FEAT-CRD-005`). **Sin clave ajena** a `user_ctx.platform_invitation`: una clave ajena entre esquemas de contextos distintos convertiría en obligatorio el acoplamiento que la arquitectura prohíbe |
| `credit_rule` | Reglas vigentes, si se decide hacerlas configurables (`C-3`). **Todavía no existe** |

## Reglas de negocio

- `RN-1` El saldo es siempre la suma de los movimientos de la cuenta.
- `RN-2` Un movimiento nunca se modifica ni se elimina. Una corrección es un movimiento nuevo.
- `RN-3` Un mismo `eventId` no produce efecto más de una vez.
- `RN-4` **Ningún contexto externo determina el importe de un movimiento.**
- `RN-5` Todo movimiento registra el hecho de negocio que lo originó y es auditable.
- `RN-6` **Lo que paga el autor y lo que cobra el lector es la misma cifra.**
- `RN-7` El importe queda fijado **al empezar la corrección**, no al entregar. Quien empezó a
  corregir con unas condiciones las conserva aunque el autor cambie el cuestionario después.
- `RN-7b` **Nunca se apartan créditos.** En ningún momento existe un saldo distinto de la
  suma de los movimientos.
- `RN-8` Los créditos de bienvenida se abonan al **activar** la cuenta, no al crearla. Una
  cuenta sin verificar nunca tiene saldo
  ([`decision:0003`](../decisions/0003-write-operations-require-activated-account.md)).
- `RN-9` **El corrector cobra siempre.** Si el autor no llega, queda en negativo.
- `RN-10` Con saldo negativo no se abren correcciones nuevas; **sí se pueden dar**.
- `RN-10b` Una corrección que deja el saldo en negativo **llega bloqueada**: el autor ve sus
  metadatos, no su contenido, hasta que reponga.
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
| C-44 | ¿Son 25 palabras el suelo adecuado para una pregunta sin mínimo? | Con 25, diez preguntas sin mínimo suman 3 créditos de escritura |
| C-39 | ¿Se avisa al autor de que alguien ha empezado a corregirle? | Le permitiría reponer saldo y evitar que la corrección llegue bloqueada |
| C-41 | ¿Cuántas correcciones simultáneas admite un capítulo? | Es la palanca para acotar el descubierto por carrera **sin apartar créditos** |
| ~~C-42~~ | ¿Qué cupo de descubierto y con qué periodicidad? | **Resuelta:** 3 por semana, en `app.overdraft.weekly_quota`. A cero apaga el mecanismo entero |
| ~~C-13~~ | ¿Cómo se cuentan las palabras de un texto con formato enriquecido? | **Resuelta** en [`FEAT-WRK-013`](../features/work/FEAT-WRK-013-word-count-and-reading-time.md): sobre el texto plano derivado del HTML ya saneado, con las etiquetas de bloque separando palabras, y una palabra es lo que separa un espacio. La regla vive en `Work`, nunca aquí |
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
| `R-1` | **No hay retención.** Se comprueba el saldo al empezar y se cobra al entregar |
| `C-14` | Una pregunta sin mínimo declarado cuenta como **10 palabras** |
| `C-16`, `C-17`, `C-18` | Desaparecen con la retención: no hay plazo que caducar ni respuesta que esperar |
| `C-27` | El descubierto se concede **por cupo periódico**, no por plazo de cada usuario |
| `C-4` | Una corrección por lector y capítulo; sí se pueden corregir varios capítulos |
| `C-5` | El enlace público **no genera ni consume** créditos |
| `C-6`, `C-7`, `C-10` | Desaparecen con el `TextTier`: el precio es continuo sobre las palabras **del capítulo** |
| `C-12` | La insignia es **lo que gana el lector**, que es lo mismo que paga el autor |
| `C-13` (antiguo) | «En corrección» es un estado de la obra (`FEAT-WRK-016`) |
| `P-1`, `P-2`, `P-3` | La fórmula existe, y coste y recompensa coinciden |
