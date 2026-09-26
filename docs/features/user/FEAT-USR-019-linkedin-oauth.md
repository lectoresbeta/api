---
id: FEAT-USR-019
title: Registro y login con LinkedIn
context: User
concept: Authentication
actors: [Guest]
spec_status: APPROVED
impl_status: DEFERRED
priority: P3
sources:
  - figma:1800-13778 (1470:9482, bloque «o continúa»)
  - docs/ui/account-creation.md
endpoints: [GET /auth/oauth/linkedin, POST /auth/oauth/linkedin/callback]
events: [UserRegistered]
depends_on: [FEAT-USR-001]
updated: 2026-09-24
---

# FEAT-USR-019 — Registro y login con LinkedIn

## Resumen

El formulario de registro ofrece tres accesos sociales: Google, Facebook y **LinkedIn**.

> **Diferido.** En esta fase **solo se implementa Google** (`FEAT-USR-002`, `FEAT-USR-005`).
> Facebook y LinkedIn quedan para más adelante. La ficha se conserva porque el diseño ya
> contempla los tres botones y conviene no volver a razonarlo desde cero.

LinkedIn **no aparece en el documento de casos de uso**, que solo contempla Google y
Facebook. Aparece únicamente en el diseño del formulario de registro.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Guest` | Registrarse o iniciar sesión con su cuenta de LinkedIn | — |

## Reglas de negocio

- `RN-1` Un mismo email no puede dar lugar a dos cuentas por proveedores distintos. Si el
  email ya existe, hay que decidir entre vincular o rechazar (`U-2`).
- `RN-2` El `AuthProvider` de la cuenta se amplía con `LINKEDIN`.
- `RN-3` El usuario que llega por un proveedor social **hace el mismo onboarding** que el
  resto: el proveedor no aporta ni el nombre visible, ni la fecha de nacimiento, ni los
  géneros.
- `RN-4` Nunca se almacena la contraseña del proveedor. Solo el identificador externo.
- `RN-5` Si el proveedor confirma que el email está verificado, la cuenta puede crearse ya
  activa. **Por decidir** (`OB-11`).
- `RN-6` La aceptación de condiciones y privacidad sigue siendo obligatoria (`FEAT-USR-024`).
  El diseño actual no la recoge en esta vía: ver `T-4`.

## Flujo principal

1. El usuario pulsa «LinkedIn».
2. Se le redirige al proveedor y autoriza el acceso.
3. El proveedor devuelve el control con un código.
4. El sistema obtiene el perfil mínimo: identificador externo y email.
5. Si ya existe cuenta para ese proveedor e identificador, inicia sesión.
6. Si no existe, crea la cuenta y publica `UserRegistered` con `authProvider: LINKEDIN`.
7. El usuario entra en el onboarding.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| El usuario cancela en el proveedor | Vuelve al registro sin crear nada | — |
| El email ya tiene cuenta local | **Sin definir** (`U-2`) | Pendiente |
| El proveedor no devuelve email | Se rechaza: el email es necesario | `422` |
| Error del proveedor | Mensaje genérico, sin exponer el detalle técnico | `502` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Iniciar OAuth | `GET /auth/oauth/linkedin` | `startOAuth` con `provider=linkedin` |
| Completar OAuth | `POST /auth/oauth/linkedin/callback` | `completeOAuth` |

Los tres proveedores comparten endpoint con el proveedor como parámetro de ruta. Cada uno
tiene su adaptador en `Infrastructure` tras el puerto `OAuthProvider`; `Application` no sabe
cuál está en uso.

## Criterios de aceptación

- [ ] Un usuario nuevo puede registrarse con LinkedIn y acaba en el onboarding.
- [ ] Un usuario existente que ya se registró con LinkedIn inicia sesión, no crea otra cuenta.
- [ ] No se almacena ninguna credencial del proveedor.
- [ ] `UserRegistered` lleva `authProvider: LINKEDIN`.
- [ ] Añadir un cuarto proveedor no obliga a tocar `Application` ni `Domain`.
- [ ] El usuario que entra por LinkedIn ha aceptado las condiciones antes de tener cuenta.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| L-1 | ¿LinkedIn entra realmente en alcance? | **Aplazado:** fuera de esta fase |
| U-2 | ¿Se pueden vincular varios proveedores a la misma cuenta? ¿Qué pasa si el email ya existe? | Afecta a los tres proveedores |
| OB-11 | ¿El email del proveedor se da por verificado? | Evitaría el paso de activación |
| T-4 | ¿Dónde acepta las condiciones quien entra por un proveedor social? | **Hueco en el diseño actual** |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `DEFERRED`. Fuera del alcance de esta fase: solo se implementa Google.
Se retomará junto con Facebook (`FEAT-USR-003`, `FEAT-USR-006`), y entonces habrá que
resolver `U-2`, que les afecta a los tres.
