# Comunicación entre contextos

> Mecanismo por defecto: **evento de integración asíncrono** sobre Symfony Messenger y
> RabbitMQ. Las llamadas síncronas entre contextos son la excepción y deben justificarse.

## Cadena

```text
Acción del usuario
    ▼
Caso de uso (Application)
    ▼
Evento de dominio (Domain)               ← no conoce el transporte
    ▼
Evento de integración (contrato público) ← payload estable y mínimo
    ▼
Symfony Messenger (Infrastructure)
    ▼
RabbitMQ
    ▼
Handler en el contexto consumidor
```

`Domain` emite eventos de dominio. La traducción a evento de integración, su serialización,
su enrutado y sus reintentos son **exclusivamente** responsabilidad de `Infrastructure`.

## Qué publica un evento

Un hecho **ya ocurrido**, descrito en el lenguaje del contexto que lo emite.

Correcto:

```text
FeedbackSubmitted
ReadingCompleted
WorkPublished
InvitedUserParticipated
```

Incorrecto:

```text
AddTenCreditsToUser
UpdateCommunityStatistics
SendNotificationNow
```

El consumidor decide qué significa el hecho dentro de su propio modelo. `Feedback` no sabe
cuántos créditos vale un comentario; solo sabe que se ha enviado uno.

## Contenido del payload

- Solo los datos **estables** que el consumidor necesita.
- Nunca un agregado serializado ni una entidad de Doctrine.
- Nunca contenido de la obra ni datos sensibles.
- Siempre: identificador único del evento, tipo, versión, marca temporal y el identificador
  del agregado de origen.

Envoltorio común:

```json
{
  "eventId": "01J8...",
  "eventType": "FeedbackSubmitted",
  "eventVersion": 1,
  "occurredAt": "2026-09-21T10:15:30Z",
  "payload": { }
}
```

El catálogo completo está en [`../events/README.md`](../events/README.md).

## Fiabilidad

| Problema | Decisión |
|---|---|
| Entrega duplicada | Se asume. **Todos los handlers deben ser idempotentes.** |
| Deduplicación | `Credits` registra cada `eventId` procesado antes de aplicar el efecto. Ningún evento aplica dos veces el mismo movimiento. |
| Fallo del handler | Reintentos con retardo creciente y transporte de fallos (`failed`), configurados en `Infrastructure`. |
| Atomicidad entre base de datos y publicación | **Outbox Pattern** cuando la pérdida del evento sea inaceptable. Obligatorio para los eventos que afectan a créditos. |
| Orden de los mensajes | No se garantiza. El diseño no puede depender del orden de llegada. |
| Evolución del contrato | Campos nuevos opcionales. Un cambio incompatible crea una versión nueva del evento y ambas conviven durante la migración. |

## Cuándo una consulta síncrona es aceptable

Solo si el caso de uso **no puede completarse sin el dato en ese instante**. Entonces:

- se define un contrato explícito de consulta en el contexto proveedor;
- devuelve DTO o escalares, nunca entidades;
- el consumidor depende de la interfaz del contrato, no de la implementación;
- se documenta como excepción en un ADR.

Compartir un repositorio de Doctrine no es una opción en ningún caso.

## El coste del feedback: reserva previa

Cuando un lector beta envía feedback, el autor de la obra paga créditos. Es un flujo entre
`Feedback`, `Reading` y `Credits`, tres contextos deliberadamente desacoplados.

**Resuelto en [`decision:0004`](../decisions/0004-credit-reservation-on-access-grant.md):** el
coste se **retiene al conceder el acceso** y se **confirma al recibir el comentario**.

```text
Reading: concede acceso
     │ BetaReaderAccessGranted
     ▼
Credits: ¿saldo disponible?
     │
     ├── sí ──▶ retención HELD ──▶ CreditsReserved
     │                                   │
     │                          Feedback: se envía el comentario
     │                                   │ FeedbackSubmitted
     │                                   ▼
     │                          Credits: retención CONFIRMED
     │                                   └──▶ CreditsSpent
     │
     └── no ──▶ CreditReservationRejected ──▶ Reading: revoca el acceso
```

Consecuencias para la comunicación entre contextos:

- **Aparece una compensación.** `Reading` actúa antes de saber si `Credits` puede respaldarlo,
  y debe poder deshacerlo. Es el primer saga del sistema.
- **`Reading` mantiene una proyección** del saldo disponible de cada autor, alimentada por
  `CreditBalanceChanged`, para no conceder accesos condenados al fracaso. Es una optimización,
  **no una garantía**: la compensación sigue siendo obligatoria.
- **`CreditReservationRejected` exige Outbox Pattern.** Es el único evento del que depende
  otro contexto para deshacer algo; perderlo deja un acceso sin respaldo.
- **`Feedback` no consulta saldos.** Que exista un acceso vigente ya implica que los créditos
  están retenidos.

Este flujo es el ejemplo de referencia del proyecto para cualquier operación que deba
comprometer recursos de otro contexto: **reservar, confirmar, liberar**, con compensación
ante el rechazo.

## Nomenclatura de colas

Propuesta, pendiente de confirmar al configurar RabbitMQ:

```text
exchange:  lectores_beta.events           (topic)
routing:   <context>.<EventName>          p. ej. feedback.FeedbackSubmitted
cola:      <consumer_context>.<purpose>   p. ej. credits.feedback_events
cola dlq:  <cola>.failed
```
