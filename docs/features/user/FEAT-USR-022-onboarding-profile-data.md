---
id: FEAT-USR-022
title: Onboarding paso 1 — nombre y fecha de nacimiento
context: User
concept: Onboarding
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - figma:1800-13778 (1470:9456, error en 1679:8998)
  - docs/ui/account-creation.md
endpoints: [GET /me/onboarding, PUT /me/onboarding/profile]
events: []
depends_on: [FEAT-USR-001]
updated: 2026-09-21
---

# FEAT-USR-022 — Onboarding paso 1: nombre y fecha de nacimiento

## Resumen

Primer paso del onboarding. Recoge el nombre y la fecha de nacimiento del usuario recién
registrado. Ambos campos llevan un tooltip que dice: *«Esta información solo será visible
para ti y el equipo de LectoresBeta.»*

Ese tooltip es el requisito más importante de la ficha: **son datos privados** y no pueden
aparecer en ninguna respuesta de la API dirigida a terceros.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Rellenar su propio onboarding | Sesión iniciada. **No requiere cuenta activada** |

## Precondiciones

- Cuenta creada, en `PENDING_ACTIVATION` o `ACTIVE`.
- Onboarding no completado.

## Reglas de negocio

- `RN-1` El nombre es obligatorio.
- `RN-2` La fecha de nacimiento es obligatoria y se envía en formato `YYYY-MM-DD`
  (la interfaz la presenta como `XX/XX/XXXX`).
- `RN-3` La fecha debe ser una fecha real y pasada. No se aceptan fechas futuras ni
  imposibles.
- `RN-4` **Nombre y fecha de nacimiento son datos privados.** No se devuelven en el perfil
  público (`FEAT-USR-014`) ni en ningún listado, sugerencia o resultado de búsqueda.
- `RN-5` El estado del onboarding se persiste al completar cada paso, de modo que el usuario
  pueda abandonarlo y retomarlo donde lo dejó.
- `RN-6` El paso 1 es obligatorio: no se puede avanzar sin completarlo.
- `RN-7` La validación del servidor es independiente de la del cliente. Un cliente
  manipulado no puede guardar una fecha inválida.

## Estado del onboarding

| Estado | Significado |
|---|---|
| `PROFILE_PENDING` | Falta el paso 1 |
| `GENRES_PENDING` | Falta el paso 2 |
| `SUGGESTIONS_PENDING` | Falta el paso 3, que es opcional |
| `COMPLETED` | Terminado |

Se expone en `GET /me/onboarding` para que el frontend sepa en qué paso retomar.

## Flujo principal

1. El usuario llega al paso 1 tras registrarse.
2. La pantalla le saluda por su alias (ver `OB-1`).
3. Introduce nombre y fecha de nacimiento.
4. El sistema valida ambos campos.
5. Los guarda y avanza el estado a `GENRES_PENDING`.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Fecha con formato inválido | Se rechaza. El diseño muestra «Formato incorrecto de fecha» | `422` con `code: INVALID_DATE_FORMAT` |
| Fecha futura o inexistente (30/02) | Se rechaza | `422` |
| Nombre vacío | Se rechaza | `422` |
| Edad por debajo del mínimo legal | **Sin definir** (`OB-7`) | Pendiente |
| Onboarding ya completado | Se rechaza; para cambiar los datos se usa `FEAT-USR-008` | `409` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Consultar estado del onboarding | `GET /me/onboarding` | `getOnboardingState` |
| Guardar datos del paso 1 | `PUT /me/onboarding/profile` | `submitOnboardingProfile` |

`PUT` y no `POST`: el paso es idempotente, reenviar los mismos datos no cambia el resultado.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `user` | `name`, `birth_date`, `onboarding_status` |

`birth_date` es dato personal: no se registra en logs y no se expone en la API pública.

## Diseño (Figma)

`1470:9456` (estado normal), `1679:8998` (error de fecha y tooltip).

Ver [`../../ui/account-creation.md`](../../ui/account-creation.md).

## Criterios de aceptación

- [ ] Guardar nombre y fecha válidos avanza el estado a `GENRES_PENDING`.
- [ ] Una fecha con formato incorrecto devuelve `422`.
- [ ] Una fecha futura devuelve `422`.
- [ ] Una fecha inexistente como `2000-02-30` devuelve `422`.
- [ ] Un nombre vacío devuelve `422`.
- [ ] `GET /users/{userId}` de otro usuario **no** devuelve `name` privado ni `birthDate`.
- [ ] Ningún endpoint accesible por terceros expone la fecha de nacimiento.
- [ ] El usuario puede completar este paso sin haber activado su cuenta.
- [ ] Tras cerrar sesión y volver, el onboarding se retoma en el paso 2.
- [ ] Un cliente que envíe una fecha inválida saltándose la validación de cliente recibe `422`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **OB-1** | ¿De dónde sale el alias con el que saluda la pantalla? | **Bloqueante.** Define si existe `username` y cuándo se fija |
| **OB-2** | ¿«Nombre» es el nombre real (privado) o el nombre público de autor? El tooltip dice privado, pero entonces falta definir qué nombre se ve en el perfil | Determina qué se expone en `GET /users/{userId}` |
| **OB-7** | ¿Hay edad mínima? ¿Se rechaza el registro por debajo de ella? | **Legal.** Si se pide la fecha de nacimiento, debe haber un motivo declarado |
| OB-10 | ¿Se persiste el paso a paso o el onboarding completo al final? | Asumido paso a paso en `RN-5`; confirmar |

## Estado

**Especificación:** `DRAFT`. `OB-1` y `OB-2` deben resolverse antes de `APPROVED`: afectan a
qué campos existen y cuáles son públicos.

**Implementación:** `TODO`.
