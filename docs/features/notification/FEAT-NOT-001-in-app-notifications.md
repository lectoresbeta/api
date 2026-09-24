---
id: FEAT-NOT-001
title: Entregar notificaciones in-app
context: Notification
concept: Delivery
actors: []
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - docs/bounded-contexts/notification.md
  - conversation:2026-09-24
endpoints: []
events: []
depends_on: [FEAT-NOT-008]
updated: 2026-09-24
---

# FEAT-NOT-001 — Entregar notificaciones in-app

## Resumen

Convertir en avisos los hechos que ya publican los demás contextos. **Sin esto el sistema es
silencioso**: alguien solicita leer una obra y su autor no se entera hasta que entra a
mirar; alguien pierde el acceso a una obra que estaba corrigiendo y nunca sabe por qué.

Esta ficha cubre **el canal in-app**: la notificación que se guarda y se lee dentro de la
aplicación. El correo es [`FEAT-NOT-002`](../README.md) y no está aquí; el centro de
notificaciones —la pantalla— es [`FEAT-NOT-009`](FEAT-NOT-009-notification-centre.md).

## La deuda que salda

Dos funcionalidades terminaron con la misma frase escrita en su ficha:

- [`FEAT-COM-034`](../community/FEAT-COM-034-block-user.md): quien estaba corrigiendo pierde
  su trabajo al ser bloqueado, **y la ficha pedía avisarle**;
- [`FEAT-RDG-010`](../reading/FEAT-RDG-010-revoke-beta-reader-access.md) `R-13`: lo mismo al
  revocarle el acceso.

Descubrir que un texto ha dejado de poder entregarse, sin explicación, es mucho peor que que
te lo digan. Esta ficha lo arregla para el canal que no depende de nada externo.

## Qué se avisa, y a quién

El catálogo de tipos ya existe (`NotificationKind`). Lo que esta ficha decide es **cuáles se
conectan ahora**, que son los hechos que hoy se publican de verdad:

| Hecho | Tipo | Destinatario | Por qué le importa |
|---|---|---|---|
| `AccessRequested` | `ACCESS_REQUESTED` | El autor | Hay algo que resolver, y nadie más puede |
| `BetaReaderAccessGranted` | `ACCESS_REQUEST_RESOLVED` | El lector | Ya puede leer la obra que pidió |
| `AccessRequestRejected` | `ACCESS_REQUEST_RESOLVED` | El lector | Estaba esperando respuesta |
| `BetaReaderInvited` | `BETA_READER_INVITATION` | El invitado | Le han ofrecido una obra |
| `BetaReaderAccessRevoked` | `BETA_READER_ACCESS_REVOKED` | El lector | **Dejó de poder leer, y de poder entregar** |
| `FeedbackSubmitted` | `CORRECTION_RECEIVED` | El autor | Alguien ha corregido su obra |

`BETA_READER_ACCESS_REVOKED` es un tipo nuevo. No estaba en el catálogo porque cuando se
escribió, retirar un acceso no existía.

Lo que **no** se conecta todavía, y no por olvido: los hechos de `Credits`, que son muchos y
de grano fino —cada movimiento— y merecen su propia decisión sobre agrupación (`N-2`); y todo
lo de `Community` y `Feedback` que aún no existe como código.

## Reglas de negocio

- `RN-1` Un aviso tiene **un destinatario, un tipo y un origen trazable**. Sin las tres cosas
  no se guarda.
- `RN-2` La entrega es **idempotente**: el mismo hecho, reentregado por RabbitMQ, no produce
  dos avisos iguales. Lo garantiza el índice único `(destinatario, tipo, evento de origen)`,
  no solo la comprobación previa.
- `RN-3` **Nadie se avisa a sí mismo.** Un hecho que provoca el propio destinatario no genera
  aviso: el autor que se autoinvita, o quien se revoca a sí mismo, ya sabe lo que ha hecho.
- `RN-4` El aviso **no lleva contenido**: ni texto de obra, ni de corrección, ni de mensaje.
  Lleva lo justo para construir una frase y un enlace.
- `RN-5` El `payload` es una **instantánea**: el nombre y el título que había al ocurrir el
  hecho. Si después cambian, el aviso antiguo conserva los de entonces, que es lo correcto —
  describe algo que pasó.
- `RN-6` Un hecho cuyo destinatario no se puede resolver **se descarta sin error**. Una cuenta
  eliminada no recibe avisos, y reintentar no la va a resucitar.
- `RN-7` Los avisos in-app **no se filtran por preferencias todavía** (`N-1` sigue abierta:
  está por decidir si el canal in-app se puede desactivar). Cuando se decida, se aplica aquí.
- `RN-8` `Notification` **no pregunta a nadie si un hecho ocurrió**: reacciona a lo que le
  llega. Sí consulta contratos publicados para **poner nombre** a lo que ya sabe.

## Por qué el aviso guarda nombres y no solo identificadores

Un aviso tiene que poder pintarse: «Ana quiere leer *La ciudad de los pájaros*». Con solo
identificadores, cada fila de la bandeja obligaría al cliente a dos peticiones más, y una
bandeja de veinte avisos serían cuarenta.

Así que al crear el aviso se resuelven el nombre de quien lo provoca y el título de la obra,
por los **contratos publicados** de `User` y `Work`, y se guardan en el `payload`. Es una
instantánea a propósito (`RN-5`).

Que esto sea legítimo y la consulta desde `CheckAuthorAudience` no lo fuera tiene una razón
concreta: **un consumidor de eventos no es un contrato respondiendo**. La regla 4 de
[`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md) prohíbe que un
contrato llame a otro mientras responde; aquí no hay nadie esperando una respuesta.

## Flujo

```text
Reading: AccessRequested
      │
      ▼  cola
Notification
      ├── ¿ya existe un aviso para este hecho y esta persona?  → no hace nada
      ├── pide a `User` el nombre de quien solicita
      ├── pide a `Work` el título de la obra
      └── guarda el aviso, sin leer
```

## Criterios de aceptación

- [x] Solicitar acceso avisa al autor, y no al solicitante.
- [x] Resolver la solicitud avisa al solicitante, en los dos sentidos.
- [x] Invitar avisa al invitado.
- [x] Revocar un acceso avisa a quien lo pierde.
- [x] Bloquear a alguien que tenía acceso también le avisa, por el mismo camino.
- [x] Entregar una corrección avisa al autor de la obra.
- [x] El mismo hecho entregado dos veces no crea dos avisos.
- [x] El aviso lleva el nombre de quien lo provoca y el título de la obra.
- [x] El aviso no lleva texto de la obra ni de la corrección.
- [x] Nadie recibe un aviso por algo que ha hecho él mismo.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| N-1 | ¿Se pueden desactivar las notificaciones in-app, o solo el email? | Mientras no se decida, el canal in-app entrega siempre (`RN-7`) |
| N-2 | ¿Se agrupan los avisos de grano fino —los de créditos— o se manda uno por movimiento? | Por eso `Credits` no se conecta todavía |
| N-6 | ¿Qué pasa con los avisos de una cuenta eliminada? | Hoy se descartan al crearse (`RN-6`); los ya creados siguen ahí |

## Estado

**Especificación:** `APPROVED` (2026-09-24). El modelo —`Notification`, `NotificationKind`,
la idempotencia por evento de origen— ya estaba escrito desde `FEAT-NOT-008`; lo que esta
ficha añade es qué hechos se escuchan y qué lleva cada aviso.

**Implementación:** `DONE` (2026-09-24). Seis hechos escuchados, cinco tipos de aviso.

Una decisión de implementación que la ficha no anticipaba: **`BetaReaderAccessGranted` llega
por tres caminos y solo uno merece aviso**. Aprobar una solicitud lo concede otra persona;
aceptar una invitación y empezar a corregir una obra pública los inicia el propio lector, y
avisarle ahí sería contarle lo que acaba de hacer. `RN-3` no lo filtra solo, porque el actor
del hecho es el autor en los tres: lo que distingue es `grantedVia`, y de ahí
`BetaReaderAccessGranted::causedByTheReader()`. La pregunta se hace en negativo —todo lo que
no sea una solicitud aprobada lo inició el lector— para que un camino nuevo de `Reading` no
genere avisos sin que nadie lo haya decidido.

`AccessRequestRejected` sí lleva la obra, al contrario de lo que se supuso al escribir la
ficha: el hecho la transporta, y sin ella «tu solicitud ha sido rechazada» obliga a abrir cada
aviso de la bandeja para saber de qué habla. Lo que no lleva es quién decidió, porque el hecho
tampoco lo transporta — y a propósito.
