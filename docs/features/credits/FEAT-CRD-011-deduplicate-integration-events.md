---
id: FEAT-CRD-011
title: Deduplicar eventos para garantizar idempotencia
context: Credits
concept: EventProcessing
actors: []
spec_status: APPROVED
impl_status: PARTIAL
priority: P0
sources:
  - docs/decisions/0006-credit-system.md
  - docs/api/conventions/concurrency-and-idempotency.md
  - conversation:2026-09-23 (rediseño del sistema de créditos)
endpoints: []
events: []
depends_on: []
updated: 2026-09-23
---

# FEAT-CRD-011 — Deduplicar eventos para garantizar idempotencia

## Resumen

RabbitMQ **no garantiza entrega única**. Un mismo evento puede llegar dos veces por un
reintento, por una confirmación perdida o por un reproceso manual del transporte de fallos.

En cualquier otro contexto eso sería una molestia. En `Credits` es corrupción: **un crédito
aplicado dos veces no se repara del todo después**, porque los movimientos son inmutables y la
corrección deja dos apuntes donde debía haber uno.

Esta funcionalidad es la garantía de que **cada hecho se aplica exactamente una vez**.

## Por qué no basta con «que el handler sea idempotente»

Es la recomendación habitual y aquí no sirve. Un abono de 10 créditos no es naturalmente
idempotente: no hay forma de mirar el saldo y saber si el evento ya se aplicó, porque el saldo
es la suma de muchos movimientos y varios pueden valer lo mismo.

Hace falta un **registro explícito de lo ya procesado**. Es el patrón *inbox*.

## Reglas de negocio

- `RN-1` Antes de aplicar cualquier efecto, el consumidor comprueba si ese evento ya se
  procesó. Si lo fue, **no hace nada y confirma el mensaje**.
- `RN-2` El registro de lo procesado se escribe **en la misma transacción** que el movimiento
  que protege.
- `RN-3` La garantía última es una **restricción de unicidad en la base de datos**, no la
  comprobación previa. La comprobación evita trabajo inútil; la restricción evita la carrera
  entre dos entregas simultáneas del mismo mensaje.
- `RN-4` La clave de deduplicación es **`(eventId, consumer)`**, no `eventId` a secas. Ver
  [abajo](#la-clave-es-eventid-consumer-no-eventid).
- `RN-5` Un duplicado **no es un error**: se confirma el mensaje, se registra a nivel `debug` y
  **no va al transporte de fallos**. Tratarlo como error llenaría la cola de fallos de mensajes
  correctos.
- `RN-6` Se guarda el `eventName` junto al identificador, para poder auditar y purgar por tipo.
- `RN-7` Las filas se conservan **180 días** y se purgan con un comando programado.
- `RN-8` La deduplicación cubre **todos** los consumidores de `Credits`, no solo los que mueven
  saldo. Un consumidor exento hoy deja de serlo en cuanto alguien le añada un efecto.

`RN-2` es la regla que no se puede relajar, y el orden importa en los dos sentidos:

| Si se escribiera… | Fallo entre medias |
|---|---|
| El registro **después** del movimiento | Una caída deja el evento sin marcar y se aplica otra vez |
| El registro **antes** del movimiento | Un *rollback* pierde el movimiento y deja el evento marcado como hecho |

Solo escribirlos **juntos o ninguno** cierra las dos puertas.

## La clave es `(eventId, consumer)`, no `eventId`

La tabla actual tiene `event_id` como clave primaria. **Es insuficiente, y conviene arreglarlo
antes de que haya datos.**

Con la clave actual, el primer consumidor que procesa un evento lo marca como hecho **para
todos los demás**. Y dentro de `Credits` hay eventos que dos reglas distintas necesitan ver:

| Evento | Consumidor | Efecto |
|---|---|---|
| Entrega de una corrección | Cargo y abono ([`FEAT-CRD-006`](FEAT-CRD-006-charge-author-for-received-feedback.md)) | Mueve el precio anotado entre dos cuentas |
| El mismo evento | Bonificación por invitación (`FEAT-CRD-005`) | +5 al invitador si es la primera corrección del invitado |

Son dos reglas **independientes**, con motivos distintos y ciclos de vida distintos. Con la
clave actual, la segunda nunca se ejecutaría.

La alternativa —un único consumidor que haga las dos cosas— se descarta: acopla reglas que no
tienen nada que ver, y un fallo en la bonificación por invitación revertiría el cargo de una
corrección, que es exactamente lo que `FEAT-CRD-006` `RN-5` quiere evitar.

**Decisión:** la clave primaria pasa a ser `(event_id, consumer)`, donde `consumer` identifica
la regla que consumió el evento, no la clase que la implementa. Un nombre estable y corto, del
estilo `welcome-grant` o `invitation-reward`: renombrar una clase no puede reabrir un evento ya
procesado.

## Cuánto se conserva

**180 días**, con purga programada (`RN-7`).

La ventana de reentrega real de RabbitMQ se mide en minutos: los reintentos configurados en el
transporte se agotan en segundos. 180 días no cubren eso —lo cubre cualquier cosa— sino el caso
lento: un mensaje que cayó al transporte de fallos, se quedó ahí semanas y alguien lo reprocesa
a mano. Pasado ese margen, reprocesar un evento antiguo es una operación deliberada que debe
fallar de forma visible, no aplicarse en silencio.

Sin purga la tabla crece con **cada evento que recibe el contexto, para siempre**. Es la clase
de crecimiento que no molesta durante dos años y luego obliga a una migración con la aplicación
parada.

## Modelo de datos afectado

`credits_ctx.processed_event`, que **ya existe** con la forma equivocada.

| Columna | Estado |
|---|---|
| `event_id` | Existe. Deja de ser clave primaria por sí sola |
| `consumer` | **Nueva.** Segunda mitad de la clave primaria |
| `event_name` | Existe |
| `processed_at` | Existe, con índice para la purga |

La migración es barata ahora —la tabla está vacía y nada la usa— y cara en cuanto haya
movimientos que dependan de ella.

## Criterios de aceptación

- [ ] Entregar dos veces el mismo evento al mismo consumidor produce **un solo** efecto.
- [ ] El mismo evento entregado a **dos consumidores distintos** produce **los dos** efectos.
- [ ] Si falla el movimiento, no queda registro de procesado (y al revés).
- [ ] Dos entregas simultáneas del mismo mensaje: una aplica el efecto y la otra falla contra
      la restricción de unicidad, sin duplicar nada.
- [ ] Un duplicado se confirma sin error y **no** aparece en el transporte de fallos.
- [ ] La purga borra las filas anteriores al plazo y ninguna posterior.
- [ ] Renombrar la clase de un consumidor no reabre eventos ya procesados.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| `C-43` | ¿Debería este mecanismo vivir en `Shared` para que lo usen otros contextos? | Ninguno ahora: `Credits` es el único con efectos irreversibles. Se extrae cuando haya un segundo caso real, no antes |

## Estado

**Especificación:** `APPROVED` (2026-09-23). `RN-4` —la clave es `(eventId, consumer)`, lo que
**obliga a cambiar una tabla ya creada**— y el plazo de retención de `RN-7` quedan validados.

**Implementación:** `PARTIAL` (2026-09-26).

Hecho: la clave es `(event_id, consumer)` —migración `Version20260923190000`—, la restricción
de unicidad la impone PostgreSQL (`RN-3`) y el primer consumidor la usa
([`FEAT-CRD-002`](FEAT-CRD-002-welcome-credit-grant.md)).

~~El **comando de purga** (`RN-7`)~~ — **hecho** (2026-09-26):
`lectoresbeta:credits:purge-processed-events`. `purgeOlderThan` llevaba desde el principio en
el repositorio sin que nada lo llamara, así que la tabla crecía sin límite.

Dos cosas que la ficha no decía y la implementación obligó a decidir:

- **el borrado va acotado.** `purgeOlderThan` recibe ahora un tope por pasada y el servicio
  repite hasta agotar. Un único `DELETE` sin límite bloquearía la tabla que **cada consumidor
  lee antes de mover un crédito**, y un fallo a la mitad desharía el trabajo ya hecho;
- **en SQL nativo**, porque DQL no sabe acotar un `DELETE`. Se selecciona por `ctid`, la
  dirección física de la fila en PostgreSQL, porque la clave es compuesta y compararla en un
  `IN` de dos columnas contra una subconsulta acotada es más caro y menos legible.

Los 180 días viven en una constante del servicio y no en la configuración: es una regla de
esta ficha, no una palanca de operación — cambiarla cambia qué significa reprocesar un evento
viejo. **Con qué se programa** sí es decisión de operación (`O-1`), y por eso el comando no
programa nada; la propuesta está en
[operaciones](../../architecture/07-observability-and-operations.md).

**Falta** comprobar el comportamiento ante un duplicado **en el transporte real** (`RN-5`):
que se confirme el mensaje y no acabe en la cola de fallos.
