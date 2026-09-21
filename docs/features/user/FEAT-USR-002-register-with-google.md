---
id: FEAT-USR-002
title: Registro con cuenta de Google
context: User
concept: Authentication
actors: [Guest]
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - _sources/use-cases.pdf#p3
  - figma:1800-13778 (1470:9482, bloque «o continúa»)
  - conversation:2026-09-21
endpoints: [GET /auth/oauth/google, POST /auth/oauth/google/callback]
events: [UserRegistered, AccountActivated]
depends_on: [FEAT-USR-024]
updated: 2026-09-21
---

# FEAT-USR-002 — Registro con cuenta de Google

## Resumen

Alta en la plataforma usando una cuenta de Google, sin crear contraseña. Es el **único
proveedor externo de esta fase**: Facebook (`FEAT-USR-003`) y LinkedIn (`FEAT-USR-019`)
quedan diferidos.

El registro por Google **exige aceptar las condiciones de uso y la política de privacidad**
igual que el registro con email. Sin esa aceptación no se crea la cuenta.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Guest` | Registrarse con su cuenta de Google | Aceptando los documentos legales |

## Reglas de negocio

- `RN-1` **No se crea ninguna cuenta sin aceptación de los documentos legales vigentes.**
  Es la misma exigencia que en el registro con email (`FEAT-USR-024`), y se aplica aunque
  el proveedor externo ya haya autenticado a la persona.
- `RN-2` La aceptación se registra con la versión de cada documento y su fecha, igual que en
  el registro con email. Un `accepted: true` sin versión no demuestra nada.
- `RN-3` La autenticación de Google acredita **quién es** la persona, no **qué acepta**. Son
  dos cosas distintas y el proveedor solo resuelve la primera.
- `RN-4` **Iniciar sesión con una cuenta ya existente no exige volver a aceptar nada**
  (`FEAT-USR-005`). La aceptación se pide una vez, al crear la cuenta.
- `RN-5` No se almacena ninguna credencial de Google. Solo el identificador externo y el
  email.
- `RN-6` Si el email devuelto por Google ya tiene cuenta local, hay que decidir entre
  vincular o rechazar: sin resolver (`U-2`).
- `RN-7` El usuario que entra por Google hace **el mismo onboarding** que el resto: el
  proveedor no aporta el nombre público, ni la fecha de nacimiento, ni los géneros.
- `RN-8` Google entrega el email ya verificado, por lo que la cuenta puede crearse
  directamente en `ACTIVE`. **Propuesta pendiente de confirmar** (`OB-11`).

`RN-3` es la razón de fondo de toda la ficha: es fácil dar por hecho que «entrar con Google»
sustituye al registro completo, y no es así. Google dice que esa persona controla ese correo;
no dice que haya leído nada.

## Dónde se recoge la aceptación

El diseño actual no la recoge: la casilla está bajo el formulario de email y los botones
sociales quedan fuera.

La regla de backend es la que cierra el hueco, con independencia de cómo se resuelva la
interfaz:

> `POST /auth/oauth/google/callback` **no crea una cuenta nueva** si la petición no incluye
> la aceptación de la versión vigente de ambos documentos. Responde `422` con
> `code: TERMS_NOT_ACCEPTED` y no persiste nada.

Para la interfaz hay dos caminos:

| Opción | Cómo funciona | Valoración |
|---|---|---|
| **A. Casilla previa** | La casilla del formulario gobierna también los botones sociales: sin marcarla no se puede pulsar «Google» | Simple, pero el estado tiene que sobrevivir al viaje de ida y vuelta al proveedor |
| **B. Pantalla intermedia** | Al volver de Google, si es una cuenta nueva, se muestra una pantalla que pide la aceptación antes de crearla | **Recomendada.** No depende de conservar estado durante la redirección, y no molesta a quien solo inicia sesión |

La opción B además distingue de forma natural el alta del login, que es justo lo que pide
`RN-4`.

## Flujo principal

1. El usuario pulsa «Google».
2. Se le redirige al proveedor y autoriza el acceso.
3. El proveedor devuelve el control con un código.
4. El sistema obtiene el identificador externo y el email.
5. Si ya existe cuenta para ese proveedor e identificador, **inicia sesión** y termina aquí
   (`FEAT-USR-005`).
6. Si no existe, **antes de crear nada** se comprueba la aceptación de los documentos
   legales. Si falta, se responde `422` y el frontend muestra la pantalla intermedia.
7. Con la aceptación presente: se crea la cuenta, se registra la aceptación con versión y
   fecha, y se publica `UserRegistered`.
8. Si se confirma `RN-8`, la cuenta nace `ACTIVE` y se publica también `AccountActivated`,
   que abona los 20 créditos de bienvenida.
9. El usuario entra en el onboarding.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Cuenta nueva sin aceptación legal | **No se crea la cuenta** | `422` con `code: TERMS_NOT_ACCEPTED` |
| El usuario cancela en Google | Vuelve al registro sin crear nada | — |
| Google no devuelve email | Se rechaza: el email es imprescindible | `422` |
| El email ya tiene cuenta local | Sin definir (`U-2`) | Pendiente |
| Error del proveedor | Mensaje genérico, sin exponer el detalle técnico | `502` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Iniciar OAuth | `GET /auth/oauth/google` | `startOAuth` |
| Completar OAuth | `POST /auth/oauth/google/callback` | `completeOAuth` |

El endpoint de callback admite los datos de aceptación legal. Su ausencia solo es un error
cuando la operación implicaría **crear** una cuenta.

La ruta usa el proveedor como parámetro, de modo que Facebook y LinkedIn encajen sin cambiar
el contrato cuando se retomen. Cada uno tiene su adaptador en `Infrastructure` tras el puerto
`OAuthProvider`.

## Eventos

| Evento | Cuándo | Consumidores |
|---|---|---|
| `UserRegistered` | Se crea la cuenta | `Credits` (cuenta con saldo 0), `Notification` |
| `AccountActivated` | Si la cuenta nace activa por `RN-8` | `Credits` (+20), `Notification`, `Feedback` |

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `user` | Nuevo registro con `auth_provider = GOOGLE` y el identificador externo |
| `legal_acceptance` | Aceptación con versión y fecha |

Índice único sobre (`auth_provider`, `external_id`).

## Criterios de aceptación

- [ ] Un usuario nuevo puede registrarse con Google y acaba en el onboarding.
- [ ] **No se crea ninguna cuenta si falta la aceptación legal**: responde `422` y no persiste nada.
- [ ] La aceptación guarda la versión de cada documento y su fecha, igual que en el registro con email.
- [ ] Un usuario existente que inicia sesión con Google **no** vuelve a aceptar los documentos.
- [ ] Registrarse dos veces con la misma cuenta de Google no crea dos cuentas.
- [ ] No se almacena ninguna credencial del proveedor.
- [ ] El usuario de Google hace el mismo onboarding que el de email.
- [ ] Añadir un proveedor nuevo no obliga a tocar `Application` ni `Domain`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| T-4 | ¿Dónde acepta las condiciones quien entra con Google? | **Resuelto en backend:** sin aceptación no hay cuenta. Falta elegir A o B en la interfaz |
| **OB-11** | ¿La cuenta creada con Google nace `ACTIVE`, dado que Google ya verifica el correo? | Si no, se pide verificar dos veces lo mismo, y hasta entonces el usuario no puede escribir ni tiene créditos |
| U-2 | ¿Se pueden vincular varios proveedores a la misma cuenta? ¿Qué pasa si el email ya existe? | Afecta también a Facebook y LinkedIn cuando se retomen |
| T-1 | ¿Dónde viven los textos legales y cómo se versionan? | `FEAT-USR-024` |

## Estado

**Especificación:** `DRAFT`. La regla legal está cerrada en backend. Faltan `OB-11` y la
elección de interfaz entre las opciones A y B.

**Implementación:** `TODO`.
