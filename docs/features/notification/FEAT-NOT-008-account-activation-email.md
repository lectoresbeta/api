---
id: FEAT-NOT-008
title: Enviar el email de activación de cuenta
context: Notification
concept: Delivery
actors: []
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - figma:1800-13778 (1470:9560)
  - docs/ui/account-creation.md
endpoints: []
events: [UserRegistered, ActivationEmailRequested]
depends_on: [FEAT-USR-001, FEAT-USR-020]
updated: 2026-09-21
---

# FEAT-NOT-008 — Enviar el email de activación de cuenta

## Resumen

Correo transaccional que se envía al registrarse, con el botón «ACTIVAR MI CUENTA». Es el
único camino para que una cuenta pase a `ACTIVE`, así que su entrega es crítica: un correo
que no llega es un usuario que no entra.

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
| Pie | «Ver en navegador», «Cancelar suscripción», redes sociales |

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

`RN-4` choca de frente con el «Cancelar suscripción» del pie: ver `OB-8`.

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
| OB-8 | ¿Por qué lleva «Cancelar suscripción» un correo transaccional? | Contradice `RN-4` y puede dejar cuentas inactivables |
| N-3 | ¿Qué proveedor de email se usa? | Sin decidir |
| N-6 | ¿Cuál es el asunto del correo? | Sin definir en el diseño |
| N-7 | ¿Hay versión en texto plano además de HTML? | Entregabilidad y accesibilidad |

## Estado

**Especificación:** `DRAFT`. Falta el asunto, el proveedor y resolver `OB-8`.

**Implementación:** `TODO`.
