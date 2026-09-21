# Concurrencia e idempotencia

## Reintentos seguros

Una petición que falla por red puede haberse ejecutado. Para las operaciones que no son
naturalmente idempotentes, el cliente envía una clave:

```text
Idempotency-Key: <uuid generado por el cliente>
```

Con la misma clave, el servidor devuelve el resultado original en lugar de ejecutar otra vez.

Operaciones donde importa especialmente:

| Operación | Por qué |
|---|---|
| Enviar feedback | Mueve créditos en dos cuentas |
| Solicitar acceso de LB | Evita solicitudes duplicadas |
| Crear una obra | Evita obras duplicadas por doble clic |
| Enviar un mensaje directo | Evita mensajes repetidos |

Pendiente: cuánto tiempo se conserva la clave y dónde se almacena.

## Edición concurrente

Dos dispositivos editando la misma obra pueden pisarse el trabajo. Escribir sobre el texto de
alguien sin avisar es una pérdida de datos, no un conflicto técnico menor.

**Propuesta: control optimista con `ETag`.**

```http
GET /works/{workId}
ETag: "v7"

PUT /works/{workId}
If-Match: "v7"
```

Si la versión ha cambiado, la respuesta es `409 Conflict` con `code: STALE_VERSION`.

Aplicable a: contenido de obras y fragmentos, cuestionarios, y configuración de acceso.

Pendiente (`Q-5` de `FEAT-WRK-001`): si el editor guarda automáticamente, el control
optimista por sí solo no basta y hace falta una estrategia de guardado incremental.

## Idempotencia de los consumidores de eventos

Distinta de la anterior y **obligatoria**: RabbitMQ no garantiza entrega única.

Todo handler debe poder procesar el mismo evento dos veces sin efecto adicional. En
`Credits` esto se implementa registrando el `eventId` procesado en la misma transacción que
el movimiento. Ver [`../../architecture/04-cross-context-communication.md`](../../architecture/04-cross-context-communication.md).
