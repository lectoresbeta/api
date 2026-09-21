# Bounded context: `Notification`

> Estado: `DRAFT` — Prefijo: `NOT`

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

| Tipo | Se origina en | Destinatario |
|---|---|---|
| `ACCESS_REQUESTED` | `Reading` | Autor de la obra |
| `ACCESS_REQUEST_ACCEPTED` / `_REJECTED` | `Reading` | Solicitante |
| `BETA_READER_INVITATION_RECEIVED` | `Reading` | Usuario invitado |
| `WRITING_BUDDY_PROPOSED` | `Reading` | Usuario propuesto |
| `FEEDBACK_RECEIVED` | `Feedback` | Autor de la obra |
| `FEEDBACK_REPLIED` | `Feedback` | Autor del comentario |
| `FEEDBACK_RATED_POSITIVELY` | `Feedback` | Autor del comentario |
| `CREDITS_ADDED` / `CREDITS_SPENT` | `Credits` | Titular de la cuenta |
| `INSUFFICIENT_CREDITS` | `Credits` | Autor de la obra |
| `SUBSCRIBED_AUTHOR_PUBLISHED_WORK` | `Work` | Suscriptores |
| `SUBSCRIBED_AUTHOR_PUBLISHED_POST` | `Community` | Suscriptores |
| `DIRECT_MESSAGE_RECEIVED` | `Community` | Destinatario |
| `PLATFORM_INVITATION` | `User` | Persona invitada (solo email; aún no es usuaria) |

## Reglas de negocio

- `RN-1` Una notificación se entrega solo por los canales que el usuario ha aceptado.
- `RN-2` El canal in-app siempre está disponible; solo el email es configurable.
  *(Pendiente de confirmar: `N-1`.)*
- `RN-3` La entrega es idempotente: un mismo evento no genera dos avisos iguales.
- `RN-4` Ninguna notificación incluye contenido de obras, de feedback o de mensajes directos
  en el cuerpo del email. Solo un aviso y un enlace.

`RN-4` no es una preferencia de estilo: es protección del contenido inédito, que no debe
salir de la plataforma por correo.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| N-1 | ¿Se pueden desactivar también las notificaciones in-app o solo las de email? | El documento habla solo del correo |
| N-2 | ¿Se agrupan las notificaciones (resumen diario) o se envían una a una? | Volumen de correo |
| N-3 | ¿Qué proveedor de email se usa? | Integración externa |
| N-4 | ¿Hay notificaciones push o solo in-app y email? | Alcance |
| N-5 | ¿Cuánto tiempo se conservan las notificaciones leídas? | Retención |
