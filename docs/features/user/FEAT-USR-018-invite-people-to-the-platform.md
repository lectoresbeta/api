---
id: FEAT-USR-018
title: Invitar a personas a la plataforma por email
context: User
concept: Invitation
actors: [Autor, Lector beta]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/features/README.md
  - conversation:2026-09-25
endpoints:
  - POST /api/v1/invitations
  - GET /api/v1/me/invitations
events:
  - PlatformInvitationSent
  - PlatformInvitationConsumed
depends_on: [FEAT-USR-001, FEAT-NOT-007, FEAT-CRD-005]
updated: 2026-09-25
---

# FEAT-USR-018 — Invitar a personas a la plataforma por email

## Resumen

Mandar a alguien de fuera un enlace para crear su cuenta, y saber después qué fue de esa
invitación.

Es la primera mitad de un mecanismo de tres piezas: esta ficha crea la invitación,
[`FEAT-NOT-007`](../notification/FEAT-NOT-007-platform-invitation-email.md) manda el correo y
[`FEAT-CRD-005`](../credits/FEAT-CRD-005-invitation-reward.md) paga. Separadas tienen poco
sentido; juntas son el único canal por el que la plataforma crece desde dentro.

## Lo que gobierna toda la ficha

**Invitar no puede decir quién tiene cuenta.**

El alta y la recuperación de contraseña se cuidan de no revelarlo: responden igual exista o no
esa dirección. Un formulario de invitar que dijera «esa persona ya está» derribaría las dos de
un golpe, porque cualquiera podría comprobar direcciones de una en una desde una cuenta
recién hecha.

De ahí sale casi todo lo demás: la respuesta es la misma se mande el correo o no, y lo que
cambia entre los casos ocurre donde no se ve.

## Reglas de negocio

- `RN-1` Cualquier persona con cuenta activada puede invitar a una dirección de correo. La
  invitación crea un token de un solo uso, del que **solo se guarda el hash**.
- `RN-2` **La respuesta es idéntica se mande el correo o no.** No se envía nada si la
  dirección ya tiene cuenta; la petición responde `202` igualmente.
- `RN-3` **Nadie se invita a sí mismo.** Invitar a la dirección de la propia cuenta responde
  `422` con `code: CANNOT_INVITE_YOURSELF`. Es la única excepción a `RN-2`, y no filtra nada:
  quien invita ya sabe cuál es su correo.
- `RN-4` **Tope de 20 invitaciones al día por persona**, contadas sobre las últimas 24 horas.
  Pasado el tope, `429` con `code: INVITATION_LIMIT_REACHED`. Sin él, esto es un canal de
  correo gratuito hacia direcciones ajenas con el remitente de la plataforma: el tipo de cosa
  que quema un dominio en una tarde.
- `RN-5` **Reinvitar a quien ya invitaste no crea una segunda invitación.** Si quien invita ya
  tiene una invitación viva a esa dirección, la primera sigue valiendo y no sale un segundo
  correo. Dos correos iguales son spam con buena intención.
- `RN-6` **Dos personas distintas sí pueden invitar a la misma dirección.** No es duplicar
  nada: son dos invitaciones de dos personas, y la que se use decide quién trajo a quién.
- `RN-7` «Mis invitaciones» lista las enviadas, de la más reciente a la más antigua, con la
  dirección, la fecha y si fueron aceptadas. **No dice si se cobró por ellas** — eso es del
  saldo, y preguntárselo a `Credits` para pintar esta lista sería abrir una puerta entre
  contextos para un adorno. **Tampoco dice quién se registró**: el invitador tiene derecho a
  saber que su invitación fue aceptada, no a que le entreguen la cuenta de esa persona.
- `RN-8` Solo se ven las propias. No hay forma de ver las de otro: a quién invita alguien es
  de las cosas más privadas que guarda la plataforma.
- `RN-9` Al registrarse con el token, la invitación se marca consumida **una sola vez** y se
  publica `PlatformInvitationConsumed`. **Un token inválido o ya gastado nunca impide un
  alta** (`FEAT-USR-001` `RN-13`): se ignora en silencio.
- `RN-10` **Consumir no abona nada.** Solo deja constancia del par. La recompensa es de
  `FEAT-CRD-005` y llega con la primera corrección del invitado.
- `RN-11` Quien llega con su propio enlace no queda apuntado como invitado de sí mismo.

## API

| Operación | `operationId` | Respuesta |
|---|---|---|
| `POST /api/v1/invitations` | `sendPlatformInvitation` | `202` |
| `GET /api/v1/me/invitations` | `listMyInvitations` | `200` |

El token viaja **en el cuerpo** del alta, como `invitationToken` de
`POST /api/v1/auth/register`, y no en la URL: la página de alta del frontend lo extrae del
enlace y lo manda, para que no quede en los logs del servidor ni en el historial del
navegador.

## Eventos

**Publica**

| Evento | Cuándo | Lleva |
|---|---|---|
| `PlatformInvitationSent` | Al crear una invitación que sí se va a enviar | `invitationId`, `inviterId`, `email`, `sentAt` |
| `PlatformInvitationConsumed` | Al registrarse alguien con el token | `invitationId`, `inviterId`, `inviteeId`, `consumedAt` |

**`PlatformInvitationSent` no lleva el token**, y esa ausencia es la regla de seguridad de la
ficha. Una credencial viva no entra en una cola que persiste, reintenta y aparca mensajes:
acabaría en un fichero de mensajes muertos que nadie considera secreto. Quien manda el correo
pide el enlace en el momento de enviarlo, por el contrato `InvitationLinkProvider`.

**Consume** — ninguno.

## Efectos en créditos

Ninguno **en esta ficha**. Invitar y registrarse no mueven un crédito; eso es lo que hace que
una invitación valga más que un correo desechable. Ver
[`FEAT-CRD-005`](../credits/FEAT-CRD-005-invitation-reward.md).

## Criterios de aceptación

- [x] Invitar a una dirección sin cuenta manda un correo con un enlace.
- [x] Invitar a una dirección que ya tiene cuenta responde exactamente igual y no manda nada.
- [x] Invitarse a uno mismo se rechaza.
- [x] Superado el tope diario, se rechaza con `429`.
- [x] Reinvitar a la misma dirección no manda un segundo correo.
- [x] El hecho publicado no lleva el token ni su hash.
- [x] Registrarse con el token apunta el par y no abona nada.
- [x] Un token inválido no impide el alta.
- [x] «Mis invitaciones» muestra lo enviado y si fue aceptado, sin decir quién se registró.
- [x] Sin sesión no se invita ni se lista.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
