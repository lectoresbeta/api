---
id: FEAT-MOD-001
title: Presentar una reclamación
context: Moderation
concept: Claim
actors: [User, Writer]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-23 (backoffice de administración)
  - docs/bounded-contexts/moderation.md
endpoints:
  - POST /claims
events: [ClaimSubmitted]
depends_on: []
updated: 2026-09-23
---

# FEAT-MOD-001 — Presentar una reclamación

## Resumen

Un usuario señala contenido ajeno para que un moderador lo revise.

| Quién | Sobre qué | Motivos típicos |
|---|---|---|
| Cualquier lector | Una **obra** inapropiada | Contenido ilegal, ofensivo, plagio |
| El autor | Una **corrección** recibida | No aporta valor, demasiado breve, fuera de tema, ofensiva |
| Cualquier usuario | Otro **usuario** | Comportamiento abusivo |

**No produce ningún efecto inmediato.** Se registra y espera.

## Por qué no hay efecto inmediato

Es la decisión que define el mecanismo. Si reclamar ocultase el contenido, congelase créditos
o avisase al reclamado, **el botón de denunciar sería un arma**: bastaría con reclamar para
silenciar a alguien, o para dejar de pagar una corrección que no gustó.

Lo que se paga a cambio es real y conviene decirlo: **un contenido genuinamente dañino
permanece visible hasta que alguien lo revise**. Si eso resulta inaceptable para algún tipo de
contenido, la salida no es ocultar por defecto sino abrir una vía urgente con criterios
estrictos (`MOD-3`).

## Actores y autorización

| Actor | Puede reclamar | Condición |
|---|---|---|
| `User` | Una obra que puede ver | Cuenta activada |
| `Writer` | Una corrección de **su** obra | Es el autor |
| `User` | Otro usuario | Cuenta activada |

Nadie reclama sobre lo propio.

## Reglas de negocio

- `RN-1` La reclamación **no produce ningún efecto**: ni oculta, ni congela, ni avisa al
  reclamado.
- `RN-2` Un usuario **no reclama dos veces** sobre el mismo objeto. Reintentar devuelve la
  reclamación existente.
- `RN-3` Toda reclamación lleva **un motivo del catálogo** y admite texto libre. El catálogo
  ordena el trabajo del moderador; el texto libre es donde está la información real.
- `RN-4` Reclamar una **corrección** solo puede hacerlo el autor de la obra corregida.
- `RN-5` Solo se puede reclamar una corrección **visible**: una bloqueada por descubierto aún
  no se ha leído ([`FEAT-CRD-018`](../credits/FEAT-CRD-018-negative-balance.md)).
- `RN-6` Hay un **límite de reclamaciones por usuario y periodo** (`MOD-2`).
- `RN-7` El reclamante **no conoce la identidad del moderador** que la revisa.
- `RN-8` Una corrección **por enlace público** no admite reclamación de créditos —no los
  movió— pero sí por contenido inapropiado (`C-46`).

`RN-5` evita una vía de abuso sutil: reclamar a ciegas una corrección que aún no se ha podido
leer, solo para no pagarla.

## El límite no es una formalidad

**Reclamar una corrección devuelve créditos al autor si se estima.** Eso convierte el botón en
una forma de no pagar:

```text
Recibo una corrección → la reclamo como «no aporta valor» → recupero mis créditos
```

Si el sistema no distingue **una crítica dura de una corrección fraudulenta**, los correctores
aprenderán a escribir elogios, que es exactamente lo contrario del producto.

De ahí `RN-6` y su pareja: **reclamar en falso de forma reiterada tiene consecuencias**
([`FEAT-MOD-002`](FEAT-MOD-002-review-claim.md)). Un autor con muchas reclamaciones
desestimadas debería perder temporalmente la posibilidad de reclamar.

## Flujo principal

1. El usuario abre el menú del contenido y elige reclamar.
2. Selecciona motivo y, opcionalmente, explica.
3. Se registra la reclamación en `PENDING`.
4. Se publica `ClaimSubmitted`.
5. `Notification` avisa por correo a los moderadores.
6. El usuario recibe confirmación de que se ha registrado. **Nada más ocurre.**

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Ya reclamó ese objeto | Devuelve la existente | `200` |
| Reclama contenido propio | Se rechaza | `403` |
| Reclama una corrección que no es de su obra | Se rechaza | `403` |
| Reclama una corrección bloqueada | Se rechaza | `409` |
| Supera el límite del periodo | Se rechaza, con explicación | `429` |
| Motivo fuera del catálogo | Se rechaza | `422` |
| Cuenta sin activar | Se rechaza | `403` |
| El objeto ya no existe | Se rechaza | `404` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Presentar reclamación | `POST /claims` | `submitClaim` |
| Ver mis reclamaciones | `GET /me/claims` | `listMyClaims` |

La respuesta **no dice qué va a pasar**, porque no va a pasar nada todavía. Confirma el
registro y da un identificador con el que el usuario puede seguirla.

## Eventos

**Publica**

| Evento | Cuándo | Payload |
|---|---|---|
| `ClaimSubmitted` | Al registrarse | `claimId`, `type`, `targetType`, `targetId`, `reporterId`, `reason`, `submittedAt` |

**No lleva el texto libre del reclamante.** Es material que puede acusar a alguien y no tiene
por qué circular por la cola: `Notification` avisa de que hay trabajo, no reproduce la
acusación.

## Modelo de datos afectado

`claim`: `id`, `type`, `target_type`, `target_id`, `reporter_id`, `reason`, `description`,
`status`, `submitted_at`.

Referencia al objetivo **por tipo e identificador**, sin clave foránea: una reclamación no
puede impedir que otro contexto borre lo suyo.

Índice único `(reporter_id, target_type, target_id)` para `RN-2`.

## Criterios de aceptación

- [ ] Presentar una reclamación no cambia nada visible para nadie más que para el reclamante.
- [ ] El reclamado **no se entera** de que existe.
- [ ] Un usuario no puede reclamar dos veces el mismo objeto.
- [ ] Solo el autor de la obra puede reclamar sus correcciones.
- [ ] Una corrección bloqueada no se puede reclamar.
- [ ] Superar el límite devuelve `429` con explicación.
- [ ] El evento publicado no contiene el texto libre del reclamante.
- [ ] Si el objeto reclamado desaparece, la reclamación sigue siendo legible.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **MOD-2** | ¿Cuántas reclamaciones por periodo, y qué consecuencia tiene reclamar en falso? | Sin ello, reclamar es una forma gratuita de no pagar |
| MOD-5 | ¿Se agrupan varias reclamaciones sobre el mismo objeto en un solo expediente? | Diez denuncias del mismo texto no son diez decisiones |
| MOD-10 | ¿Puede reclamar un usuario sin cuenta, desde un enlace público? | Hoy no; sería la única vía para quien ve algo grave sin estar registrado |
| C-46 | ¿Puede el autor reclamar una corrección por enlace público? | No hay créditos que devolver, pero sí contenido que moderar |

## Estado

**Especificación:** `DRAFT`. `MOD-2` debe cerrarse antes de `APPROVED`.

**Implementación:** `TODO`.
