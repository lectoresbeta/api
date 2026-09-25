---
id: FEAT-NOT-007
title: Enviar el email de invitación a la plataforma
context: Notification
concept: Delivery
actors: []
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/features/README.md
  - conversation:2026-09-25
endpoints: []
events:
  - PlatformInvitationSent
depends_on: [FEAT-NOT-001, FEAT-NOT-002, FEAT-USR-018]
updated: 2026-09-25
---

# FEAT-NOT-007 — Enviar el email de invitación a la plataforma

## Resumen

El correo que recibe alguien de fuera cuando le invitan.

`PLATFORM_INVITATION` estaba en el catálogo de avisos desde
[`FEAT-NOT-001`](FEAT-NOT-001-in-app-notifications.md) **sin nadie que lo disparara**. Esta
ficha lo enciende.

## Lo que lo hace distinto de todo lo demás que manda este contexto

**Va a alguien que no tiene cuenta.** Dos consecuencias, y ninguna es un detalle:

- **la fila del aviso se apunta a nombre de quien invita**, no del destinatario. El
  destinatario no es un usuario de la plataforma y no tiene dónde apuntarle nada; el
  invitador sí, y además la acción es suya;
- **no se le pueden aplicar preferencias de notificación** porque no tiene ninguna. Es correo
  transaccional: lo pidió alguien, va una vez, y no se repite salvo que vuelvan a pedirlo.

## Reglas de negocio

- `RN-1` Al recibir `PlatformInvitationSent`, se manda un correo a la dirección invitada con
  un enlace de alta.
- `RN-2` El asunto y el cuerpo nombran a quien invita: su nombre si lo tiene, su `@usuario` si
  no. Un enlace de una plataforma desconocida sin un nombre detrás es indistinguible de spam.
- `RN-3` **El token no viaja en el hecho.** Es una credencial viva, y una credencial viva no
  entra en una cola que persiste, reintenta y aparca mensajes. El enlace se pide a `User` en
  el momento de enviar, por el contrato `InvitationLinkProvider`.
- `RN-4` **Cada envío emite un token nuevo que invalida el anterior.** El token en claro no se
  puede recuperar —en la tabla solo está su hash—, así que reenviar es emitir otro. **Solo el
  último correo funciona**, que es exactamente lo que hace seguro reenviar.
- `RN-5` Al pedir el enlace se vuelve a comprobar todo: si la invitación ya se usó, o si esa
  dirección se registró por su cuenta mientras el correo esperaba en un reintento, **no se
  manda nada**. Es el desenlace normal de un hecho reentregado, no un fallo.
- `RN-6` **Un `eventId` no manda dos correos.** La fila del aviso lo guarda, y una reentrega
  se detiene ahí — antes de emitir un token nuevo que invalidaría el enlace del primer correo.
- `RN-7` El aviso apuntado **no guarda la dirección invitada ni el token**. A quién invita
  alguien no hace falta guardarlo para nada, y el token no se apunta en ningún sitio.
- `RN-8` La URL del enlace sale de `INVITATION_URL_TEMPLATE`, que apunta al frontend: la
  página de alta extrae el token y lo manda en el cuerpo, para que no quede en los logs ni en
  el historial.

## Configuración

| Variable | Qué es |
|---|---|
| `INVITATION_URL_TEMPLATE` | La URL de la página de alta del frontend, con `{token}` donde va el token |

## Eventos

**Consume** — `PlatformInvitationSent`.

**Publica** — ninguno.

## Efectos en créditos

Ninguno.

## Criterios de aceptación

- [x] Recibir el hecho manda un correo con un enlace utilizable.
- [x] El hecho no lleva el token ni su hash.
- [x] Reenviar invalida el enlace anterior.
- [x] Una invitación ya consumida no manda nada.
- [x] Una dirección que se registró mientras tanto no recibe la invitación.
- [x] El mismo `eventId` no manda dos correos.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
