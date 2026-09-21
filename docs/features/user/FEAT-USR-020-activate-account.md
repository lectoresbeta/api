---
id: FEAT-USR-020
title: Activar la cuenta desde el enlace enviado por email
context: User
concept: Account
actors: [Guest, User]
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - figma:1800-13778 (Mailing 1470:9560, aviso en 1470:9456)
  - docs/ui/account-creation.md
endpoints: [POST /auth/activate]
events: [AccountActivated]
depends_on: [FEAT-USR-001, FEAT-NOT-008]
updated: 2026-09-21
---

# FEAT-USR-020 — Activar la cuenta desde el enlace enviado por email

## Resumen

Al registrarse, el usuario recibe un correo con el botón «ACTIVAR MI CUENTA». Al seguir ese
enlace, su cuenta pasa de `PENDING_ACTIVATION` a `ACTIVE`.

La particularidad de este flujo es que **la activación no bloquea el onboarding**: el
diseño muestra el aviso de verificación como panel informativo en los tres pasos, mientras
el usuario sigue rellenando su perfil. Esto reduce la fricción del alta, pero abre un hueco
de seguridad que hay que cerrar de forma explícita (`OB-3`).

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Guest` | Activar una cuenta | Poseer un token de activación válido |
| `User` | Activar su propia cuenta | Igual; puede llegar con sesión ya iniciada |

La autorización la da **el token**, no la sesión: el usuario puede abrir el correo en otro
dispositivo o navegador.

## Precondiciones

- Existe una cuenta en estado `PENDING_ACTIVATION`.
- Se ha emitido un token de activación asociado a esa cuenta.

## Reglas de negocio

- `RN-1` El token es de un solo uso: al activarse la cuenta queda invalidado.
- `RN-2` El token es largo, aleatorio y no enumerable. No se deriva del email ni del `UserId`.
- `RN-3` Activar una cuenta ya activa no es un error: la operación es idempotente y devuelve
  el mismo resultado.
- `RN-4` Un token caducado no activa la cuenta y ofrece reenviar uno nuevo (`FEAT-USR-021`).
- `RN-5` La respuesta a un token inválido no revela si el token existió, si caducó o si la
  cuenta existe.
- `RN-6` La activación no forma parte del onboarding: son dos procesos independientes que
  transcurren en paralelo y pueden completarse en cualquier orden.
- `RN-7` Al activarse la cuenta se publica `AccountActivated`.

## Estados de la cuenta

| Estado | Cuándo | Qué permite |
|---|---|---|
| `PENDING_ACTIVATION` | Tras el registro | Onboarding completo. **El resto está sin decidir** (`OB-3`) |
| `ACTIVE` | Tras seguir el enlace | Todo |
| `DELETED` | Tras eliminar la cuenta | Nada |

## Flujo principal

1. El usuario abre el correo y pulsa «ACTIVAR MI CUENTA».
2. El cliente envía el token al backend.
3. El sistema valida el token: existe, no está usado y no ha caducado.
4. La cuenta pasa a `ACTIVE`.
5. El token se invalida.
6. Se publica `AccountActivated`.
7. El usuario continúa donde estuviera: en el onboarding si no lo ha terminado, o en el Home.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Token ya usado y cuenta activa | Éxito idempotente (`RN-3`) | `200` |
| Token caducado | Se rechaza y se ofrece reenviar | `410` con `code: ACTIVATION_TOKEN_EXPIRED` |
| Token inexistente o manipulado | Se rechaza sin revelar la causa (`RN-5`) | `404` con `code: INVALID_ACTIVATION_TOKEN` |
| Cuenta eliminada | Se rechaza | `404` |
| Sin sesión | Funciona igual: el token es la autorización | — |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Activar cuenta | `POST /auth/activate` | `activateAccount` |

El token viaja en el cuerpo, no en la ruta: así no queda registrado en logs de servidor ni
en el historial del navegador. El enlace del correo apunta a una página del frontend que lo
extrae y lo envía.

## Eventos

**Publica**

| Evento | Cuándo | Consumidores |
|---|---|---|
| `AccountActivated` | La cuenta pasa a `ACTIVE` | `Notification`, y `Credits` si se decide que los créditos de bienvenida dependan de la activación (`OB-3`) |

## Efectos en créditos

Depende de `OB-3`. Hay dos posturas:

| Opción | Consecuencia |
|---|---|
| Los +20 créditos se abonan al registrarse (`UserRegistered`) | Más simple. Una cuenta sin verificar tiene saldo |
| Se abonan al activarse (`AccountActivated`) | Ata el saldo a un email real y encarece el registro masivo de cuentas falsas |

**Recomendación: abonar al activar.** Los créditos son el valor económico de la plataforma y
el registro sin verificar es gratis; separar ambos convierte la verificación en la barrera
natural contra el abuso, sin coste para el usuario legítimo.

Esto afecta a `FEAT-CRD-002` y debe decidirse junto con `OB-3`.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `user` | Campo `status`, y `activated_at` |
| `account_activation_token` | Token, `user_id`, `expires_at`, `used_at` |

Índice único sobre el hash del token. **El token se almacena cifrado con un hash, no en
claro**: quien lea la base de datos no debe poder activar cuentas ajenas.

## Diseño (Figma)

Correo: `1470:9560`. Aviso persistente durante el onboarding: `1470:9456`.

Ver [`../../ui/account-creation.md`](../../ui/account-creation.md).

## Criterios de aceptación

- [ ] Un token válido pasa la cuenta a `ACTIVE` y lo invalida.
- [ ] Reutilizar el token de una cuenta ya activa devuelve éxito, no error.
- [ ] Un token caducado devuelve `410` y no activa la cuenta.
- [ ] Un token inexistente devuelve `404` sin revelar si existió.
- [ ] El token se almacena con hash, nunca en claro.
- [ ] La activación funciona sin sesión iniciada.
- [ ] Se publica `AccountActivated` exactamente una vez por activación.
- [ ] El usuario puede completar todo el onboarding sin haber activado la cuenta.
- [ ] Ningún log registra el token.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| OB-3 | ¿Qué puede hacer exactamente una cuenta `PENDING_ACTIVATION`? ¿Recibe créditos? ¿Puede comentar? | **Bloqueante.** Define la superficie de abuso |
| OB-9 | ¿Cuánto dura el token? | Propuesta: 24 horas |
| OB-11 | ¿El registro por Google, Facebook o LinkedIn da el email por verificado? | Evitaría un paso innecesario, pero depende de la confianza en cada proveedor |
| OB-8 | «Cancelar suscripción» en el correo de activación | Podría impedir la activación |

## Estado

**Especificación:** `DRAFT`. Para llegar a `APPROVED` hace falta resolver `OB-3`, que decide
además dónde se abonan los créditos de bienvenida.

**Implementación:** `TODO`.
