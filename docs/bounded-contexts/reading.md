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
- Búsqueda de lectores beta.

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
| `PUBLIC` | El usuario se convierte en LB directamente | `BetaReaderAccess` inmediato |
| `ON_REQUEST` | El usuario solicita, el autor acepta | `BetaReaderAccess` tras la aprobación |
| `PRIVATE` | El autor invita, el usuario acepta | `BetaReaderAccess` tras la aceptación |

Los tres caminos producen el mismo objeto de dominio. La modalidad decide el camino, no el
resultado.

## Eventos publicados

| Evento | Cuándo | Consumidores |
|---|---|---|
| `BetaReaderAccessGranted` | Se concede acceso | `Feedback`, `Notification`, `Credits` (si se decide la reserva, `C-2`) |
| `BetaReaderAccessRevoked` | Se retira el acceso | `Feedback`, `Notification` |
| `AccessRequested` | Un usuario solicita ser LB | `Notification` (avisa al autor) |
| `AccessRequestRejected` | El autor rechaza | `Notification` |
| `BetaReaderInvited` | El autor invita a un usuario | `Notification` |
| `WritingBuddyProposed` | Se propone el vínculo | `Notification` |
| `WritingBuddyLinked` | Se acepta el vínculo | `Notification`, `Community` |

## Eventos consumidos

| Evento | Origen | Efecto |
|---|---|---|
| `WorkAccessModeChanged` | `Work` | Actualiza qué caminos de acceso admite la obra |
| `WorkDeleted` | `Work` | Cierra accesos y solicitudes pendientes |
| `UserDeleted` | `User` | Cierra accesos y vínculos del usuario |

## Reglas de negocio

- `RN-1` El autor de una obra no puede ser lector beta de ella.
- `RN-2` Un usuario no puede tener dos accesos vigentes sobre la misma obra.
- `RN-3` Una obra `PRIVATE` no admite solicitudes: el intento se rechaza.
- `RN-4` Solo el autor de la obra resuelve sus solicitudes e invitaciones.
- `RN-5` Cambiar la modalidad de acceso de una obra no afecta a los accesos ya concedidos.
- `RN-6` Una propuesta solo se envía si el destinatario tiene habilitada su recepción.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| R-1 | ¿El autor puede revocar un acceso ya concedido? (`A-4`) | Funcionalidad nueva y sus consecuencias sobre el feedback existente |
| R-2 | ¿Las solicitudes e invitaciones expiran? | Ciclo de vida y limpieza |
| R-3 | ¿Qué habilita exactamente el vínculo de writing buddy? (`A-1`) | Podría conceder accesos automáticos |
| R-4 | ¿Los grupos de LB conceden acceso en bloque a una obra? | Caso de uso de asignación masiva |
| R-5 | ¿El acceso es a la obra completa o fragmento a fragmento? | Con novelas por fragmentos cambia el modelo (`D-1`) |
| R-6 | ¿Hay límite de obras de las que ser LB simultáneamente? | Control de calidad del feedback |
