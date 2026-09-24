# Endpoints — `User`

> Estado: **esqueleto**. Las rutas son una propuesta; solo se consideran contrato cuando su
> funcionalidad esté en `APPROVED` y exista su esquema en `openapi/`.

## Resumen

| Método y ruta | `operationId` | Propósito | Funcionalidad | Estado |
|---|---|---|---|---|
| `POST /api/v1/auth/register` | `registerUser` | Registro con email y contraseña | FEAT-USR-001 | **Implementado** |
| `GET /auth/oauth/{provider}` | `startOAuth` | Iniciar autenticación externa. **Solo `google` en esta fase** | FEAT-USR-002/005 | PENDING |
| `POST /auth/oauth/{provider}/callback` | `completeOAuth` | Completar autenticación externa | FEAT-USR-002/005 | PENDING |
| `POST /api/v1/auth/activate` | `activateAccount` | Activar la cuenta con el token del correo | FEAT-USR-020 | **Implementado** |
| `POST /auth/activation/resend` | `resendActivationEmail` | Reenviar el correo de activación | FEAT-USR-021 | DRAFT |
| `POST /api/v1/auth/login` | `login` | Login con email y contraseña | FEAT-USR-004 | **Implementado** |
| `POST /api/v1/auth/refresh` | `refreshSession` | Renovar la sesión. El refresco **rota** | FEAT-USR-004 | **Implementado** |
| `POST /api/v1/auth/logout` | `logout` | Cerrar sesión | FEAT-USR-004 | **Implementado** |
| `POST /auth/password-reset` | `requestPasswordReset` | Solicitar recuperación | FEAT-USR-007 | PENDING |
| `POST /auth/password-reset/{token}` | `confirmPasswordReset` | Fijar nueva contraseña | FEAT-USR-007 | PENDING |
| `GET /api/v1/me/onboarding` | `getOnboardingState` | Paso en el que se retoma el onboarding | FEAT-USR-022 | **Implementado** |
| `PUT /api/v1/me/onboarding/profile` | `submitOnboardingProfile` | Paso 1: nombre y fecha de nacimiento | FEAT-USR-022 | **Implementado** |
| `PUT /api/v1/me/onboarding/genres` | `submitOnboardingGenres` | Paso 2: géneros de interés | FEAT-USR-023 | **Implementado** |
| `POST /me/onboarding/complete` | `completeOnboarding` | Cerrar el onboarding | FEAT-COM-016 | DRAFT |
| `GET /api/v1/genres` | `listGenres` | Catálogo de géneros. **Público** | FEAT-USR-023 | **Implementado** |
| `GET /legal/documents` | `getLegalDocuments` | Documentos legales vigentes | FEAT-USR-024 | DRAFT |
| `GET /me/legal-acceptances` | `getMyLegalAcceptances` | Qué aceptó el usuario y cuándo | FEAT-USR-024 | DRAFT |
| `GET /me/context` | `getSessionContext` | Contexto de sesión para el layout | FEAT-USR-027 | DRAFT |
| `GET /me` | `getCurrentUser` | Datos de la cuenta propia | FEAT-USR-008 | PENDING |
| `GET /me/profile` | `getMyProfile` | Perfil editable: nombre, biografía, foto | FEAT-USR-008 | DRAFT |
| `PUT /me/profile` | `updateMyProfile` | Editar nombre y biografía | FEAT-USR-008 | DRAFT |
| `PATCH /me` | `updateCurrentUser` | Editar datos personales | FEAT-USR-008 | PENDING |
| `POST /me/email-change` | `requestEmailChange` | Solicitar cambio de correo | FEAT-USR-040 | DRAFT |
| `POST /me/email-change/confirm` | `confirmEmailChange` | Confirmarlo desde el correo nuevo | FEAT-USR-040 | DRAFT |
| `PUT /me/password` | `changeMyPassword` | Cambiar o **establecer** contraseña | FEAT-USR-041 | DRAFT |
| `PUT /me/literary-preferences` | `updateLiteraryPreferences` | Preferencias literarias | FEAT-USR-009 | PENDING |
| `GET /me/settings` | `getAccountSettings` | Ajustes de cuenta | FEAT-USR-010/011 | PENDING |
| `GET /api/v1/me/privacy-settings` | `getMyPrivacySettings` | Ajustes de privacidad | FEAT-USR-038 | **Implementado** |
| `PUT /api/v1/me/privacy-settings` | `updateMyPrivacySettings` | Modificarlos | FEAT-USR-038 | **Implementado** |
| `GET /me/notification-preferences` | `getMyNotificationPreferences` | Preferencias de aviso por canal | FEAT-USR-039 | DRAFT |
| `PUT /me/notification-preferences` | `updateMyNotificationPreferences` | Modificarlas | FEAT-USR-039 | DRAFT |
| `PATCH /me/settings` | `updateAccountSettings` | MD y propuestas de LB | FEAT-USR-010/011 | PENDING |
| `DELETE /me` | `deleteMyAccount` | Eliminar cuenta | FEAT-USR-013 | BLOCKED |
| `GET /users/{userId}` | `getUserProfile` | Perfil público por identificador | FEAT-USR-014 | PENDING |
| `GET /profiles/{username}` | `getProfileByUsername` | Perfil por nombre de usuario o alias | FEAT-USR-035 | DRAFT |
| `PUT /me/username` | `changeUsername` | Cambiar el nombre de usuario | FEAT-USR-034 | DRAFT |
| `GET /usernames/{username}/availability` | `checkUsernameAvailability` | Comprobar si un nombre está libre | FEAT-USR-033 | DRAFT |
| `GET /me/profile` | `getMyProfile` | Cabecera del perfil propio | FEAT-USR-028 | DRAFT |
| `PATCH /me/profile` | `updateMyProfile` | Editar descripción y datos | FEAT-USR-028 | DRAFT |
| `PUT /me/profile/avatar` | `updateAvatar` | Subir o reencuadrar la foto de perfil | FEAT-USR-037 | DRAFT |
| `DELETE /me/profile/avatar` | `deleteAvatar` | Eliminar la foto de perfil | FEAT-USR-037 | DRAFT |
| `PUT /me/profile/cover` | `updateCover` | Cambiar portada | FEAT-USR-028 | DRAFT |
| `GET /users/{userId}/published-books` | `listPublishedBooks` | Obras publicadas de un autor | FEAT-USR-029 | DRAFT |
| `POST /me/published-books` | `addPublishedBook` | Añadir obra publicada | FEAT-USR-029 | DRAFT |
| `GET /users` | `searchUsers` | Buscar autores | FEAT-USR-017 | PENDING |
| `GET /authors/{userId}/page` | `getAuthorPage` | Página pública de autor | FEAT-USR-015 | PENDING |
| `PUT /me/author-page` | `updateAuthorPage` | Información de la página de autor | FEAT-USR-015 | PENDING |
| `PUT /me/author-page/theme` | `updateAuthorPageTheme` | Personalización visual | FEAT-USR-016 | PENDING |
| `POST /invitations` | `sendPlatformInvitation` | Invitar por email | FEAT-USR-018 | PENDING |
| `GET /me/invitations` | `listMyInvitations` | Invitaciones enviadas y su estado | FEAT-USR-018 | PENDING |

---

## `POST /api/v1/auth/register`

**`operationId`:** `registerUser` · **Funcionalidad:** [`FEAT-USR-001`](../../features/user/FEAT-USR-001-register-with-email.md)

### Propósito

Crear una cuenta con email y contraseña.

### Autorización

Público.

### Reglas aplicadas

`RN-1` a `RN-15` de `FEAT-USR-001`.

### Entrada

`email`, `password`, `acceptedLegalVersions` y opcionalmente `invitationToken`.

**No se pide nombre de usuario**: se asigna solo a partir del email (`RN-2`). Y **no se
acepta** ningún campo que determine créditos iniciales, rol o identidad: son decisiones del
servidor.

### Respuesta

`202 Accepted`, **sin cuerpo y siempre la misma**, exista o no ya una cuenta con ese correo.

Es la pieza que hace cumplir `RN-14`. Un `201` cuando el alta procede y otra cosa cuando no,
convierte el formulario de alta en un comprobador de quién tiene cuenta en la plataforma; y
devolver una sesión solo en el primer caso es el mismo oráculo con otro nombre.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `INVALID_VALUE` | 422 | El correo no tiene forma de correo |
| `WEAK_PASSWORD` | 422 | La contraseña incumple la política (`RN-3`) |
| `TERMS_NOT_ACCEPTED` | 422 | Falta la versión aceptada de alguno de los dos documentos |

**No existe un error de «correo ya registrado».** Ese caso devuelve `202`.

Los `422` se comprueban **antes** de mirar si el correo está libre, de modo que ninguno de
ellos dependa de si hay cuenta: describen lo que el cliente acaba de escribir, que ya sabe.

### Efectos

Publica `UserRegistered`, que provocará de forma asíncrona el envío del correo de activación
(`FEAT-NOT-008`). **`Credits` no lo consume**: no hay efecto de créditos en el registro, y la
cuenta de créditos no se crea hasta la activación (`FEAT-CRD-002` `RN-5`).

La cuenta se crea en `PENDING_ACTIVATION`: el usuario entra directamente al onboarding, pero
no puede ejecutar ninguna operación de escritura hasta activarla (`FEAT-USR-025`).

---

## `POST /api/v1/auth/activate`

**`operationId`:** `activateAccount` · **Funcionalidad:** [`FEAT-USR-020`](../../features/user/FEAT-USR-020-activate-account.md)

### Propósito

Llevar la cuenta de `PENDING_ACTIVATION` a `ACTIVE` mediante el token del correo.

### Autorización

**La autorización la da el token, no la sesión.** El usuario puede abrir el correo en otro
dispositivo o navegador.

### Entrada

El token viaja **en el cuerpo, no en la ruta**: así no queda en los logs del servidor ni en
el historial del navegador. El enlace del correo apunta a una página del frontend que lo
extrae y lo envía.

### Respuesta

`204 No Content`. La operación es idempotente: reactivar una cuenta ya activa devuelve lo
mismo.

**No devuelve sesión**, aunque el diseño inicial la contemplaba. El enlace se abre a menudo en
un dispositivo distinto de aquel en que se creó la cuenta, y emitir una sesión ahí convertiría
un enlace de correo en una credencial de acceso. Quien tenga su sesión abierta la conserva;
quien no, inicia sesión con normalidad.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `ACTIVATION_TOKEN_EXPIRED` | 410 | El token caducó; se ofrece reenviar |
| `INVALID_ACTIVATION_TOKEN` | 404 | Token inexistente, usado o manipulado. No se distingue cuál |

Que el caducado **sí** se distinga es deliberado: hay que poder ofrecer el reenvío, y decirlo
solo revela algo a quien ya tenía un enlace válido.

### Efectos

Publica `AccountActivated`, que desencadena el abono de los **10 créditos de bienvenida**
(`FEAT-CRD-002`) y habilita todas las operaciones de escritura (`FEAT-USR-025`).

---

## `PUT /api/v1/me/onboarding/profile`

**`operationId`:** `submitOnboardingProfile` · **Funcionalidad:** [`FEAT-USR-022`](../../features/user/FEAT-USR-022-onboarding-profile-data.md)

### Autorización

Sesión iniciada. **No exige cuenta activada**: es una de las dos escrituras exentas de
[`FEAT-USR-025`](../../features/user/FEAT-USR-025-block-writes-until-activation.md), y es la
razón de dejar entrar a alguien antes de que siga el enlace del correo.

### Entrada

`name` es **público** y `birthDate` es **privado**. Es la pareja de campos con visibilidad más
opuesta de toda la API, y conviene no mezclarlos nunca: ningún endpoint accesible por terceros
devuelve la fecha de nacimiento.

`birthDate` viaja como `YYYY-MM-DD` y debe ser una fecha **real y pasada**. `2000-02-30` se
rechaza: aceptarla la convertiría en el 1 de marzo y guardaría una fecha que nadie escribió.

### Respuesta

`204`. El onboarding avanza a `GENRES_PENDING`.

---

## `PUT /api/v1/me/onboarding/genres`

**`operationId`:** `submitOnboardingGenres` · **Funcionalidad:** [`FEAT-USR-023`](../../features/user/FEAT-USR-023-onboarding-select-genres.md)

### Reglas aplicadas

Mínimo **tres** géneros, todos del catálogo vigente. La validación es de servidor: que el
botón esté deshabilitado en el cliente es una cortesía, no una garantía.

### Entrada

La selección **sustituye** a la anterior. Los duplicados se normalizan —dos veces el mismo
género es un fallo del cliente, no una decisión— y por tanto **no cuentan para el mínimo**:
`['DRAMA','drama','DRAMA']` es un género, no tres.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `NOT_ENOUGH_GENRES` | 422 | Menos de tres géneros distintos |
| `UNKNOWN_GENRE` | 422 | Alguno no está en el catálogo. **Se nombra**, no se ignora |
| `ONBOARDING_STEP_OUT_OF_ORDER` | 409 | Falta el paso 1. La edad decide qué se le puede enseñar a esa persona |

### Efectos

Publica `LiteraryPreferencesUpdated` con la **selección entera**, no con lo que cambió: un
consumidor que aplicase deltas acabaría con un conjunto equivocado el primer mensaje que se
perdiera, y sin forma de notarlo.

---

## `POST /api/v1/auth/login`

**`operationId`:** `login` · **Funcionalidad:** [`FEAT-USR-004`](../../features/user/FEAT-USR-004-login-with-email.md)

### Propósito

Abrir una sesión: token de acceso de 15 minutos y token de refresco revocable
([`decision:0007`](../../decisions/0007-jwt-sessions.md)).

### Autorización

Pública. **Una cuenta sin activar inicia sesión con normalidad**: el onboarding ocurre antes
de activar, así que impedirlo dejaría fuera a todo el mundo justo después de registrarse. Lo
que no puede hacer es escribir (`FEAT-USR-025`).

### Respuesta

`200` con `accessToken`, `refreshToken` y `expiresIn`.

El token de acceso lleva `sub`, `iat`, `exp` y `jti`, **y nada más**. Un rol dentro de un token
sobrevive a su propia retirada hasta quince minutos, así que los permisos se consultan.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `INVALID_CREDENTIALS` | 401 | Contraseña incorrecta, cuenta inexistente, cuenta de Google sin contraseña o cuenta eliminada. **Los cuatro son indistinguibles** |
| `ACCOUNT_BLOCKED` | 403 | Cuenta bloqueada, con la contraseña correcta |
| — | 429 | Demasiados intentos, por dirección o por cuenta |

`ACCOUNT_BLOCKED` sí se distingue, y es deliberado: solo llega ahí quien ya ha acertado la
contraseña, y callarlo dejaría a una persona sancionada creyendo que la ha olvidado.

Cuando el correo no existe, el servidor **verifica igualmente una contraseña ficticia**. Sin
eso, la diferencia de tiempo delataría qué correos tienen cuenta y el cuidado con los mensajes
no serviría de nada.

---

## `POST /api/v1/auth/refresh`

**`operationId`:** `refreshSession` · **Funcionalidad:** [`FEAT-USR-004`](../../features/user/FEAT-USR-004-login-with-email.md)

### Propósito

Renovar la sesión. **El token de refresco rota**: el presentado queda revocado y se entrega
uno nuevo, así que sirve una sola vez.

### Efectos

Presentar un token **ya revocado** revoca **todas** las sesiones del usuario. Que dos partes
tengan el mismo token no tiene lectura benigna, y cerrar todo es la única acción que expulsa a
un ladrón sin saber cuál de los dos lo es. A quien pregunta no se le dice nada de esto: puede
ser el ladrón.

---

## `POST /api/v1/auth/logout`

**`operationId`:** `logout` · **Funcionalidad:** [`FEAT-USR-004`](../../features/user/FEAT-USR-004-login-with-email.md)

### Respuesta

`204`, siempre. Un token inexistente o ya revocado también: distinguirlos diría a quien
pregunta si ese token era real, y el resultado que pedía —quedar fuera— es cierto igualmente.

**El token de acceso sigue siendo válido hasta 15 minutos.** Un JWT no se puede retirar, y
conviene no prometer lo contrario en ninguna pantalla.

---

## Nota sobre el estado de la cuenta

**Toda operación de escritura de la API exige `AccountStatus: ACTIVE`.** Una cuenta en
`PENDING_ACTIVATION` recibe `403` con `code: ACCOUNT_NOT_ACTIVATED`, que la interfaz
distingue de un `403` genérico para poder ofrecer el reenvío del correo.

Las únicas excepciones son los pasos del onboarding y la gestión de la propia cuenta. Ver
[`FEAT-USR-025`](../../features/user/FEAT-USR-025-block-writes-until-activation.md).

---

## Nota sobre datos públicos y privados

El paso 1 del onboarding recoge dos datos con visibilidad opuesta:

| Campo | Visibilidad | Dónde aparece |
|---|---|---|
| `name` | **Público** | Perfiles, catálogo, muro, comentarios, rankings, sugerencias de autores |
| `birthDate` | **Privado** | Solo en `GET /me` |

`name` es el referente con el que se identifica a un usuario: al no existir nombre de
usuario, es el único nombre visible de una persona.

**Ningún endpoint accesible por terceros** —`GET /users/{userId}`, `GET /users`,
`GET /authors/{userId}/page`, las sugerencias de autores o cualquier listado— devuelve
`birthDate` ni el email. Es una regla de contrato, no una recomendación.

Las rutas y los enlaces de perfil usan siempre el `UserId`, nunca el nombre.

---

## Nota sobre el nombre de usuario en las rutas

`GET /profiles/{username}` es la **única** ruta que acepta un nombre de usuario. Todas las
demás referencias entre recursos de la API usan `UserId`.

El motivo es simple: el nombre de usuario **cambia** y el identificador no. Una relación
guardada por nombre se rompería en el primer cambio.

`GET /profiles/{username}` resuelve primero entre nombres en uso y después entre **alias
vigentes**, y devuelve siempre el nombre canónico actual para que el cliente pueda corregir
la URL. Ver [`FEAT-USR-035`](../../features/user/FEAT-USR-035-resolve-profile-by-username.md).

---

## Nota sobre el alta con proveedores externos

`POST /auth/oauth/{provider}/callback` **no crea una cuenta** si la petición no incluye la
aceptación de la versión vigente de las condiciones de uso y la política de privacidad:
responde `422` con `code: TERMS_NOT_ACCEPTED` y no persiste nada.

Iniciar sesión en una cuenta ya existente no lo exige. Ver
[`FEAT-USR-002`](../../features/user/FEAT-USR-002-register-with-google.md).


---

## Nota sobre las operaciones de la pantalla de Configuración

Las cinco pestañas de [`Configuración`](../../ui/settings.md) **no son un solo recurso**, y
conviene que la API lo refleje:

| Pestaña | Recurso | Por qué separado |
|---|---|---|
| Perfil | `/me/profile` | Datos públicos |
| Cuenta → correo | `/me/email-change` | Flujo en dos pasos con verificación, no una edición |
| Cuenta → contraseña | `/me/password` | Operación de seguridad; cierra sesiones |
| Cuenta → eliminar | `DELETE /me` | Proceso asíncrono entre contextos |
| Notificaciones | `/me/notification-preferences` | Preferencias, sin efecto en autorización |
| Privacidad | `/me/privacy-settings` | **Reglas de autorización** |

Un único `PATCH /me/settings` para todo sería más cómodo y ocultaría que **cambiar quién
puede ver tu perfil no se parece en nada a cambiar si quieres correos**: una afecta a lo que
el servidor deja hacer a terceros; la otra, a lo que se envía.

Tampoco conviene que el correo y la contraseña se guarden en la misma llamada que el resto:
son operaciones que exigen reautenticación y que cierran sesiones, y mezclarlas con un cambio
de biografía obliga a pedir la contraseña para cambiar la biografía o a no pedirla para
cambiar el correo.


---

## `GET` y `PUT /api/v1/me/privacy-settings`

**`operationId`:** `getMyPrivacySettings`, `updateMyPrivacySettings` · **Funcionalidad:**
[`FEAT-USR-038`](../../features/user/FEAT-USR-038-privacy-settings.md)

### Propósito

Quién puede ver mi perfil, comentar mis textos y mandarme mensajes. **No son preferencias de
visualización: son reglas de autorización**, y de ahí que las aplique el servidor en cada
operación y no el cliente escondiendo botones.

### Autorización

Solo el titular. **No hay endpoint para los ajenos**, y no es un olvido: saber que alguien
tiene el perfil restringido ya es información sobre esa persona. El efecto de esos ajustes se
ve en las respuestas de los demás endpoints.

No exige cuenta activada, al contrario que el resto de escrituras: cerrar la puerta es lo
último que conviene dificultarle a nadie.

### Reglas aplicadas

- Los tres comparten valores: `EVERYONE`, `FOLLOWERS`, `NOBODY`.
- Una cuenta nace con los tres en `EVERYONE`, escritos **en la misma transacción que la
  cuenta**: un ajuste ausente es lo que alguien acaba leyendo como «todo permitido».
- El `PUT` es **parcial**: lo que no se envía se queda como estaba.
- Un valor desconocido devuelve `422 UNKNOWN_AUDIENCE`. **Nunca se interpreta**: tomarlo por
  «todos» abriría una puerta que su dueño cree cerrada y por «nadie» cerraría una que cree
  abierta.
- `commentPermission` es un **techo** sobre la modalidad de cada obra. En `NOBODY` no se puede
  corregir ninguna obra propia, ni siquiera por quien ya tiene acceso concedido.
- No reescribe el pasado ni la modalidad guardada de cada obra, así que relajarlo devuelve a
  cada una la que su autor eligió.

### Qué no está todavía

«Visibilidad de actividad» **no se expone**: la etiqueta no dice qué es «actividad» (`S-16`),
y un interruptor que nada lee es peor que ninguno.

`messagePermission` se guarda y no se aplica, porque la mensajería no existe.

### Efectos

Publica `PrivacySettingsChanged` con los tres ajustes y **nada del perfil**.
