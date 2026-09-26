---
id: FEAT-USR-004
title: Iniciar sesión con email y contraseña
context: User
concept: Authentication
actors: [Guest, User]
spec_status: APPROVED
impl_status: DONE
priority: P0
sources:
  - docs/decisions/0007-jwt-sessions.md
  - _sources/use-cases.pdf#p3
endpoints: [POST /auth/login, POST /auth/refresh, POST /auth/logout]
events: []
depends_on: [FEAT-USR-001]
updated: 2026-09-23
---

# FEAT-USR-004 — Iniciar sesión con email y contraseña

## Resumen

El ciclo de vida completo de una sesión: abrirla, renovarla y cerrarla.

El mecanismo está decidido en [`decision:0007`](../../decisions/0007-jwt-sessions.md): un
**token de acceso JWT de 15 minutos** más un **token de refresco revocable**, que es el único
elemento de la sesión con estado. Esta ficha no lo vuelve a decidir; especifica cómo se
comporta la API.

Es la pieza que desbloquea todo lo autenticado: hoy nadie puede pasar del registro.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Guest` | Iniciar sesión y renovar | Con credenciales válidas, o con un token de refresco vigente |
| `User` | Cerrar sesión | Con su token de refresco |

## Reglas de negocio

- `RN-1` Las credenciales son **correo y contraseña**. El correo se normaliza igual que en el
  alta, así que `Ana@Ejemplo.com` entra en la cuenta de `ana@ejemplo.com`.
- `RN-2` **Credenciales incorrectas y cuenta inexistente dan exactamente la misma respuesta**:
  `401` con `code: INVALID_CREDENTIALS`. Nada distingue los dos casos.
- `RN-3` Cuando el correo no existe, el servidor **verifica igualmente una contraseña ficticia**
  antes de responder. Sin eso, la diferencia de tiempo entre «no existe» y «existe pero la
  contraseña falla» delata qué correos tienen cuenta, y `RN-2` no serviría de nada.
- `RN-4` Una cuenta **`PENDING_ACTIVATION` sí inicia sesión.** El onboarding ocurre antes de
  activar (`FEAT-USR-001` `RN-9`), así que impedirlo dejaría a todo el mundo fuera justo
  después de registrarse. Las escrituras siguen bloqueadas (`FEAT-USR-025`).
- `RN-5` Una cuenta **`BLOCKED` no inicia sesión**, y **sí se le dice**: `403` con
  `code: ACCOUNT_BLOCKED`. Solo llega ahí quien ya ha acertado la contraseña, así que no
  revela nada que no supiera, y callarlo dejaría a una persona sancionada creyendo que ha
  olvidado su contraseña (`FEAT-MOD-006`).
- `RN-6` Una cuenta **`DELETED` no inicia sesión**, y se comporta como credenciales
  incorrectas: la cuenta está anonimizada y no hay nadie a quien explicarle nada.
- `RN-7` Una cuenta **sin contraseña** —alta con Google (`FEAT-USR-002`)— responde como
  credenciales incorrectas. Decir «esa cuenta usa Google» delataría de nuevo qué correos
  existen.
- `RN-8` El token de acceso caduca a los **15 minutos** y lleva `sub`, `iat`, `exp` y `jti`.
  **Ni roles ni datos personales** (`decision:0007` `RN-4`).
- `RN-9` El token de refresco vive **30 días**, se guarda **cifrado** y es revocable.
- `RN-10` **El refresco rota**: usarlo revoca el presentado y entrega uno nuevo. Un token de
  refresco sirve una sola vez.
- `RN-11` **Presentar un token de refresco ya revocado revoca todas las sesiones del usuario.**
  Ver [abajo](#por-qué-reutilizar-un-token-revocado-cierra-todo).
- `RN-12` Cerrar sesión revoca el token de refresco presentado. **El token de acceso sigue
  siendo válido hasta 15 minutos**, y así hay que contarlo: prometer un corte inmediato que no
  ocurre es peor que asumir la ventana (`decision:0007`).
- `RN-13` El inicio de sesión está **limitado por frecuencia**, por dirección IP y por cuenta.
  Superado el límite, `429`.
- `RN-14` Ni la contraseña ni ninguno de los dos tokens aparecen en logs, trazas o métricas.

## Por qué reutilizar un token revocado cierra todo

`RN-10` y `RN-11` van juntas y son lo único de esta ficha que no se deduce de
`decision:0007`.

Con rotación, un token de refresco se usa una vez. Si alguien **roba** uno, hay dos
desenlaces, y en los dos queda un rastro inconfundible:

| Quién lo usa primero | Qué pasa después |
|---|---|
| El ladrón | La persona legítima presenta un token ya revocado |
| La persona legítima | El ladrón presenta un token ya revocado |

**Un token revocado presentado de nuevo solo ocurre si dos partes tienen el mismo token.** No
hay lectura benigna. Por eso la respuesta es cerrar todas las sesiones del usuario: es molesto
—hay que volver a entrar— y es la única acción que expulsa al ladrón sin saber cuál de los dos
es.

El caso que sí es benigno y hay que no confundir: un cliente que reintenta el mismo refresco
por un fallo de red. Se distingue porque el token **no está revocado** todavía, o porque la
respuesta anterior nunca llegó; por eso el límite se aplica a la reutilización de un token
**ya revocado**, no a la de uno vigente.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Iniciar sesión | `POST /api/v1/auth/login` | `login` |
| Renovar la sesión | `POST /api/v1/auth/refresh` | `refreshSession` |
| Cerrar sesión | `POST /api/v1/auth/logout` | `logout` |

Las tres son públicas: la autorización la dan las credenciales o el token de refresco, no una
sesión previa.

Respuesta de `login` y `refresh`:

```json
{ "accessToken": "...", "refreshToken": "...", "expiresIn": 900 }
```

`logout` devuelve `204`.

## Flujos alternativos y errores

| Caso | Respuesta |
|---|---|
| Contraseña incorrecta, cuenta inexistente, cuenta sin contraseña o `DELETED` | `401` con `INVALID_CREDENTIALS` |
| Cuenta `BLOCKED` con credenciales correctas | `403` con `ACCOUNT_BLOCKED` |
| Demasiados intentos | `429` |
| Token de refresco inexistente, caducado o revocado | `401` con `INVALID_REFRESH_TOKEN` |
| Token de refresco revocado **presentado de nuevo** | `401`, y además se revocan todas las sesiones (`RN-11`) |
| `logout` con un token ya revocado | `204`. Cerrar algo que ya está cerrado no es un error |

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `refresh_token` | Una fila por sesión. Se marca revocada al rotar, al cerrar sesión y al detectar reutilización |

Ninguna tabla nueva: la entidad y su tabla ya existen.

## Criterios de aceptación

- [ ] Credenciales correctas devuelven token de acceso, token de refresco y `expiresIn`.
- [ ] El token de acceso **no contiene roles ni datos personales** (`decision:0007`).
- [ ] Una cuenta sin activar inicia sesión con normalidad.
- [ ] Una cuenta `BLOCKED` con la contraseña correcta recibe `403` con `ACCOUNT_BLOCKED`.
- [ ] Una contraseña incorrecta y un correo inexistente devuelven respuestas idénticas.
- [ ] Un correo inexistente tarda lo mismo, en el mismo orden de magnitud, que uno existente.
- [ ] Una cuenta dada de alta con Google recibe `INVALID_CREDENTIALS`, no un mensaje distinto.
- [ ] El refresco devuelve un par nuevo y **revoca el token presentado**.
- [ ] El token de refresco anterior deja de servir inmediatamente.
- [ ] Reutilizar un token de refresco revocado revoca **todas** las sesiones del usuario.
- [ ] `logout` revoca el token presentado y es idempotente.
- [ ] Ningún log contiene la contraseña ni ninguno de los dos tokens.
- [ ] El token de refresco se guarda cifrado: la tabla no permite iniciar sesión a quien la lea.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| `S-7` | ¿Debería el usuario poder ver y cerrar sus sesiones abiertas? | `refresh_token` ya guarda el `user_agent` para eso. Es una funcionalidad aparte |
| `S-8` | ¿Cuál es el límite exacto de intentos? | `RN-13` fija que existe; el número se calibra con datos reales |

## Estado

**Especificación:** `APPROVED` (2026-09-23). El mecanismo viene de
[`decision:0007`](../../decisions/0007-jwt-sessions.md); lo que esta ficha añade es la
rotación del refresco (`RN-10`), su detección de reutilización (`RN-11`), el comportamiento
ante cada estado de cuenta (`RN-4` a `RN-7`) y la defensa contra el ataque por tiempos
(`RN-3`).

**Implementación:** `DONE`.

Los tres endpoints, la rotación del refresco, la detección de reutilización, los dos
limitadores de frecuencia y el comportamiento ante cada estado de cuenta. Cubierto por
`tests/Functional/User/SessionTest.php`, incluido el test que
[`decision:0007`](../../decisions/0007-jwt-sessions.md) pide explícitamente: **el token no
lleva roles ni datos personales**, solo `sub`, `iat`, `exp` y `jti`.

Un matiz sobre los criterios: la defensa contra el ataque por tiempos (`RN-3`) **está
implementada** —se verifica una contraseña ficticia cuando el correo no existe— pero no tiene
test automático. Una aserción sobre tiempos es inestable por naturaleza y fallaría en CI por
motivos que no son el código.

**Lo que esta ficha no cubre y sigue pendiente:** invalidar los tokens de refresco al cambiar
contraseña o correo ([`decision:0007`](../../decisions/0007-jwt-sessions.md) `RN-3`), que es
trabajo de [`FEAT-USR-041`](FEAT-USR-041-change-password.md) y
[`FEAT-USR-040`](FEAT-USR-040-change-email.md).
