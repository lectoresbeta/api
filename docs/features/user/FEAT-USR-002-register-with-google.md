---
id: FEAT-USR-002
title: Registro con cuenta de Google
context: User
concept: Authentication
actors: [Guest]
spec_status: APPROVED
impl_status: DONE
priority: P0
sources:
  - _sources/use-cases.pdf#p3
  - figma:1800-13778 (1470:9482, bloque «o continúa»)
  - conversation:2026-09-21
endpoints: [GET /auth/oauth/google, POST /auth/oauth/google/callback]
events: [UserRegistered, AccountActivated]
depends_on: [FEAT-USR-024]
updated: 2026-09-25
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

## La cuenta nace activada

**El alta con Google marca el correo como verificado** (`OB-11`, resuelta). No se envía correo
de activación y la cuenta entra directamente en `ACTIVE`.

| | Alta con email | **Alta con Google** |
|---|---|---|
| Estado inicial | `PENDING_ACTIVATION` | **`ACTIVE`** |
| Correo de activación | Sí | **No** |
| Créditos de bienvenida | Al activar | **En el alta** |
| Puede escribir | Tras activar | **De inmediato** |

El motivo es sencillo: Google ya ha comprobado que esa dirección pertenece a quien la usa.
Mandar un correo de activación sería **pedirle al usuario que demuestre algo ya demostrado**, y
la única consecuencia sería perder gente en un paso que no aporta seguridad.

Lo que no cambia: la **aceptación legal sigue siendo obligatoria** (`T-4`). Sin ella no se crea
la cuenta, venga de donde venga.

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
- `RN-6` Si el email devuelto por Google ya tiene cuenta local, **se vincula solo si Google
  afirma que ese correo está verificado** (`email_verified`). Sin esa afirmación se rechaza
  con `409 EMAIL_ALREADY_REGISTERED` y la salida es entrar con la contraseña.
- `RN-7` El usuario que entra por Google hace **el mismo onboarding** que el resto: el
  proveedor no aporta el nombre público, ni la fecha de nacimiento, ni los géneros.
- `RN-8` Google entrega el email ya verificado, por lo que la cuenta se crea directamente en
  `ACTIVE` y se le abonan sus créditos de bienvenida. **Confirmada** (`OB-11`).
- `RN-9` Vincular **no retira la contraseña** de la cuenta que ya existía. Quitársela a quien
  ya entraba con ella sería cerrarle la puerta que usa por haber probado otra, y no protege
  nada: quien acaba de demostrar que controla ese correo podría restablecerla en un minuto.

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
   que abona los 10 créditos de bienvenida.
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
| Completar OAuth | `POST /auth/oauth/{provider}/callback` | `completeOAuth` |

`startOAuth` devuelve la dirección **en JSON y no una redirección**: quien saca a la persona
de la aplicación es el cliente —que además sabe adónde volver—, y una aplicación móvil no
puede seguir una redirección de la API.

Viaja con un `state` impredecible que **el cliente guarda y compara** con el que devuelva el
proveedor. Lo compara el cliente y no el servidor: esta API no tiene sesión de navegador donde
atarlo, y un `state` que el servidor firmara sin atarlo a un navegador concreto parecería una
comprobación sin serlo (`U-3`).

`completeOAuth` responde `201` cuando ha creado la cuenta y `200` cuando solo ha abierto
sesión, y devuelve `isNewAccount` y `linkedToExistingAccount` para que el cliente sepa adónde
mandar a la persona sin deducirlo del estado del onboarding, que es un detalle que puede
cambiar.

El endpoint de callback admite los datos de aceptación legal. Su ausencia solo es un error
cuando la operación implicaría **crear** una cuenta.

La ruta usa el proveedor como parámetro, de modo que Facebook y LinkedIn encajen sin cambiar
el contrato cuando se retomen. Cada uno tiene su adaptador en `Infrastructure` tras el puerto
`OAuthProvider`.

## Eventos

| Evento | Cuándo | Consumidores |
|---|---|---|
| `UserRegistered` | Se crea la cuenta | `Credits` (cuenta con saldo 0), `Notification` |
| `AccountActivated` | Si la cuenta nace activa por `RN-8` | `Credits` (+10), `Notification`, `Feedback` |

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `user` | Nuevo registro con `auth_provider = GOOGLE` y el identificador externo |
| `legal_acceptance` | Aceptación con versión y fecha |

Índice único sobre (`auth_provider`, `external_id`).

## Criterios de aceptación

- [x] Un usuario nuevo puede registrarse con Google y acaba en el onboarding.
- [x] **No se crea ninguna cuenta si falta la aceptación legal**: responde `422` y no persiste nada.
- [x] La aceptación guarda la versión de cada documento y su fecha, igual que en el registro con email.
- [x] Un usuario existente que inicia sesión con Google **no** vuelve a aceptar los documentos.
- [x] Registrarse dos veces con la misma cuenta de Google no crea dos cuentas.
- [x] No se almacena ninguna credencial del proveedor.
- [x] El usuario de Google hace el mismo onboarding que el de email.
- [x] Añadir un proveedor nuevo no obliga a tocar `Application` ni `Domain`.

Están en `tests/Functional/User/SignInWithGoogleTest.php`, que sustituye el proveedor por uno
de mentira. **Que baste con eso es el último criterio**: si añadir un proveedor obligara a
tocar `Application`, esa sustitución no sería posible.

## Decisiones tomadas al implementar

| Decisión | Por qué |
|---|---|
| Un servicio `OpenAccount` compartido por las dos puertas de alta | Hay dos caminos para crear una cuenta y lo que ocurre detrás tiene que ser lo mismo —ajustes de privacidad, constancia legal, `UserRegistered`—. Escrito dos veces, el día que aparezca un paso nuevo estará en uno solo |
| El `id_token` de Google se lee **sin verificar su firma** | No llega por el navegador sino de una respuesta directa del endpoint de Google sobre TLS, autenticada con el `client_secret`. Es el caso que OpenID Connect Core 3.1.3.7 exime expresamente. Verificarla exigiría descargar y cachear las claves públicas de Google a cambio de nada |
| Se piden solo los permisos `openid email` | El nombre, la fecha de nacimiento y los géneros los pone la persona en el onboarding (`RN-7`). Pedir el perfil sería pedir datos que no se van a usar |
| `prompt=select_account` | Sin eso, quien tiene varias cuentas de Google entra siempre con la última, y descubrirlo cuesta una cuenta duplicada |
| `AuthProvider::LOCAL` pasa a significar «ningún proveedor enlazado» | Desde `RN-9`, «entra con contraseña» y «no tiene proveedor» dejan de ser lo mismo. Quien quiera saber si alguien puede entrar con contraseña mira si tiene hash, que es la pregunta de verdad |
| Un `FailureKind::UPSTREAM_FAILED` nuevo, que responde `502` | «Algo de lo que dependemos no ha contestado» no es culpa de quien llama ni de lo que pidió: no hay nada que corregir y sí algo que reintentar. Era el único modo de dar el `502` que pide la ficha sin saltarse el camino por el que pasan todos los errores |
| El `callback` está limitado por dirección | Aquí no hay contraseña que probar, pero cada llamada dispara una petición saliente a Google con nuestras credenciales |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| T-4 | ¿Dónde acepta las condiciones quien entra con Google? | **Resuelto en backend:** sin aceptación no hay cuenta. Falta elegir A o B en la interfaz |
| **OB-11** | ¿La cuenta creada con Google nace `ACTIVE`, dado que Google ya verifica el correo? | Si no, se pide verificar dos veces lo mismo, y hasta entonces el usuario no puede escribir ni tiene créditos |
| U-2 | ¿Se pueden vincular varios proveedores a la misma cuenta? | **Parcialmente resuelta:** qué pasa si el email ya existe lo decide `RN-6`. Lo que sigue abierto es **varios a la vez**: hoy la fila guarda un proveedor y un identificador externo, así que enlazar Google sustituye a lo que hubiera. Con un solo proveedor implementado no se nota; con Facebook sí, y entonces harán falta una tabla aparte y una migración |
| U-3 | ¿Debería el servidor comprobar el `state` en vez de dejárselo al cliente? | Haría falta guardarlo atado a un navegador. Firmarlo sin atarlo parecería una comprobación sin serlo, y por eso hoy no se hace |
| T-1 | ¿Dónde viven los textos legales y cómo se versionan? | `FEAT-USR-024` |

## Estado

**Especificación:** `APPROVED` (2026-09-24). `OB-11` resuelta: la cuenta nace activada. Lo
que queda —vincular varios proveedores, dónde viven los textos legales— no impide
implementar el alta.

**Implementación:** `DONE` (2026-09-25). Requiere tres variables de entorno nuevas —
`GOOGLE_OAUTH_CLIENT_ID`, `GOOGLE_OAUTH_CLIENT_SECRET` y `GOOGLE_OAUTH_REDIRECT_URI`— y
**ninguna credencial está en el repositorio**: `.env` las declara vacías y quien despliegue las
pone en el entorno. Vacías, el botón de Google no funciona y nada más de la aplicación se
entera.

Lo que queda por decidir es de interfaz (`T-4`: casilla previa o pantalla intermedia) y no
impide nada: la regla de backend cierra el hueco venga la aceptación por donde venga.
