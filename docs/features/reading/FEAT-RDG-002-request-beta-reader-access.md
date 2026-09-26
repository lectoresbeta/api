---
id: FEAT-RDG-002
title: Solicitar ser lector beta de una obra (obra `ON_REQUEST`)
context: Reading
concept: AccessRequest
actors: [Reader]
spec_status: APPROVED
impl_status: DONE
priority: P0
sources:
  - _sources/use-cases.pdf
  - docs/bounded-contexts/reading.md
  - docs/features/work/FEAT-WRK-007-configure-access-mode.md
endpoints:
  - POST /works/{workId}/access-requests
  - GET /me/access-requests
  - DELETE /access-requests/{requestId}
events: [AccessRequested]
depends_on: [FEAT-WRK-007, FEAT-RDG-003]
updated: 2026-09-24
---

# FEAT-RDG-002 — Solicitar ser lector beta

## Resumen

En una obra `ON_REQUEST`, el lector **pide permiso** y espera. Es el camino intermedio de los
tres: ni entrar sin preguntar (`PUBLIC`,
[`FEAT-RDG-001`](FEAT-RDG-001-become-beta-reader-by-correcting.md)) ni esperar a que le
inviten (`PRIVATE`, [`FEAT-RDG-004`](FEAT-RDG-004-invite-beta-reader.md)).

Una solicitud **no concede nada**. Es una pregunta con acuse de recibo, y lo único que produce
por sí sola es un aviso al autor. Quien la resuelve es él, en
[`FEAT-RDG-003`](FEAT-RDG-003-resolve-access-request.md), y las dos fichas se implementan
juntas: una solicitud que nadie puede contestar es peor que no poder pedirla.

## Por qué esta ficha existe, y por qué es `P0`

Una obra nace `ON_REQUEST` ([`FEAT-WRK-001`](../work/FEAT-WRK-001-create-work-with-editor.md)
`RN-5`), que es la modalidad que menos expone. Hoy eso significa que **publicar una obra no la
abre a nadie**: hasta que su autor la pase a `PUBLIC` a mano, solo la lee él.

Dicho de otra forma: el valor por defecto del producto es un camino que todavía no existe.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Reader` | Solicitar acceso a una obra | Cuenta activada; la obra es visible para él y está en `ON_REQUEST` |
| `Reader` | Ver sus propias solicitudes | Las suyas y solo las suyas |
| `Reader` | Cancelar una solicitud suya | Mientras siga `PENDING` |
| `Writer` | — | **No solicita acceso a su propia obra** |

## Qué necesita saber `Reading` de la obra, y cómo lo sabe

Tres cosas: que la obra existe y es visible para quien pregunta, de quién es, y en qué
modalidad está. Las pide por el contrato publicado `WorkAccessBrief`
([`decision:0015`](../../decisions/0015-work-and-reading-ask-each-other.md)).

No las proyecta. La razón de fondo no es la frescura sino la autorización: decidir que el
borrador de otra persona **no existe** es la misma regla que aplica `GET /works/{id}`, y
copiarla dentro de `Reading` sería tener la regla más peligrosa del backend escrita dos veces.

La edad se pregunta aparte, a `ReaderMaturity`, igual que hace `Feedback`: el contrato entrega
`adultsOnly` como dato y quien pregunta aplica la puerta.

## Reglas de negocio

- `RN-1` Solo se solicita sobre una obra **visible** para quien pregunta. Un borrador ajeno,
  una obra bloqueada por reclamación y una obra para adultos ante alguien sin edad declarada
  responden **lo mismo que una obra inexistente**.
- `RN-2` Solo una obra `ON_REQUEST` admite solicitudes. `PRIVATE` las rechaza
  (`bounded-contexts/reading.md` `RN-3`) y `PUBLIC` también, por el motivo contrario: ahí no
  hace falta pedir nada.
- `RN-3` **Una solicitud `PENDING` por par (lector, obra).** Pedirlo dos veces no crea dos.
- `RN-4` Quien ya es lector beta de la obra no solicita: ya está dentro.
- `RN-5` El autor no solicita acceso a su propia obra.
- `RN-6` La solicitud admite un **mensaje opcional** del lector, que el autor ve al
  resolverla. Es lo que convierte una lista de nombres en una decisión informada.
- `RN-7` El lector **cancela** su solicitud mientras siga `PENDING`. Cancelar no avisa al
  autor: retirarse de una cola no es un hecho del que nadie tenga que enterarse.
- `RN-8` **Solicitar no cuesta ni compromete créditos**, igual que conceder no los cuesta
  ([`decision:0006`](../../decisions/0006-credit-system.md)).
- `RN-9` Tras un rechazo **se puede volver a solicitar**. Ver abajo.
- `RN-10` Las solicitudes **no caducan**. Ver abajo.

## `RN-2` — por qué `PUBLIC` también se rechaza

Parece una asimetría innecesaria: si en `PUBLIC` cualquiera entra, aceptar la solicitud y
concederla sería más amable que devolver un error.

No lo sería. Concederla produciría un acceso con origen `REQUEST_APPROVED` donde el camino
documentado es `PUBLIC_JOIN`, y sobre todo **obligaría al autor a hacer algo para nada**: la
persona ya podía entrar antes de preguntar. El error correcto es el que dice «esta obra no
funciona así», y la pantalla no debería ofrecer el botón.

Es la asimetría que [`FEAT-RDG-004`](FEAT-RDG-004-invite-beta-reader.md) `RN-3` resuelve al
revés —invitar sí se permite en cualquier modalidad— y la diferencia está en quién trabaja: una
solicitud le pide trabajo al autor; una invitación es el autor decidiendo hacerlo.

## `RN-9` — volver a pedir después de un rechazo

Se permite, y es incómodo decirlo sin más: nada impide pedir lo mismo cien veces.

Se permite igualmente porque la alternativa es peor. Cerrar la puerta tras un rechazo
convierte un «ahora no» en un «nunca», y el caso normal —una obra que se rechaza porque tiene
ya diez lectores y dos meses después no— dejaría de tener salida. Un enfriamiento por tiempo
introduce un reloj y un estado nuevos para un problema que todavía no se ha visto.

Queda apuntado como `R-9`. Lo que sí hace esta ficha es abaratar la defensa del autor:
rechazar es una operación de un clic, y el lector se entera.

## `RN-10` — no caducan

Resuelve `R-2` de la ficha del contexto, en la parte que toca a las solicitudes.

Caducar exige un reloj, un estado más y un proceso que lo recorra, y lo que resolvería ya
está resuelto de otras dos formas: el lector cancela la suya y el autor rechaza las que no
quiere. Una lista larga de solicitudes viejas es un problema de pantalla —se ordena y se
pagina—, no de modelo.

Se revisará si aparece volumen: una obra popular con miles de solicitudes `PENDING` cambiaría
la respuesta, y entonces la caducidad sería una limpieza, no una regla de negocio.

## Flujo principal

1. El lector encuentra la obra en el catálogo y pide acceso, con o sin mensaje.
2. `Reading` pregunta a `Work` por la obra y a `User` por la edad.
3. Comprueba que no hay acceso vivo ni solicitud `PENDING` de esa persona sobre esa obra.
4. Crea la `AccessRequest` en `PENDING`.
5. Publica `AccessRequested`. `Notification` avisa al autor.
6. El lector ve su solicitud en `GET /me/access-requests`, como `PENDING`.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| La obra no existe, es un borrador ajeno o está bloqueada | La misma respuesta para los tres | `404 WORK_NOT_FOUND` |
| La obra es para adultos y quien pide no tiene edad declarada | Igual que si no existiera (`RN-1`) | `404 WORK_NOT_FOUND` |
| La obra es `PUBLIC` o `PRIVATE` | Se rechaza nombrando el motivo | `422 WORK_DOES_NOT_TAKE_REQUESTS` |
| Ya tiene acceso vivo | No se crea nada | `409 ALREADY_A_BETA_READER` |
| Ya tiene una solicitud `PENDING` | No se crea una segunda | `409 REQUEST_ALREADY_PENDING` |
| El autor solicita sobre su obra | Se rechaza sin fingir que no existe: la ve | `422 AUTHOR_CANNOT_BE_BETA_READER` |
| Cuenta sin activar | Regla general de escritura ([`decision:0003`](../../decisions/0003-write-operations-require-activated-account.md)) | `403 ACCOUNT_NOT_ACTIVATED` |
| Cancela una solicitud ya resuelta | No se deshace nada | `409 REQUEST_ALREADY_RESOLVED` |
| Cancela una solicitud ajena | Igual que una inexistente | `404 ACCESS_REQUEST_NOT_FOUND` |

El `409` de `RN-3` es la respuesta visible; lo que lo hace correcto bajo carrera es el
**índice único parcial** sobre las solicitudes `PENDING` que el agregado ya documenta. Dos
pulsaciones simultáneas producen una solicitud y un `409`, nunca dos solicitudes.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Solicitar acceso | `POST /api/v1/works/{workId}/access-requests` | `requestBetaReaderAccess` |
| Mis solicitudes | `GET /api/v1/me/access-requests` | `listMyBetaReaderAccessRequests` |
| Cancelar la mía | `DELETE /api/v1/access-requests/{requestId}` | `cancelBetaReaderAccessRequest` |

`GET /me/access-requests` se pagina **por cursor** y admite filtro por estado
([`paginación`](../../api/conventions/pagination.md)): es un flujo cronológico, no un catálogo
sobre el que se salte a la página 4.

Cada elemento lleva el estado, la fecha, el mensaje y lo justo de la obra para pintar una
línea —identificador y título—. El título llega por el mismo contrato que todo lo demás.

## Eventos

**Publica**

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `AccessRequested` | Se crea la solicitud | `Notification` (avisa al autor) | `accessRequestId`, `workId`, `authorId`, `readerId` |

**Sin el mensaje del lector.** El texto es suyo y puede ser largo; el aviso dice que hay una
solicitud y quien la resuelve la abre. Meter contenido de usuario en una cola que persiste y
reintenta es el mismo error que ya se evitó con el cuestionario.

Cancelar **no publica nada** (`RN-7`), y rechazar lo publica `FEAT-RDG-003`.

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `WorkDeleted` | `Work` | Cierra las solicitudes `PENDING` de esa obra |

## Efectos en créditos

Ninguno. Solicitar no es un hecho económico, y conceder tampoco lo es desde
[`decision:0006`](../../decisions/0006-credit-system.md).

## Modelo de datos afectado

El agregado `AccessRequest` **ya existe** con su tabla y su máquina de estados
(`PENDING → ACCEPTED | REJECTED | CANCELLED`). Lo que falta es todo lo demás: repositorio,
casos de uso, endpoints y el índice único parcial sobre `(work_id, requester_id)` restringido
a `PENDING`, que es lo que hace cumplir `RN-3` sin confiar en una lectura previa.

Ninguna tabla nueva.

## Criterios de aceptación

- [x] Un lector solicita acceso a una obra `ON_REQUEST` y el autor recibe el aviso.
- [x] Solicitar dos veces no crea dos solicitudes.
- [x] Una obra `PUBLIC` y una `PRIVATE` rechazan la solicitud nombrando el motivo.
- [x] El borrador de otra persona responde lo mismo que una obra inexistente.
- [x] Una obra para adultos no se distingue de una inexistente ante quien no tiene edad.
- [x] Quien ya es lector beta recibe `409` y no una segunda vía de entrada.
- [x] El autor no puede solicitar acceso a su propia obra.
- [x] El lector cancela su solicitud y desaparece de la lista del autor.
- [x] Cancelar la solicitud de otra persona responde `404`.
- [x] Tras un rechazo se puede volver a solicitar.
- [x] `Reading` no consulta ninguna tabla de `Work` en todo el proceso. *Lo comprueba Deptrac, no un test.*

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| R-9 | ¿Hace falta un enfriamiento entre un rechazo y la siguiente solicitud? | Hoy no hay ninguno (`RN-9`). Si aparece acoso, es la primera palanca |
| R-10 | ¿El autor puede cerrar su obra a nuevas solicitudes sin cambiar de modalidad? | Un «no acepto más por ahora» evitaría tener que pasar a `PRIVATE` |
| ~~R-2~~ | ¿Las solicitudes caducan? | **Resuelta: no** (`RN-10`). Se cancelan y se rechazan |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Aprobada con sus decisiones discutibles
explícitas: `RN-2` —que `PUBLIC` rechace la solicitud en lugar de concederla— y `RN-9` —que
se pueda volver a pedir sin límite—, que son las dos decisiones con consecuencias visibles
para el usuario.

**Implementación:** `DONE` (2026-09-24), junto a
[`FEAT-RDG-003`](FEAT-RDG-003-resolve-access-request.md).

**Ninguna tabla nueva ni migración**: `access_request` existía desde el andamiaje inicial, con
sus tres índices y el índice único parcial que sostiene `RN-3`. Lo que faltaba era todo lo de
arriba.

Lo que sí apareció por el camino:

- **`WorkAccessBriefs`**, el contrato de `Work` que cierra el primer ciclo entre contextos
  ([`decision:0015`](../../decisions/0015-work-and-reading-ask-each-other.md)). Devuelve por
  obra y **por lote**: una página de veinte solicitudes habría sido veinte llamadas, y un
  contrato que invita a un N+1 acaba teniendo uno;
- **`Cursor` y `PageSize` en `Shared`**, la primera paginación por cursor del proyecto. El
  cursor lleva **fecha e identificador**, y las dos cosas hacen falta: ordenar solo por
  instante hace que dos solicitudes del mismo segundo se repitan o se pierdan al pasar de
  página;
- **`WorkAccessMode` en `Reading`**, copia deliberada del vocabulario de `Work`. Qué significa
  cada modalidad **para entrar** es regla de este contexto, y una regla no se escribe sobre el
  enum de otro.

Lo único de la ficha que no se ha podido implementar es el consumo de `WorkDeleted`: **ese
evento todavía no lo publica nadie**, porque `Work` no tiene borrado de obras. El hueco que
dejaría está tapado igualmente — resolver una solicitud cuya obra ya no existe responde `410`,
porque se comprueba contra el contrato y no contra una proyección.
