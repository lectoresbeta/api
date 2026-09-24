# Bounded context: `Reading`

> Estado: `DRAFT` — Prefijo: `RDG`

## Responsabilidad

Quién puede leer qué, y cómo llegó a poder hacerlo. Gestiona el ciclo de vida del acceso de
lector beta y los vínculos entre usuarios que lo habilitan.

## Qué posee

- Accesos de lector beta (`BetaReaderAccess`).
- Solicitudes de acceso y su aprobación o rechazo.
- Invitaciones del autor a usuarios concretos.
- Grupos de lectores beta gestionados por el autor.
- Vínculos de writing buddy.
- El **descarte** de la búsqueda de lectores beta: quién ya está dentro de una obra. El índice
  de personas es de `User` ([`FEAT-RDG-006`](../features/reading/FEAT-RDG-006-find-beta-readers.md)).

## Qué NO posee

| No es suyo | Es de |
|---|---|
| El contenido de la obra | `Work` |
| La modalidad de acceso configurada en la obra (la **consume**) | `Work` |
| Los comentarios que deja el lector beta | `Feedback` |
| Las publicaciones "busco LB" del muro | `Community` |
| Los datos del usuario | `User` |

## Conceptos

| Concepto | Responsabilidad |
|---|---|
| `Access` | Concesión y revocación del acceso de lector beta |
| `AccessRequest` | Solicitudes del lector y su resolución |
| `AccessInvitation` | Invitaciones del autor |
| `BetaReaderGroup` | Agrupación de lectores beta |
| `WritingBuddy` | Vínculo recíproco entre dos usuarios |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `BetaReaderAccess` | `BetaReaderAccessId` | Un único acceso vigente por par (usuario, obra). El autor no es lector beta de su propia obra. |
| `AccessRequest` | `AccessRequestId` | Una solicitud pendiente por par (usuario, obra). Estados: `PENDING → ACCEPTED \| REJECTED \| CANCELLED`. |
| `AccessInvitation` | `AccessInvitationId` | Solo el autor de la obra puede emitirla. |
| `BetaReaderGroup` | `BetaReaderGroupId` | Pertenece a un autor. |
| `WritingBuddyLink` | `WritingBuddyLinkId` | Recíproco. Un único vínculo vigente por par de usuarios. |

## Cómo se concede el acceso

| Modalidad de la obra | Camino | Resultado |
|---|---|---|
| `PUBLIC` | El usuario **empieza a corregir** y con eso se convierte en LB ([`FEAT-RDG-001`](../features/reading/FEAT-RDG-001-become-beta-reader-by-correcting.md)) | `BetaReaderAccess` inmediato |
| `ON_REQUEST` | El usuario solicita, el autor acepta ([`FEAT-RDG-002`](../features/reading/FEAT-RDG-002-request-beta-reader-access.md), [`FEAT-RDG-003`](../features/reading/FEAT-RDG-003-resolve-access-request.md)) | `BetaReaderAccess` tras la aprobación |
| `PRIVATE` | El autor invita, el usuario acepta ([`FEAT-RDG-004`](../features/reading/FEAT-RDG-004-invite-beta-reader.md), [`FEAT-RDG-005`](../features/reading/FEAT-RDG-005-resolve-invitation.md)) | `BetaReaderAccess` tras la aceptación |

Los tres caminos producen el mismo objeto de dominio. La modalidad decide el camino, no el
resultado.

**Los tres funcionan.** Resolver una solicitud o una invitación **cierra el objeto y concede
el acceso en la misma transacción**: son las dos únicas escrituras del proyecto que cambian
dos agregados a la vez, y se puede porque los dos son de este contexto — en cuanto uno fuese
de otro, volvería a ser un evento.

Los tres se cruzan en un solo sitio, y conviene saber cuál: no se puede invitar a quien tiene
una solicitud abierta ni a quien ya es lector beta, así que la única forma de tener una oferta
pendiente **y** acceso vivo es que el acceso haya llegado por la tercera vía, corrigiendo.

### Conceder un acceso no compromete nada

Lo comprometía hasta [`decision:0004`](../decisions/0004-credit-reservation-on-access-grant.md),
que apartaba créditos del autor al conceder. Esa decisión está **sustituida por
[`0006`](../decisions/0006-credit-system.md)** y no queda nada vigente de ella: no hay
reserva, ni saldo disponible distinto del saldo, ni compensación entre `Reading` y `Credits`.

Lo que eso borra de este contexto:

| Antes | Ahora |
|---|---|
| Proyección del saldo disponible del autor | No existe. Este contexto no conoce saldos |
| `Reading` niega accesos por falta de saldo | No los niega: dar acceso no cuesta nada |
| `CreditReservationRejected` revoca el acceso concedido | Ese evento no existe |
| Compensación ante una reserva fallida | No hay nada que compensar |

Quién puede corregir **ahora mismo** lo decide `Credits`, lo publica como un booleano por
capítulo y lo consume `Feedback`
([`FEAT-CRD-009`](../features/credits/FEAT-CRD-009-balance-check-on-correction-start.md)).
`Reading` no participa en esa conversación.

## Eventos publicados

| Evento | Cuándo | Consumidores |
|---|---|---|
| `BetaReaderAccessGranted` | Se concede acceso | `Notification`. **`Credits` no lo consume**: conceder acceso no mueve ni compromete créditos |
| `BetaReaderAccessRevoked` | Se retira el acceso, **por cualquiera de los tres caminos**: descartar un borrador, un bloqueo o la decisión del autor (`FEAT-RDG-010`) | `Feedback`, `Notification` |
| `AccessRequested` | Un usuario solicita ser LB | `Notification` (avisa al autor) |
| `AccessRequestRejected` | El autor rechaza | `Notification` |
| `BetaReaderInvited` | El autor invita a un usuario | `Notification` |
| `BetaReaderInvitationDeclined` | El invitado rechaza | `Notification` (avisa al autor, que esperaba respuesta) |
| `WritingBuddyProposed` | Se propone el vínculo | `Notification` |
| `WritingBuddyLinked` | Se acepta el vínculo | `Notification`, `Community` |

## Eventos consumidos

| Evento | Origen | Efecto |
|---|---|---|
| **`CorrectionStarted`** | `Feedback` | **Concede el acceso** si no había uno vivo (`FEAT-RDG-001`) |
| `CorrectionDraftDiscarded` | `Feedback` | Revoca el acceso nacido de esa corrección, si el lector no llegó a entregar nada |
| `WorkDeleted` | `Work` | Cierra accesos y solicitudes pendientes |
| `UserDeleted` | `User` | Cierra accesos y vínculos del usuario |
| **`UserBlocked`** | `Community` | **Revoca el acceso del bloqueado a las obras del bloqueador** (`FEAT-COM-034` `RN-B1`). `Community` no lo ordena: qué significa un bloqueo para una obra inédita se decide aquí |

`WorkAccessModeChanged` **ya no se consume**. Estaba aquí para mantener una proyección de
«qué caminos admite la obra», y [`decision:0015`](../decisions/0015-work-and-reading-ask-each-other.md)
la sustituyó por el contrato `WorkAccessBriefs`: copiar dentro de `Reading` la regla de qué
obra es visible para quién sería tener la regla más peligrosa del backend escrita dos veces.

**Ningún evento de `Credits` llega aquí**, y esa ausencia es la huella de
[`decision:0006`](../decisions/0006-credit-system.md): este contexto dejó de tener nada que
ver con la economía el día que desaparecieron las retenciones.

## Contratos publicados

| Contrato | Responde | Quién pregunta |
|---|---|---|
| `BetaReaderAccessCheck` | **Un booleano**: ¿tiene esta persona acceso vigente a esta obra? | `Feedback` |

Un booleano y nada más: quién lo concedió, cuándo y por qué vía no le hacen falta a nadie de
fuera, y entregarlos convertiría la forma de un registro de acceso en asunto ajeno. Responde
solo por accesos **vivos**, que es lo que hace que revocar tenga efecto en todas partes a la
vez.

## Contratos consumidos

| Contrato | De | Para qué |
|---|---|---|
| `WorkAccessBriefs` | `Work` | De quién es la obra, en qué modalidad está y si existe para quien pregunta |
| `ReaderMaturity` | `User` | Si quien pide tiene edad, ante una obra para adultos |
| `RegisteredUsers` | `User` | Si existe la persona a la que se invita |
| `ReaderDirectory` | `User` | Quién encaja con lo que el autor está tecleando, para invitar |

El primero es el que crea **el primer ciclo entre contextos** —`Work` ya preguntaba aquí por
el acceso— y tiene su propia decisión:
[`0015`](../decisions/0015-work-and-reading-ask-each-other.md). La regla que lo mantiene
inofensivo es que ninguno de los dos llama al otro mientras responde.

[`FEAT-RDG-001`](../features/reading/FEAT-RDG-001-become-beta-reader-by-correcting.md) sigue
sin preguntar nada a nadie: el camino `PUBLIC` no lo necesita, y esa sigue siendo la razón de
que sea el más barato de los tres.

## Reglas de negocio

- `RN-1` El autor de una obra no puede ser lector beta de ella.
- `RN-2` Un usuario no puede tener dos accesos vigentes sobre la misma obra.
- `RN-3` Una obra `PRIVATE` no admite solicitudes: el intento se rechaza.
- `RN-4` Solo el autor de la obra resuelve sus solicitudes e invitaciones.
- `RN-5` Cambiar la modalidad de acceso de una obra no afecta a los accesos ya concedidos.
- `RN-6` Una propuesta solo se envía si el destinatario tiene habilitada su recepción.
- `RN-7` **Conceder un acceso no cuesta ni compromete créditos.** El saldo del autor no se
  consulta aquí (`decision:0006`).
- `RN-8` En una obra `PUBLIC`, el acceso lo concede **empezar una corrección**, no una acción
  aparte.
- `RN-9` Una obra `PUBLIC` **tampoco admite solicitudes**, por el motivo contrario a `RN-3`:
  ahí no hace falta pedir nada, y aceptarlas le daría trabajo al autor para nada
  ([`FEAT-RDG-002`](../features/reading/FEAT-RDG-002-request-beta-reader-access.md) `RN-2`).
- `RN-10` **Invitar sí vale en cualquier modalidad**, incluida `PUBLIC`. La asimetría con
  `RN-9` es deliberada: una solicitud le pide trabajo al autor, una invitación es el autor
  decidiendo hacerlo.
- `RN-11` Resolver una solicitud o una invitación **cierra el objeto y concede el acceso en la
  misma transacción**. Los dos agregados son de este contexto, así que no hay frontera que
  cruzar ni evento que esperar.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| R-1 | ¿El autor puede revocar un acceso ya concedido? (`A-4`) | Funcionalidad nueva y sus consecuencias sobre el feedback existente |
| ~~R-2~~ | ¿Las solicitudes e invitaciones expiran? | **Resuelta: no.** Se cancelan, se retiran y se rechazan; caducar exige un reloj, un estado y un proceso para un problema que esas tres ya resuelven (`FEAT-RDG-002` `RN-10`). Se revisará si aparece volumen |
| R-3 | ¿Qué habilita exactamente el vínculo de writing buddy? (`A-1`) | Podría conceder accesos automáticos |
| R-4 | ¿Los grupos de LB conceden acceso en bloque a una obra? | Caso de uso de asignación masiva. Sería una operación por lotes sobre `FEAT-RDG-004`, no un camino de acceso nuevo |
| R-9 | ¿Hace falta un enfriamiento entre un rechazo y la siguiente solicitud? | Hoy se puede volver a pedir sin límite (`FEAT-RDG-002` `RN-9`) |
| R-16 | ¿Hay tope de invitaciones pendientes por obra? | Sin tope, invitar es un canal de mensajería con otro nombre |
| R-5 | ¿El acceso es a la obra completa o fragmento a fragmento? | Con novelas por fragmentos cambia el modelo (`D-1`) |
| R-6 | ¿Hay límite de obras de las que ser LB simultáneamente? | Control de calidad del feedback |
| ~~R-7~~ | ¿Qué ve el lector cuando su acceso se revoca por falta de saldo del autor? | **Desaparece:** ya no hay revocaciones por saldo (`decision:0006`) |
| ~~R-8~~ | ¿Una obra sin saldo del autor desaparece del catálogo o aparece marcada como cerrada? | **Resuelta de otra forma:** el catálogo filtra por `ChapterCorrectabilityChanged`, que es de `Credits` (`decision:0008`) |
