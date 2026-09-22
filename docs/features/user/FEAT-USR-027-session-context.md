---
id: FEAT-USR-027
title: Contexto de sesión para el layout
context: User
concept: Account
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - docs/ui/app-layout-and-navigation.md
  - figma:1800-14717
endpoints: [GET /me/context]
events: []
depends_on: [FEAT-CRD-001, FEAT-NOT-009, FEAT-USR-020, FEAT-USR-026]
updated: 2026-09-22
---

# FEAT-USR-027 — Contexto de sesión para el layout

## Resumen

Endpoint único que devuelve los datos que **el layout** necesita en cualquier pantalla:
identidad, saldo de créditos, notificaciones sin leer, estado de la cuenta y estado del tour.

Sin él, cada navegación dispararía cinco peticiones a cuatro contextos distintos para pintar
un menú lateral que no cambia.

## Qué devuelve

| Dato | Origen | Para qué |
|---|---|---|
| `userId`, `name`, `avatarUrl` | `User` | Cabecera y saludo |
| `accountStatus` | `User` | Avisar si la cuenta está sin activar (`FEAT-USR-025`) |
| `onboardingStatus` | `User` | Retomar el onboarding si quedó a medias |
| `credits.available` | `Credits` | Bloque del menú lateral |
| `credits.balance`, `credits.held` | `Credits` | Contexto del saldo retenido |
| `unreadNotifications` | `Notification` | Punto de la campana |
| `pendingTours` | `User` | Mostrar el tour (`FEAT-USR-026`) |

`credits` devuelve los **tres** números y no uno solo. Con
[`decision:0004`](../../decisions/0004-credit-reservation-on-access-grant.md) el saldo
disponible y el total son distintos, y un único campo llamado `balance` sería una invitación
a mostrar el número equivocado.

## Cómo se construye sin romper las fronteras

El endpoint cruza cuatro bounded contexts, así que **no puede ser un caso de uso de ninguno
de ellos**. Se resuelve como composición explícita:

```text
GET /me/context   (Infrastructure)
        │
        ├──▶ contrato de consulta de User          → identidad y estados
        ├──▶ contrato de consulta de Credits       → saldo, retenido, disponible
        └──▶ contrato de consulta de Notification  → no leídas
```

Reglas que lo mantienen legítimo:

- `RN-1` Cada contexto expone un **contrato de consulta explícito** que devuelve un DTO, no
  entidades ni repositorios.
- `RN-2` La composición vive en `Infrastructure` y no contiene lógica de negocio: solo
  ensambla.
- `RN-3` Ningún contexto conoce a los demás. El que compone los conoce a todos, y es el único
  que puede.
- `RN-4` Es de **solo lectura**. Un endpoint de contexto que modificara algo sería un lugar
  pésimo donde esconder efectos.

Es el caso que `AGENTS.md` contempla como *«un servicio de integración a nivel de
aplicación»*: comunicación síncrona genuinamente necesaria, con contrato explícito.

## Reglas de negocio

- `RN-5` Solo devuelve datos del usuario autenticado. No admite consultar el contexto de otro.
- `RN-6` No incluye datos privados innecesarios: ni email, ni fecha de nacimiento.
- `RN-7` **Degrada en lugar de fallar.** Si `Credits` o `Notification` no responden, el
  endpoint devuelve el resto con el campo correspondiente marcado como no disponible.
- `RN-8` Funciona con la cuenta en `PENDING_ACTIVATION`: es de lectura y justo entonces hace
  falta para avisar del estado.

`RN-7` importa: el saldo llega de un contexto deliberadamente desacoplado que puede fallar de
forma independiente. Que no se pueda pintar el saldo no debería impedir navegar.

## Frescura del saldo

El saldo cambia por **eventos asíncronos**: alguien comenta una obra, se concede un acceso,
se confirma una retención. Puede quedar obsoleto entre navegaciones.

| Opción | Valoración |
|---|---|
| Consultar en cada navegación | Sencillo y suficiente para empezar |
| Cachear por sesión con invalidación por evento | Más eficiente, más piezas |
| Notificar el cambio al cliente en vivo | Lo mejor para el usuario, requiere canal en tiempo real |

**Propuesta: consultar en cada navegación** y revisarlo si se convierte en un problema. El
endpoint es pequeño y sus consultas son por clave primaria.

Lo que **no** debe hacerse es cachearlo sin invalidación: un saldo obsoleto en una pantalla
sobre dinero interno genera desconfianza inmediata.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Contexto de sesión | `GET /me/context` | `getSessionContext` |

Respuesta `200`. Sin sesión, `401`.

## Criterios de aceptación

- [ ] Devuelve identidad, estados, créditos, no leídas y tours pendientes en una sola llamada.
- [ ] `credits` incluye los tres números: total, retenido y disponible.
- [ ] No devuelve email ni fecha de nacimiento.
- [ ] No permite consultar el contexto de otro usuario.
- [ ] Si `Credits` no responde, devuelve el resto con el saldo marcado como no disponible.
- [ ] Funciona con la cuenta en `PENDING_ACTIVATION` e indica ese estado.
- [ ] La composición no accede a repositorios ni entidades de otros contextos.
- [ ] Es de solo lectura: no provoca ninguna escritura.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| X-1 | ¿Cada cuánto se refresca en una sesión larga sin navegación? | El saldo puede cambiar sin que el usuario haga nada |
| X-2 | ¿Conviene un canal en tiempo real para el saldo y las notificaciones? | Mejoraría la experiencia; añade infraestructura |
| X-3 | ¿Debe incluir también los permisos efectivos del usuario? | Hoy no hacen falta: la autorización es por recurso |
| R-5 | ¿Qué saldo muestra el menú lateral? | Propuesta: el disponible |

## Estado

**Especificación:** `DRAFT`. El contenido está definido; falta la estrategia de frescura.

**Implementación:** `TODO`.
