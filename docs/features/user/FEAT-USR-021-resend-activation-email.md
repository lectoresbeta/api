---
id: FEAT-USR-021
title: Reenviar el email de activación
context: User
concept: Account
actors: [Guest, User]
spec_status: APPROVED
impl_status: TODO
priority: P1
sources:
  - figma:1800-13778 (aviso lateral «¿No te ha llegado? Reenviar enlace»)
  - docs/ui/account-creation.md
endpoints: [POST /auth/activation/resend]
events: [ActivationEmailRequested]
depends_on: [FEAT-USR-020]
updated: 2026-09-24
---

# FEAT-USR-021 — Reenviar el email de activación

## Resumen

El aviso lateral que acompaña a los tres pasos del onboarding ofrece «¿No te ha llegado?
**Reenviar enlace**», junto con la advertencia de revisar la carpeta de spam.

Es una funcionalidad pequeña con una superficie de abuso desproporcionada: un endpoint que
envía correo a una dirección arbitraria es un vector de spam si no se limita.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Pedir el reenvío para su propia cuenta | Sesión iniciada y cuenta en `PENDING_ACTIVATION` |
| `Guest` | Pedir el reenvío indicando su email | Ver `RN-4` |

## Se puede pedir sin sesión

**Decidido** (`R-1`): basta con el correo. No hace falta tener la sesión abierta.

Sin ello, quien cierra el navegador antes de activar se queda atrapado: no puede entrar
—porque no ha activado— y no puede pedir el reenvío —porque no puede entrar—. Es un callejón
sin salida con una solución trivial.

- `RN-R1` La respuesta es **siempre la misma**, exista o no esa cuenta. Si dijera «ese correo
  no está registrado», el formulario sería un comprobador de quién tiene cuenta.
- `RN-R2` Si la cuenta ya está activada, **no se envía nada** y la respuesta no cambia.
- `RN-R3` Hay **limitación de frecuencia por correo y por origen**. Un formulario público que
  dispara envíos es un amplificador de spam con el dominio de la plataforma.

`RN-R1` y `RN-R2` van juntas: cualquier diferencia observable entre los tres casos —no existe,
existe sin activar, ya activada— filtra información.

## Reglas de negocio

- `RN-1` Cada reenvío **invalida el token anterior** y emite uno nuevo. Solo hay un token de
  activación vigente por cuenta.
- `RN-2` Hay un intervalo mínimo entre reenvíos. Propuesta: 60 segundos.
- `RN-3` Hay un máximo de reenvíos por cuenta y periodo. Propuesta: 5 en 24 horas.
- `RN-4` La respuesta es siempre la misma, exista o no la cuenta y esté o no activa. Si no,
  el endpoint sirve para averiguar qué emails están registrados.
- `RN-5` Una cuenta ya activa no genera correo, pero la respuesta no lo distingue (`RN-4`).
- `RN-6` El envío es asíncrono: la respuesta no espera a que el correo salga.

`RN-4` implica que la interfaz no puede decir «no existe esa cuenta». El diseño ya acierta
al limitarse a «¿No te ha llegado?».

## Flujo principal

1. El usuario pulsa «Reenviar enlace».
2. El sistema comprueba los límites de frecuencia.
3. Si la cuenta existe y está pendiente, invalida el token anterior y emite uno nuevo.
4. Publica el hecho para que `Notification` envíe el correo.
5. Devuelve la misma respuesta en todos los casos.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Cuenta ya activa | No se envía nada | `202`, indistinguible |
| Email no registrado | No se envía nada | `202`, indistinguible |
| Reenvío antes del intervalo mínimo | Se rechaza | `429` con `code: RESEND_TOO_SOON` y `Retry-After` |
| Superado el máximo diario | Se rechaza | `429` con `code: RESEND_LIMIT_REACHED` |

El `429` sí es distinguible, pero solo lo ve quien ya controla esa cuenta o ese email, así
que no filtra información útil a un atacante.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Reenviar activación | `POST /auth/activation/resend` | `resendActivationEmail` |

## Eventos

| Evento | Cuándo | Consumidores |
|---|---|---|
| `ActivationEmailRequested` | Se emite un token nuevo | `Notification` |

## Modelo de datos afectado

`account_activation_token`: se invalida el token vigente y se inserta el nuevo. Conviene
registrar los envíos para aplicar `RN-3`.

## Criterios de aceptación

- [ ] Un reenvío invalida el token anterior: el enlace del primer correo deja de funcionar.
- [ ] Dos reenvíos seguidos dentro del intervalo mínimo: el segundo devuelve `429`.
- [ ] Pedir el reenvío para un email no registrado devuelve lo mismo que para uno registrado.
- [ ] Pedir el reenvío para una cuenta ya activa no envía correo.
- [ ] Superar el máximo diario devuelve `429`.
- [ ] La respuesta no espera al envío del correo.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| OB-9 | ¿Cuáles son el intervalo y el máximo reales? | Las cifras propuestas son un punto de partida |
| ~~R-1~~ | ¿El reenvío se puede pedir sin sesión, solo con el email? | Si no, un usuario que cierre el navegador antes de activar se queda sin salida |

## Estado

**Especificación:** `APPROVED` (2026-09-24). `R-1` resuelta: el reenvío se pide sin sesión, con respuesta
indistinguible y limitación de frecuencia.

**Implementación:** `TODO`.
