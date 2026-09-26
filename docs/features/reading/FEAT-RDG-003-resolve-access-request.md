---
id: FEAT-RDG-003
title: Aceptar o rechazar una solicitud de lector beta
context: Reading
concept: AccessRequest
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P0
sources:
  - _sources/use-cases.pdf
  - docs/bounded-contexts/reading.md
  - docs/features/reading/FEAT-RDG-002-request-beta-reader-access.md
endpoints:
  - GET /works/{workId}/access-requests
  - PUT /access-requests/{requestId}/resolution
events: [BetaReaderAccessGranted, AccessRequestRejected]
depends_on: [FEAT-RDG-002]
updated: 2026-09-24
---

# FEAT-RDG-003 — Resolver una solicitud de lector beta

## Resumen

El autor ve quién quiere leer su obra y decide. Aceptar **concede el acceso en el acto**;
rechazar cierra la solicitud y se lo dice al lector.

Es la otra mitad de [`FEAT-RDG-002`](FEAT-RDG-002-request-beta-reader-access.md) y la única
de las cuatro fichas del acceso que **crea un `BetaReaderAccess`** por decisión de una
persona.

## La decisión de diseño: aceptar concede, y lo hace aquí mismo

Los dos agregados implicados —`AccessRequest` y `BetaReaderAccess`— son de `Reading`. Así que
aceptar **cierra la solicitud y concede el acceso en la misma transacción**, sin evento de por
medio.

Merece decirse porque va contra la costumbre del proyecto. En todo lo demás, un hecho de un
contexto llega a otro por RabbitMQ y cada uno decide qué significa; aquí no hay frontera que
cruzar, y meter una cola entre dos tablas del mismo contexto solo añadiría una ventana en la
que la solicitud está aceptada y el acceso todavía no existe.

La regla es la de `AGENTS.md`: **una transacción es un caso de uso sobre una frontera de
consistencia**. Estos dos agregados están dentro de la misma.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Writer` | Ver las solicitudes de su obra | Es el autor. Otro recibe `404` |
| `Writer` | Aceptar o rechazar una | La solicitud es de su obra y sigue `PENDING` |
| `Reader` | — | No resuelve nada, ni la suya |

## Reglas de negocio

- `RN-1` **Solo el autor de la obra resuelve** (`bounded-contexts/reading.md` `RN-4`). Para
  cualquier otro, la solicitud no existe.
- `RN-2` Aceptar crea un `BetaReaderAccess` con origen `REQUEST_APPROVED`, **en la misma
  transacción** que cierra la solicitud.
- `RN-3` Una solicitud ya resuelta no se vuelve a resolver. Aceptar lo que ya se rechazó
  exige una solicitud nueva.
- `RN-4` Si el lector **ya tiene acceso vivo** por otro camino, aceptar cierra la solicitud y
  **no crea un segundo acceso**: la invariante de uno vivo por par manda sobre la operación.
- `RN-5` Rechazar **no da motivo**. Ver abajo.
- `RN-6` Resolver **no cuesta ni compromete créditos**
  ([`decision:0006`](../../decisions/0006-credit-system.md)).
- `RN-7` El acceso concedido aquí **no se revoca al descartar un borrador**: eso solo le pasa
  a lo que nació por `PUBLIC_JOIN` ([`FEAT-RDG-001`](FEAT-RDG-001-become-beta-reader-by-correcting.md)
  `RN-5`).
- `RN-8` Aceptar sobre una obra que ya no existe responde `410`. Ver abajo.
- `RN-9` La modalidad de la obra **no se comprueba al resolver**. Ver abajo.

## `RN-5` — rechazar sin explicar

Un campo de motivo parece una cortesía y sería una trampa: obligaría al autor a justificar por
escrito cada «no», y la salida fácil ante esa fricción es no rechazar nunca y dejar la
solicitud pendiente para siempre. El lector recibe entonces lo peor de las dos opciones — ni
acceso ni respuesta.

Rechazar tiene que ser de un clic. El lector se entera de que es un no, que es la información
que necesita para buscar otra obra.

## `RN-8` — la obra desapareció mientras tanto

Una solicitud puede sobrevivir a su obra: el autor la borra y quedan solicitudes apuntando a
algo que no está. `WorkDeleted` las cierra ([`FEAT-RDG-002`](FEAT-RDG-002-request-beta-reader-access.md)),
pero el aviso es asíncrono y alguien puede llegar antes.

La respuesta es `410 Gone` y no `404`: quien resuelve es el autor, sabe perfectamente que esa
obra existió, y fingir que no es confundirle sobre algo que hizo él mismo.

## `RN-9` — por qué no se mira la modalidad al resolver

Entre la solicitud y su respuesta el autor pudo cambiar la obra de modalidad. Aceptar la
solicitud de todas formas es lo correcto, y por el mismo motivo que `RN-3` de
[`FEAT-WRK-007`](../work/FEAT-WRK-007-configure-access-mode.md): **la modalidad gobierna quién
puede entrar a partir de ahora, no quién ya estaba pidiendo entrar.**

Si pasó a `PUBLIC`, esa persona podía entrar sola de todos modos. Si pasó a `PRIVATE`, el
autor tiene delante la solicitud y puede rechazarla: no hace falta que el sistema decida por
él lo que él está mirando.

## Flujo principal

1. El autor abre las solicitudes de su obra.
2. Elige una y responde `ACCEPTED` o `REJECTED`.
3. `Reading` comprueba que es suya y que sigue `PENDING`.
4. **Si acepta:** cierra la solicitud, crea el `BetaReaderAccess` y publica
   `BetaReaderAccessGranted`. El lector recibe el aviso y a partir de ese momento lee la obra
   sea cual sea la modalidad ([`FEAT-WRK-004`](../work/FEAT-WRK-004-read-a-work.md), quinta
   puerta).
5. **Si rechaza:** cierra la solicitud y publica `AccessRequestRejected`.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| La solicitud no existe o es de otra obra | La misma respuesta | `404 ACCESS_REQUEST_NOT_FOUND` |
| Quien resuelve no es el autor | Igual que si no existiera | `404 ACCESS_REQUEST_NOT_FOUND` |
| Ya estaba aceptada, rechazada o cancelada | No se deshace nada | `409 REQUEST_ALREADY_RESOLVED` |
| Una decisión que no es `ACCEPTED` ni `REJECTED` | No se ignora en silencio | `422 UNKNOWN_DECISION` |
| El lector ya tenía acceso vivo | La solicitud se cierra; no hay segundo acceso (`RN-4`) | `200` |
| La obra ya no existe | `410` y no `404` (`RN-8`) | `410 WORK_GONE` |
| Cuenta sin activar | Regla general de escritura | `403 ACCOUNT_NOT_ACTIVATED` |

El `409` es la respuesta visible. Por debajo, el agregado **es tolerante**: `resolve()` no
hace nada si la solicitud ya estaba cerrada, y esa tolerancia es lo que permite reentregar un
mensaje sin romper nada. Las dos cosas conviven a propósito — una acción humana repetida
merece un aviso, un mensaje repetido merece silencio.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Solicitudes de mi obra | `GET /api/v1/works/{workId}/access-requests` | `listWorkAccessRequests` |
| Resolver una | `PUT /api/v1/access-requests/{requestId}/resolution` | `resolveBetaReaderAccessRequest` |

Un `PUT` con la decisión, y no dos endpoints `accept` y `reject`: es **una** transición de
estado con dos valores posibles, igual que `PUT /works/{id}/status`. Dos rutas para el mismo
cambio obligan a mantener dos veces las mismas comprobaciones.

`GET` se pagina por cursor y filtra por estado, con `PENDING` por defecto: lo que el autor
abre es la bandeja, no el histórico. Cada elemento lleva quién pide, cuándo y **su mensaje**
—que es lo que hace posible decidir— además de lo público del solicitante.

## Eventos

**Publica**

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `BetaReaderAccessGranted` | Se acepta | `Notification` | `accessId`, `workId`, `authorId`, `readerId`, `grantedVia`, `grantedAt` |
| `AccessRequestRejected` | Se rechaza | `Notification` | `accessRequestId`, `readerId` |

`grantedVia` vale aquí `REQUEST_APPROVED`, que es lo que permite responder meses después por
qué esta persona entró ([`eventos`](../../events/README.md)).

**`Credits` no consume ninguno de los dos.** Conceder acceso no mueve ni compromete créditos
desde [`decision:0006`](../../decisions/0006-credit-system.md).

**Consume**

Nada propio. `WorkDeleted` lo consume
[`FEAT-RDG-002`](FEAT-RDG-002-request-beta-reader-access.md) para las dos fichas.

## Modelo de datos afectado

Ninguna tabla nueva: `access_request` y `beta_reader_access` existen. Lo que aparece es la
primera escritura del contexto que **toca dos agregados a la vez**, y el índice único parcial
sobre los accesos vivos es lo que sostiene `RN-4` si dos caminos llegan a la vez.

## Criterios de aceptación

- [x] El autor acepta una solicitud y el lector queda como lector beta de la obra.
- [x] Tras aceptar, el lector lee la obra aunque sea `PRIVATE`.
- [x] El autor rechaza y el lector se entera; no queda acceso ninguno.
- [x] Resolver dos veces la misma solicitud responde `409`.
- [x] Resolver la solicitud de la obra de otra persona responde `404`.
- [x] Aceptar a quien ya tenía acceso cierra la solicitud sin crear un segundo acceso.
- [ ] Aceptar sobre una obra borrada responde `410`. *El código está y se ejecuta en cada aceptación; no hay forma de provocarlo todavía porque `Work` no tiene borrado de obras.*
- [x] Una decisión desconocida responde `422` y no se interpreta como rechazo.
- [x] El acceso concedido así **no** se revoca al descartar un borrador. *Lo garantiza el modelo: solo se deshace lo que vino por `PUBLIC_JOIN`, y el test que lo comprueba está del otro lado, en `FEAT-RDG-001`.*
- [x] Aceptar y conceder ocurren en la misma transacción: no hay estado intermedio observable.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| R-11 | ¿Puede el autor aceptar varias solicitudes de una vez? | Con volumen, resolver de una en una es tedioso. Es una operación por lotes, no un modelo distinto |
| R-12 | ¿Debería el autor ver algo del solicitante además de su perfil público —obras corregidas, valoraciones— para decidir? | Depende de `Community` y de `FEAT-RDG-006` |
| R-1 | ¿El autor puede revocar un acceso ya concedido? | **Resuelta: sí**, en [`FEAT-RDG-010`](FEAT-RDG-010-revoke-beta-reader-access.md) |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Aprobada con sus decisiones discutibles
explícitas: `RN-5` —rechazar sin motivo— y `RN-9` —aceptar aunque la obra haya cambiado de
modalidad entretanto—.

**Implementación:** `DONE` (2026-09-24), junto a
[`FEAT-RDG-002`](FEAT-RDG-002-request-beta-reader-access.md): las dos son el mismo camino.

Sin tabla nueva y sin migración. Lo que esta mitad estrena es **la primera escritura del
proyecto que cambia dos agregados a la vez**, y conviene que quede dicho dónde está el límite:
se puede porque los dos son de `Reading`. En cuanto uno de ellos fuese de otro contexto,
volvería a ser un evento.

El caso que más cuesta ver y que el test cubre: aceptar a quien **ya** tenía acceso por otro
camino cierra la solicitud y no crea un segundo acceso. La invariante de uno vivo por par
manda sobre la operación, y el índice único la haría cumplir aunque el código se olvidara.
