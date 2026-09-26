---
id: FEAT-NOT-008
title: Enviar el email de activación de cuenta
context: Notification
concept: Delivery
actors: []
spec_status: APPROVED
impl_status: PARTIAL
priority: P0
sources:
  - figma:1800-13778 (1470:9560)
  - docs/ui/account-creation.md
endpoints: []
events: [UserRegistered, ActivationEmailRequested]
depends_on: [FEAT-USR-001, FEAT-USR-020]
updated: 2026-09-24
---

# FEAT-NOT-008 — Enviar el email de activación de cuenta

## Resumen

Correo transaccional que se envía al registrarse, con el botón «ACTIVAR MI CUENTA».

Es el único camino para que una cuenta pase a `ACTIVE`, y de la activación dependen los
créditos de bienvenida y **todas las operaciones de escritura** ([`decision:0003`](../../decisions/0003-write-operations-require-activated-account.md)).
Su entrega no es importante: es crítica. Un correo que no llega es un usuario que no puede
usar la plataforma.

## Contenido

Según el diseño (`1470:9560`):

| Elemento | Contenido |
|---|---|
| Cabecera | Logo de lectoresbeta |
| Título | «🌟 ¡Gracias por unirte a lectoresbeta!» |
| Cuerpo | Presentación de la comunidad e invitación a activar |
| CTA | «ACTIVAR MI CUENTA», con el token de activación |
| Soporte | Enlace a la página de soporte |
| Firma | «El equipo de lectoresbeta» y cita de George R. R. Martin |
| Pie | «Ver en navegador» y redes sociales. **Sin «Cancelar suscripción»**, ver `RN-7` |

El asunto no está definido en el diseño.

## Reglas de negocio

- `RN-1` Se dispara al consumir `UserRegistered` y al consumir `ActivationEmailRequested`
  (reenvío).
- `RN-2` El envío es **idempotente por evento**: una entrega duplicada del mismo `eventId` no
  produce dos correos.
- `RN-3` El correo contiene el token vigente. Un reenvío invalida el anterior, de modo que
  **solo el último correo funciona**.
- `RN-4` Es un correo transaccional: **no depende de las preferencias de notificación**. Un
  usuario que haya desactivado los avisos por email debe recibirlo igualmente, porque sin él
  no puede usar la cuenta.
- `RN-5` El token no aparece en logs, ni en trazas, ni en métricas.
- `RN-6` El fallo de envío se reintenta. Agotados los reintentos, el mensaje va a la cola de
  fallos y se alerta: un usuario bloqueado sin correo es un alta perdida.

- `RN-7` El pie **no incluye baja de suscripción**. El diseño original la llevaba y se
  retira: es un correo transaccional, y un usuario que se diera de baja ahí no podría recibir
  el correo que necesita para activar su cuenta ni, por tanto, usar la plataforma.
- `RN-8` La baja de suscripción sigue existiendo en los correos de aviso y novedades, que sí
  son opcionales (`FEAT-NOT-002`, `FEAT-NOT-003`).

`RN-7` y `RN-8` trazan la línea: transaccional frente a informativo. Un correo sin el cual la
cuenta no funciona no es una suscripción.

## Eventos consumidos

| Evento | Origen | Efecto |
|---|---|---|
| `UserRegistered` | `User` | Envía el correo de activación inicial |
| `ActivationEmailRequested` | `User` | Reenvía con el token nuevo |

## Criterios de aceptación

- [ ] Registrarse produce exactamente un correo de activación.
- [ ] Procesar dos veces el mismo `UserRegistered` no produce dos correos.
- [ ] Tras un reenvío, solo el enlace del último correo activa la cuenta.
- [ ] El correo se envía aunque el usuario tenga desactivadas las notificaciones por email.
- [ ] Ningún log contiene el token de activación.
- [ ] Un fallo de envío se reintenta y acaba en la cola de fallos si no se recupera.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| OB-8 | ¿Por qué lleva «Cancelar suscripción» un correo transaccional? | **Resuelto:** se retira del pie |
| N-3 | ¿Qué proveedor de email se usa? | Sin decidir |
| N-6 | ¿Cuál es el asunto del correo? | Sin definir en el diseño |
| N-7 | ¿Hay versión en texto plano además de HTML? | Entregabilidad y accesibilidad |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `PARTIAL`.

Hecho: `SendActivationEmail` consume `UserRegistered`, pide el enlace a `User` por su contrato
publicado ([`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md)),
invalida el token anterior (`RN-3`), envía en texto y HTML (`N-7`) sin pie de baja (`RN-7`) y
sin pasar por preferencias (`RN-4`). La idempotencia (`RN-2`) se apoya en el índice único
`(recipient_id, kind, source_event_id)`, que ya existía. `ActivationFlowTest` recorre el
camino completo, de alta a saldo.

**Por qué el token no viaja en el evento:** es una credencial viva, y la cola la persiste, la
reintenta y la aparca en un transporte de fallos que se consulta. Se pide en el momento de
enviar, lo que además hace que **el enlace empiece a caducar cuando sale el correo** y no
cuando se creó la cuenta.

~~- el **reenvío** (`ActivationEmailRequested`, `RN-1`), que es `FEAT-USR-021`;~~ —
**hecho**: la funcionalidad existe y el hecho se consume (revisado el 2026-09-26).

**Falta:**

- la **alerta** cuando un envío agota los reintentos (`RN-6`). El mensaje va a la cola de
  fallos, pero nadie avisa;
- el proveedor de correo (`N-3`): hoy `MAILER_DSN` apunta a donde se le diga;
- el asunto está puesto por defecto (`N-6`), sin diseño que lo fije.
