---
id: FEAT-CRD-006
title: Cargar créditos al autor por comentario recibido
context: Credits
concept: Account
actors: []
spec_status: DRAFT
impl_status: BLOCKED
priority: P0
sources:
  - _sources/credit-system.pdf#p1
  - _sources/credit-system.pdf#p2
  - _sources/credit-system.pdf#p4
endpoints: []
events: [FeedbackSubmitted, CreditsSpent, InsufficientCredits]
depends_on: [FEAT-FBK-001, FEAT-WRK-013, FEAT-CRD-011]
updated: 2026-09-21
---

# FEAT-CRD-006 — Cargar créditos al autor por comentario recibido

## Resumen

Cuando un lector beta envía feedback sobre una obra, su autor **paga créditos**. Es el
mecanismo que hace que recibir crítica exija haberla dado antes, y por tanto el núcleo
económico del producto.

`Credits` aplica el cargo al recibir el evento `FeedbackSubmitted`. Ningún otro contexto
calcula el importe ni ordena el cargo.

## Actores y autorización

No hay actor humano. Es una reacción del sistema a un hecho de negocio.

## Precondiciones

- Existe una `CreditAccount` para el autor de la obra.
- Se ha recibido `FeedbackSubmitted` con `authorId`, `textTier` y `questionCount`.
- El `eventId` no ha sido procesado antes (`FEAT-CRD-011`).

## Reglas de negocio

- `RN-1` El coste se calcula así:

  ```text
  coste = créditos(textTier) + max(0, questionCount − 3)
  ```

- `RN-2` Los créditos por nivel de texto son los de la tabla de
  [`credits.md`](../../bounded-contexts/credits.md#clasificación-por-extensión-texttier).
- `RN-3` Las tres primeras preguntas del cuestionario no tienen coste. Cada pregunta
  adicional suma 1 crédito **por cada comentario recibido**, no una sola vez.
- `RN-4` El cargo genera un `CreditTransaction` con motivo `FEEDBACK_RECEIVED`, importe
  negativo y referencia al `eventId` de origen.
- `RN-5` El mismo `eventId` nunca produce dos cargos.
- `RN-6` `textTier` y `questionCount` se toman **del evento**, que refleja el estado en el
  momento del envío del comentario. `Credits` no consulta a `Work`.
- `RN-7` El movimiento es inmutable. Una corrección es un movimiento nuevo, nunca una
  modificación del anterior.

## Ejemplos de cálculo

| Texto | Palabras | `TextTier` | Preguntas | Coste |
|---|---|---|---|---|
| Relato corto | 2.400 | `SHORT_STORY` (15) | 3 | **15** |
| Relato corto | 2.400 | `SHORT_STORY` (15) | 6 | **18** |
| Micro cuento | 380 | `MICRO_STORY` (5) | 3 | **5** |
| Relato medio | 3.004 | `MEDIUM_TALE` (40) | 3 | **40** |
| Relato corto | 2.999 | `SHORT_STORY` (15) | 3 | **15** |

Las dos últimas filas ilustran el salto entre tramos que motiva la alternativa de cálculo
continuo (`FEAT-CRD-010`): cinco palabras de diferencia multiplican el coste por más de dos.

## Flujo principal

1. `Feedback` publica `FeedbackSubmitted`.
2. `Credits` recibe el evento.
3. Comprueba si el `eventId` ya fue procesado. Si lo fue, descarta sin efecto.
4. Calcula el coste según `RN-1`.
5. Aplica el cargo a la cuenta del autor y registra el `eventId` en la misma transacción.
6. Publica `CreditsSpent` y `CreditBalanceChanged`.

## El problema sin resolver

**El paso 5 no está definido cuando el autor no tiene saldo suficiente.**

El documento de origen establece que recibir un comentario descuenta créditos, pero no dice
qué ocurre si no los hay. Es la decisión de producto más importante pendiente, porque
condiciona el diseño de dos bounded contexts y el recorrido principal del producto.

### Opciones

| | Opción A — Post-pago | Opción B — Reserva previa | Opción C — Validación previa |
|---|---|---|---|
| **Cómo funciona** | El comentario se entrega siempre; el saldo puede quedar negativo | Al conceder acceso a un LB se reservan créditos; al enviar el comentario se confirma | `Feedback` consulta el saldo antes de aceptar el comentario |
| **Experiencia del lector** | Nunca pierde su trabajo | Nunca pierde su trabajo: si no hay reserva, no hay acceso | Puede escribir un comentario y que se rechace al enviarlo |
| **Experiencia del autor** | Puede quedar en negativo y bloqueado | No recibe accesos que no puede pagar | Recibe accesos que luego no puede pagar |
| **Acoplamiento** | Ninguno | Ninguno, pero añade ciclo de vida de reserva | Rompe el desacoplo entre `Feedback` y `Credits` |
| **Complejidad** | Baja | Alta: reservas, expiración, compensación | Media, y con carrera entre consulta y gasto |
| **Riesgo** | Saldo negativo sin límite | Créditos inmovilizados en accesos que no comentan | Sigue sin garantizar el cobro |

**Recomendación:** opción **B**, con la opción **A** como paso intermedio para una primera
versión. B es la única que protege a la vez el trabajo del lector y el saldo del autor, y
lo hace **en el momento correcto**: el acceso es el compromiso, no el comentario. A es
aceptable al principio si se acota el saldo negativo.

La opción C debe descartarse: rompe el aislamiento de `Credits` y ni siquiera resuelve el
problema, porque entre consultar el saldo y gastarlo puede llegar otro cargo.

Esta decisión debe cerrarse en un ADR antes de implementar `Feedback` o `Credits`.

## Eventos

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `FeedbackSubmitted` | `Feedback` | Calcula y aplica el cargo |

**Publica**

| Evento | Cuándo | Consumidores |
|---|---|---|
| `CreditsSpent` | Cargo aplicado | `Notification` |
| `CreditBalanceChanged` | Cambia el saldo | Read models, `Notification` |
| `InsufficientCredits` | El cargo no se puede aplicar (según lo que se decida) | `Feedback`, `Notification` |

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `credit_transaction` | Nuevo movimiento negativo con `reason = FEEDBACK_RECEIVED` |
| `credit_account` | Actualización del saldo |
| `processed_event` | Registro del `eventId` |

Los tres cambios ocurren en **una única transacción**. Si no, una entrega duplicada puede
cobrar dos veces.

## Criterios de aceptación

- [ ] Recibir `FeedbackSubmitted` con `textTier: SHORT_STORY` y `questionCount: 3` carga 15 créditos al autor.
- [ ] Con `questionCount: 6` carga 18 créditos.
- [ ] Con `questionCount: 1` carga 15 créditos: menos de tres preguntas no abarata.
- [ ] Procesar dos veces el mismo `eventId` produce un único cargo.
- [ ] El movimiento registra el `eventId` de origen y es auditable.
- [ ] El saldo resultante coincide con la suma de todos los movimientos de la cuenta.
- [ ] El cargo y el registro del `eventId` son atómicos.
- [ ] `Credits` no realiza ninguna llamada a `Work` ni a `Feedback` durante el proceso.
- [ ] *(Pendiente de `C-1`)* Comportamiento definido cuando el saldo es insuficiente.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| C-1 | **¿Qué ocurre si el autor no tiene saldo suficiente?** | **Bloqueante** |
| C-4 | ¿Un mismo lector beta puede comentar varias veces y cobrar cada vez? | Vector de abuso |
| C-6 | ¿Cuánto cuesta un texto de más de 75.000 palabras? | Tramo no cubierto |
| C-7 | ¿El `textTier` se congela al conceder el acceso o se toma al enviar el comentario? | Un autor podría ampliar el texto tras conceder accesos |
| C-9 | ¿Se devuelven créditos si el autor oculta el comentario por abusivo? | Protección frente a feedback malicioso |
| C-10 | ¿El nivel se calcula sobre la obra completa o sobre el fragmento comentado? | Con novelas cambia radicalmente el coste |

## Estado

**Especificación:** `DRAFT`. El cálculo está definido; el caso de saldo insuficiente no.

**Implementación:** `BLOCKED` por `C-1`. No se empieza a implementar hasta que exista el
ADR correspondiente: elegir mal aquí obliga a rehacer dos bounded contexts.
