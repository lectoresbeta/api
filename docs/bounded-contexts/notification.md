# Bounded context: `Notification`

> Estado: `DRAFT` — Prefijo: `NOT`
>
> **Implementado:** el correo de activación (`FEAT-NOT-008`), los avisos in-app
> (`FEAT-NOT-001`) y el centro de notificaciones (`FEAT-NOT-009`). Las preferencias
> (`FEAT-NOT-003`) y el canal de correo para lo que no es operativo (`FEAT-NOT-002`) no.

## Responsabilidad

Avisar al usuario de lo que ocurre y que le importa, por los canales que haya aceptado.

Es un contexto **puramente reactivo**: no origina hechos, reacciona a los de los demás.

## Qué posee

- Notificaciones y su estado (leída / no leída).
- Preferencias de notificación por tipo y canal.
- La entrega: in-app y por email.
- Las plantillas de los mensajes.

## Qué NO posee

| No es suyo | Es de |
|---|---|
| Los hechos que originan las notificaciones | Cada contexto de origen |
| La decisión de si un hecho es relevante para el usuario | `Notification` **sí** decide esto, a partir de sus preferencias |
| La preferencia de recibir MD o propuestas (distinta de la de notificaciones) | `User` |

## Conceptos

| Concepto | Responsabilidad |
|---|---|
| `Notification` | El aviso y su ciclo de vida |
| `Preference` | Qué recibe cada usuario y por qué canal |
| `Delivery` | Entrega efectiva por cada canal |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `Notification` | `NotificationId` | Tiene destinatario, tipo y origen trazable |
| `NotificationPreference` | `UserId` | Una configuración por usuario |

### Enums

| Nombre | Valores |
|---|---|
| `DeliveryChannel` | `IN_APP`, `EMAIL` |
| `NotificationType` | Ver tabla siguiente |

## Catálogo de notificaciones

Los nombres son los de `NotificationKind`, que es el enum que viaja en la API. Un ✅ marca lo
que hoy se entrega de verdad ([`FEAT-NOT-001`](../features/notification/FEAT-NOT-001-in-app-notifications.md));
el resto es el catálogo previsto.

| Tipo | Hecho que lo origina | Destinatario | |
|---|---|---|---|
| `ACCESS_REQUESTED` | `AccessRequested` (`Reading`) | Autor de la obra | ✅ |
| `ACCESS_REQUEST_RESOLVED` | `BetaReaderAccessGranted` / `AccessRequestRejected` (`Reading`) | Solicitante | ✅ |
| `BETA_READER_INVITATION` | `BetaReaderInvited` (`Reading`) | Usuario invitado | ✅ |
| `BETA_READER_ACCESS_REVOKED` | `BetaReaderAccessRevoked` (`Reading`) | Lector que lo pierde | ✅ |
| `CORRECTION_RECEIVED` | `FeedbackSubmitted` (`Feedback`) | Autor de la obra | ✅ |
| `ACCOUNT_ACTIVATION` | `UserRegistered` / `ActivationEmailRequested` (`User`) | Titular de la cuenta | ✅ (solo email) |
| `PASSWORD_RESET_REQUESTED` | `PasswordResetRequested` (`User`) | Titular de la cuenta | ✅ (solo email) |
| `PASSWORD_CHANGED` | `PasswordChanged` (`User`) | Titular de la cuenta | ✅ (solo email) |
| `WRITING_BUDDY_PROPOSED` | `Reading` | Usuario propuesto | |
| `CORRECTION_REPLIED` | `Feedback` | Autor de la corrección | |
| `CORRECTION_RATED` | `Feedback` | Autor de la corrección | |
| `CREDITS_ADDED` / `CREDITS_SPENT` | `Credits` | Titular de la cuenta | |
| `BALANCE_WENT_NEGATIVE` | `Credits` | Autor de la obra | |
| `SUBSCRIBED_AUTHOR_PUBLISHED` | `Work` / `Community` | Suscriptores | |
| `DIRECT_MESSAGE_RECEIVED` | `Community` | Destinatario | |
| `PLATFORM_INVITATION` | `User` | Persona invitada (solo email; aún no es usuaria) | |

`ACCESS_REQUEST_RESOLVED` es **un solo tipo para los dos desenlaces**, y el `outcome` del
payload los distingue: quien preguntó espera una respuesta, y la bandeja no tiene por qué
tratar dos tipos para lo que es una sola conversación.

`BETA_READER_ACCESS_REVOKED` no estaba en este catálogo porque cuando se escribió, retirar un
acceso no existía. Lo añadió `FEAT-NOT-001` para saldar la deuda que dos fichas habían dejado
anotada: quien pierde el acceso **deja de poder entregar lo que estaba escribiendo**, y nada
se lo decía.

Los de `Credits` no se conectan todavía, y no por olvido: son de grano fino —uno por
movimiento— y merecen antes una decisión sobre agrupación (`N-2`).

## Qué contiene un aviso, y qué no

El aviso guarda el `kind` y un `payload` con lo justo para construir una frase y un enlace:
nombres, títulos, identificadores. **El texto no lo compone el servidor** — lo construye el
cliente a partir de esos dos datos, que es lo que permite traducirlo sin desplegar y cambiar
la redacción sin migrar nada.

El `payload` es una **instantánea** del momento del hecho. Si quien lo provocó cambia de
nombre después, el aviso antiguo conserva el de entonces, que es lo correcto: describe algo
que pasó.

Los nombres y los títulos se piden a `User` y a `Work` por sus **contratos publicados**. Que
esto sea legítimo y la consulta desde `CheckAuthorAudience` no lo fuera tiene una razón
concreta: **un consumidor de eventos no es un contrato respondiendo**, así que la regla 4 de
[`decision:0014`](../decisions/0014-published-contracts-between-contexts.md) no aplica — no
hay nadie esperando al otro lado.

## Reglas de negocio

- `RN-1` Una notificación se entrega solo por los canales que el usuario ha aceptado.
- `RN-2` El canal in-app siempre está disponible; solo el email es configurable.
  *(Pendiente de confirmar: `N-1`.)*
- `RN-3` La entrega es idempotente: un mismo evento no genera dos avisos iguales. Lo garantiza
  el índice único `(destinatario, tipo, evento de origen)`, no solo la comprobación previa:
  la cola no promete entrega única y dos consumidores pueden correr a la vez.
- `RN-4` Ninguna notificación incluye contenido de obras, de feedback o de mensajes directos
  en el cuerpo del email. Solo un aviso y un enlace.
- `RN-5` **Nadie se avisa a sí mismo.** Un hecho que provoca el propio destinatario no genera
  aviso: ya sabe lo que ha hecho, y un aviso propio es ruido que enseña a ignorar la campana.
- `RN-6` Un hecho cuyo destinatario no se puede resolver **se descarta sin error**. Una cuenta
  eliminada no recibe avisos, y reintentar no la va a resucitar.

`RN-4` no es una preferencia de estilo: es protección del contenido inédito, que no debe
salir de la plataforma por correo. Se aplica igual al canal in-app, que sí se queda dentro,
**porque la regla es del aviso y no del canal**: un aviso dice que hay algo que leer, y se lee
donde vive.

Mientras `N-1` siga abierta, los avisos in-app **no se filtran por preferencias**: el canal
entrega siempre. Cuando se decida, se aplica aquí.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| N-1 | ¿Se pueden desactivar también las notificaciones in-app o solo las de email? | El documento habla solo del correo |
| N-2 | ¿Se agrupan las notificaciones (resumen diario) o se envían una a una? | Volumen de correo |
| N-3 | ¿Qué proveedor de email se usa? | Integración externa |
| N-4 | ¿Hay notificaciones push o solo in-app y email? | Alcance |
| N-5 | ¿Cuánto tiempo se conservan las notificaciones leídas? | Retención |
