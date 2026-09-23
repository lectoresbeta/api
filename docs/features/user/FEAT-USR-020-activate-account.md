---
id: FEAT-USR-020
title: Activar la cuenta desde el enlace enviado por email
context: User
concept: Account
actors: [Guest, User]
spec_status: APPROVED
impl_status: PARTIAL
priority: P0
sources:
  - figma:1800-13778 (Mailing 1470:9560, aviso en 1470:9456)
  - docs/ui/account-creation.md
endpoints: [POST /auth/activate]
events: [AccountActivated]
depends_on: [FEAT-USR-001, FEAT-NOT-008, FEAT-USR-025]
updated: 2026-09-24
---

# FEAT-USR-020 — Activar la cuenta desde el enlace enviado por email

## Resumen

Al registrarse, el usuario recibe un correo con el botón «ACTIVAR MI CUENTA». Al seguir ese
enlace, su cuenta pasa de `PENDING_ACTIVATION` a `ACTIVE`.

La activación es la frontera real de la plataforma. **No bloquea el onboarding** —el aviso
aparece como panel informativo mientras el usuario rellena su perfil— pero sí todo lo demás:
hasta que se activa, la cuenta no puede escribir nada (`FEAT-USR-025`) y no tiene créditos.

Al activarse se abonan los **10 créditos de bienvenida** y se desbloquea la plataforma
entera. Ver [`decision:0003`](../../decisions/0003-write-operations-require-activated-account.md).

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

## Quien entra con Google no pasa por aquí

**El alta con Google marca el correo como verificado** y crea la cuenta ya en `ACTIVE`
(`OB-11`, resuelta, ver [`FEAT-USR-002`](FEAT-USR-002-register-with-google.md)). No se genera
token ni se envía correo de activación.

Esta funcionalidad aplica solo al alta con correo y contraseña.

## Reglas de negocio## Reglas de negocio

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
- `RN-8` `AccountActivated` es el hecho que dispara el abono de los 10 créditos de bienvenida
  (`FEAT-CRD-002`). `User` no conoce la cantidad.
- `RN-9` Al activarse, todas las operaciones de escritura quedan habilitadas de inmediato.

## Estados de la cuenta

| Estado | Cuándo | Qué permite |
|---|---|---|
| `PENDING_ACTIVATION` | Tras el registro | Onboarding completo y lectura. **Ninguna operación de escritura** y **sin créditos** (`FEAT-USR-025`) |
| `ACTIVE` | Tras seguir el enlace | Todo. Se abonan los 10 créditos de bienvenida |
| `DELETED` | Tras eliminar la cuenta | Nada |

## Flujo principal

1. El usuario abre el correo y pulsa «ACTIVAR MI CUENTA».
2. El cliente envía el token al backend.
3. El sistema valida el token: existe, no está usado y no ha caducado.
4. La cuenta pasa a `ACTIVE`.
5. El token se invalida.
6. Se publica `AccountActivated`.
7. `Credits` abona los 10 créditos de bienvenida.
8. Las operaciones de escritura quedan habilitadas.
9. El usuario continúa donde estuviera: en el onboarding si no lo ha terminado, o en el Home.

Los pasos 7 y 8 son consecuencia del evento; la respuesta no los espera.

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
| `AccountActivated` | La cuenta pasa a `ACTIVE` | **`Credits`** (abona los 10 de bienvenida), `Notification`, `Feedback` (para aplicar `RN-4` de `FEAT-USR-025`) |

## Efectos en créditos

**`AccountActivated` es el hecho que abona los 10 créditos de bienvenida** (`FEAT-CRD-002`).
`UserRegistered` solo crea la cuenta de créditos, con saldo cero.

Esto ata el saldo a una dirección de correo real y encarece el registro masivo de cuentas
falsas, que el crédito por invitación (`FEAT-CRD-005`) incentiva a intentar.

Como cualquier otro consumidor, `Credits` deduplica por `eventId`: activar dos veces no
abona dos veces.

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
- [ ] Tras procesarse el evento, el saldo del usuario es 20.
- [ ] Reprocesar el mismo `AccountActivated` no abona 10 créditos por segunda vez.
- [ ] Antes de activar, el saldo es 0.
- [ ] Antes de activar, una operación de escritura devuelve `403 ACCOUNT_NOT_ACTIVATED`.
- [ ] Justo después de activar, esa misma operación funciona.
- [ ] El usuario puede completar todo el onboarding sin haber activado la cuenta.
- [ ] Ningún log registra el token.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| OB-3 | ¿Qué puede hacer una cuenta `PENDING_ACTIVATION`? | **Resuelto:** leer y completar el onboarding, nada más. Ver `decision:0003` y `FEAT-USR-025` |
| OB-8 | «Cancelar suscripción» en el correo de activación | **Resuelto:** se retira. Es un correo transaccional (`FEAT-NOT-008`) |
| OB-9 | ¿Cuánto dura el token? | Propuesta: 24 horas. Sin confirmar |
| **OB-11** | **¿El registro con Google da el email por verificado y crea la cuenta ya activa?** | Google verifica el correo antes de emitir el token, así que exigir una segunda verificación sería redundante. **Propuesta: crear la cuenta ya `ACTIVE`.** Pendiente de confirmar |
| A-1 | ¿Caduca una cuenta que nunca se activa? | Cuentas muertas con datos de onboarding |

## Estado

**Especificación:** `APPROVED` (2026-09-24). `OB-11` resuelta. Lo que queda —duración del
token, caducidad de cuentas nunca activadas— son constantes y una política posterior.

**Implementación:** `PARTIAL`.

Hecho: `POST /api/v1/auth/activate`, el consumo del token, la idempotencia (`RN-3`), la
indistinguibilidad entre token inexistente y usado (`RN-5`), la distinción del caducado
(`RN-4`) y la publicación de `AccountActivated` (`RN-7`).

**Falta:**

- el reenvío ([`FEAT-USR-021`](FEAT-USR-021-resend-activation-email.md)), que es lo que
  `RN-4` ofrece al caducar.

**Cambio de contrato:** la operación devuelve `204` y **no una sesión**. El enlace se abre a
menudo en un dispositivo distinto de aquel en que se creó la cuenta, y emitir ahí una sesión
convertiría un enlace de correo en una credencial de acceso. `openapi/paths/auth.yaml` queda
actualizado.
