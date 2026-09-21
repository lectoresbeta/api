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
| `Account` | Saldo y movimientos de un usuario |
| `Rule` | Reglas de valoración: cuánto vale cada hecho de negocio |
| `EventProcessing` | Deduplicación e idempotencia de los eventos recibidos |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `CreditAccount` | `UserId` | El saldo es siempre la suma de sus movimientos. Un movimiento nunca se modifica ni se borra. |
| `ProcessedEvent` | `eventId` | Un `eventId` se aplica como máximo una vez. |

### `CreditTransaction`

Registro inmutable. Campos: identificador, `UserId`, importe con signo, motivo
(`CreditTransactionReason`), `eventId` de origen, marca temporal y metadatos del hecho.

El saldo **no es un campo editable de forma independiente**: es la consecuencia de los
movimientos y debe poder recalcularse desde cero.

## Reglas de crédito

### Cuantificación general

| Hecho de negocio | Efecto | Motivo (`reason`) |
|---|---|---|
| **Activar** la cuenta (no crearla) | **+20** | `ACCOUNT_ACTIVATED` |
| Dar feedback de un texto | **+** según nivel del texto | `FEEDBACK_GIVEN` |
| Que tu feedback reciba una valoración positiva | **+5** | `FEEDBACK_RATED_POSITIVELY` |
| Invitar a un usuario y que participe | **+5** | `INVITED_USER_PARTICIPATED` |
| Recibir un comentario sobre un texto propio | **−** según nivel del texto | `FEEDBACK_RECEIVED` |
| Pregunta adicional del cuestionario (más allá de 3) | **−1** adicional por pregunta y por comentario recibido | incluido en `FEEDBACK_RECEIVED` |

### Clasificación por extensión (`TextTier`)

| Nivel (producto) | `TextTier` | Extensión | Créditos |
|---|---|---|---|
| Micro cuento | `MICRO_STORY` | 1 – 500 palabras | 5 |
| Micro relato | `BRIEF_TALE` | 501 – 1.000 | 10 |
| Relato corto | `SHORT_STORY` | 1.001 – 3.000 | 15 |
| Relato medio | `MEDIUM_TALE` | 3.001 – 5.000 | 40 |
| Relato largo | `LONG_TALE` | 5.001 – 10.000 | 60 |
| Micro novela | `MICRO_NOVEL` | 10.001 – 20.000 | 100 |
| Novela corta | `SHORT_NOVEL` | 20.001 – 50.000 | 200 |
| Novela media | `MEDIUM_NOVEL` | 50.001 – 75.000 | 500 |

> A partir de 10.000 palabras se recomienda dividir el texto en fragmentos más pequeños.
>
> **Nota de trazabilidad:** el documento de origen escribe "20.0001" en el tramo de novela
> corta; se interpreta como 20.001, continuidad del tramo anterior.
>
> **Sin definir:** qué ocurre por encima de 75.000 palabras.

### Alternativa de cálculo continuo

El documento de partida plantea sustituir los tramos por una fórmula continua sobre el
número exacto de palabras, para evitar el salto brusco entre tramos (un texto de 3.004
palabras cuesta 40 créditos y uno de 2.999 cuesta 15).

Registrado como `FEAT-CRD-010`, estado `DEFERRED`. La fórmula concreta no está definida.
El diseño del dominio debe permitir sustituir la estrategia de cálculo **sin rediseñar el
contexto**: la regla vive tras una abstracción, no repartida en condicionales.

### Coste del cuestionario

Por defecto el cuestionario que acompaña al texto tiene **3 preguntas sin coste adicional**.
Cada pregunta añadida por encima de tres suma **+1 crédito al coste de cada comentario
recibido**.

Coste de recibir un comentario:

```text
coste = créditos(TextTier) + max(0, númeroDePreguntas − 3)
```

## Eventos consumidos

| Evento | Origen | Efecto |
|---|---|---|
| `UserRegistered` | `User` | Crea `CreditAccount` **con saldo 0**. No abona nada |
| `AccountActivated` | `User` | Abona los **+20** créditos de bienvenida |
| `FeedbackSubmitted` | `Feedback` | Abona al autor del comentario según `TextTier`; carga al autor de la obra el coste correspondiente |
| `FeedbackRatedPositively` | `Feedback` | Abona +5 a quien escribió el comentario |
| `InvitedUserParticipated` | `User` | Abona +5 al invitador |
| `UserDeleted` | `User` | Cierra la cuenta de créditos según la política de retención (`V-4`) |

`Credits` **decide** el efecto. Los eventos describen hechos, no instrucciones.

## Eventos publicados

| Evento | Cuándo | Posibles consumidores |
|---|---|---|
| `CreditsAdded` | Se abonan créditos | `Notification` |
| `CreditsSpent` | Se consumen créditos | `Notification`, `Feedback` |
| `CreditBalanceChanged` | Cualquier variación del saldo | Read models, `Notification` |
| `InsufficientCredits` | Un hecho no se pudo cobrar por falta de saldo | `Feedback`, `Notification` |

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
| `credit_rule` | Reglas vigentes, si se decide hacerlas configurables (`C-3`) |

## Reglas de negocio

- `RN-1` El saldo es siempre la suma de los movimientos de la cuenta.
- `RN-2` Un movimiento nunca se modifica ni se elimina. Una corrección es un movimiento nuevo.
- `RN-3` Un mismo `eventId` no produce efecto más de una vez.
- `RN-4` Ningún contexto externo determina el importe de un movimiento.
- `RN-5` Todo movimiento registra el hecho de negocio que lo originó y es auditable.
- `RN-6` El coste de recibir un comentario se calcula con el nivel del texto y el número de
  preguntas del cuestionario **en el momento en que se envía el comentario**.
- `RN-7` Los créditos de bienvenida se abonan al **activar** la cuenta, no al crearla. Una
  cuenta sin verificar nunca tiene saldo. Ver
  [`decision:0003`](../decisions/0003-write-operations-require-activated-account.md).

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **C-1** | **¿Qué ocurre si el autor no tiene saldo para recibir un comentario?** ¿Se bloquea el envío del feedback, se entrega y el saldo queda negativo, o se retiene el comentario hasta que haya saldo? | **Bloqueante.** Define el flujo entre `Feedback` y `Credits` y el recorrido J-1 completo |
| C-2 | ¿Se reservan créditos al conceder acceso a un lector beta? | Opción B de [04-cross-context-communication.md](../architecture/04-cross-context-communication.md) |
| C-3 | ¿Las cantidades son configurables en caliente o van en el código? | Afecta a la auditoría: hay que saber qué regla se aplicó en cada movimiento |
| C-4 | ¿Se puede comentar la misma obra varias veces y cobrar cada vez? | Vector de abuso directo (`D-2`) |
| C-5 | ¿El feedback desde enlace público (sin sesión) genera o consume créditos? | Falta una cuenta de créditos del comentarista (`A-3`) |
| C-6 | ¿Qué coste tiene un texto de más de 75.000 palabras? | Tramo no cubierto por la tabla |
| C-7 | ¿Cuándo se fija el `TextTier`: al crear la obra, al recibir cada comentario, o se congela al conceder el acceso? | Un autor podría ampliar el texto tras recibir accesos |
| C-8 | ¿Existe ajuste manual por parte de la plataforma? | Requiere `MANUAL_ADJUSTMENT` y un actor `Admin` (`V-1`) |
| C-11 | ¿Qué ocurre con el saldo de una cuenta que nunca se activa? | Hoy no tiene: no se abona nada hasta activar |
| C-9 | ¿Se retiran créditos si el autor oculta un comentario por abusivo? | Protección frente a feedback de baja calidad |
| C-10 | ¿El nivel se calcula sobre la obra completa o sobre el fragmento comentado? | Con novelas por fragmentos cambia radicalmente el coste (`D-1`) |
