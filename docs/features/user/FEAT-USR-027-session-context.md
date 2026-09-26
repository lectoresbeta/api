---
id: FEAT-USR-027
title: Contexto de sesión para el layout
context: User
concept: Account
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - docs/ui/app-layout-and-navigation.md
  - figma:1800-14717
endpoints: [GET /me/context]
events: []
depends_on: [FEAT-CRD-001, FEAT-NOT-009, FEAT-USR-020, FEAT-USR-026]
updated: 2026-09-24
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
| `credits.balance` | `Credits` | Un solo número, que puede ser **negativo** |
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
        ├──▶ contrato de consulta de Credits       → saldo
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
se entrega una corrección. Puede quedar obsoleto entre navegaciones.

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

- [x] Devuelve identidad, estados, créditos, no leídas y tours pendientes en una sola llamada.
- [x] `credits` es **un solo número**, que puede ser negativo.
- [x] No devuelve email ni fecha de nacimiento.
- [x] No permite consultar el contexto de otro usuario.
- [x] Si `Credits` no responde, devuelve el resto con el saldo marcado como no disponible.
- [x] Funciona con la cuenta en `PENDING_ACTIVATION` e indica ese estado.
- [x] La composición no accede a repositorios ni entidades de otros contextos.
- [x] Es de solo lectura: no provoca ninguna escritura.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| X-1 | ¿Cada cuánto se refresca en una sesión larga sin navegación? | El saldo puede cambiar sin que el usuario haga nada |
| X-2 | ¿Conviene un canal en tiempo real para el saldo y las notificaciones? | Mejoraría la experiencia; añade infraestructura |
| X-3 | ¿Debe incluir también los permisos efectivos del usuario? | Hoy no hacen falta: la autorización es por recurso |
| R-5 | ¿Qué saldo muestra el menú lateral? | Propuesta: el disponible |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `DONE` (2026-09-25), con dos desviaciones de la ficha que conviene ver.

**El saldo no llega por un contrato de `Credits`, sino por una copia.** La ficha dibuja tres
contratos de consulta, uno por contexto. `Notification` publica el suyo, `User` se pregunta a
sí mismo — y `Credits` **no publica contratos**, ni siquiera para leer: es regla dura de
`AGENTS.md` y de [`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md),
y una excepción «solo para consultar» abre la misma puerta que mañana alguien usa para otra
cosa. Así que se hace lo que el proyecto ya hace con el grafo de seguidores y con los
bloqueos: escuchar `CreditBalanceChanged` y guardar un número.

Tiene un coste declarado: el saldo **puede ir retrasado**. La propia ficha ya lo asumía en
«Frescura del saldo», y la fuente de verdad para gastar sigue siendo `GET /credits/balance`.
Un matiz que la copia sí resuelve: un hecho más viejo que lo ya sabido no retrocede el
número, porque la cola no promete orden.

**`credits` devuelve un solo número y no tres.** La ficha pedía total, disponible y retenido
citando [`decision:0004`](../../decisions/0004-credit-reservation-on-access-grant.md), pero
[`decision:0006`](../../decisions/0006-credit-system.md) eliminó las retenciones: hoy
«disponible» y «total» son el mismo número, y tres campos iguales serían tres formas de
equivocarse.

`pendingTours` viaja siempre, y de momento vacío: `FEAT-USR-026` no existe como modelo. Una
lista vacía es la respuesta correcta —no hay ninguno pendiente— y deja al cliente ya escrito.

`RN-7` vive en `NotificationSideloads`, en Infrastructure, porque de fallos y tiempos de
espera sabe Infrastructure y no el caso de uso. El handler solo sabe que `null` es una
respuesta posible.
