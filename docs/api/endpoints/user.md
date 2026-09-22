# Endpoints — `User`

> Estado: **esqueleto**. Las rutas son una propuesta; solo se consideran contrato cuando su
> funcionalidad esté en `APPROVED` y exista su esquema en `openapi/`.

## Resumen

| Método y ruta | `operationId` | Propósito | Funcionalidad | Estado |
|---|---|---|---|---|
| `POST /auth/register` | `registerUser` | Registro con email y contraseña | FEAT-USR-001 | DRAFT |
| `GET /auth/oauth/{provider}` | `startOAuth` | Iniciar autenticación externa. **Solo `google` en esta fase** | FEAT-USR-002/005 | PENDING |
| `POST /auth/oauth/{provider}/callback` | `completeOAuth` | Completar autenticación externa | FEAT-USR-002/005 | PENDING |
| `POST /auth/activate` | `activateAccount` | Activar la cuenta con el token del correo | FEAT-USR-020 | DRAFT |
| `POST /auth/activation/resend` | `resendActivationEmail` | Reenviar el correo de activación | FEAT-USR-021 | DRAFT |
| `POST /auth/login` | `login` | Login con email y contraseña | FEAT-USR-004 | PENDING |
| `POST /auth/logout` | `logout` | Cerrar sesión | FEAT-USR-004 | PENDING |
| `POST /auth/password-reset` | `requestPasswordReset` | Solicitar recuperación | FEAT-USR-007 | PENDING |
| `POST /auth/password-reset/{token}` | `confirmPasswordReset` | Fijar nueva contraseña | FEAT-USR-007 | PENDING |
| `GET /me/onboarding` | `getOnboardingState` | Paso en el que se retoma el onboarding | FEAT-USR-022 | DRAFT |
| `PUT /me/onboarding/profile` | `submitOnboardingProfile` | Paso 1: nombre y fecha de nacimiento | FEAT-USR-022 | DRAFT |
| `PUT /me/onboarding/genres` | `submitOnboardingGenres` | Paso 2: géneros de interés | FEAT-USR-023 | DRAFT |
| `POST /me/onboarding/complete` | `completeOnboarding` | Cerrar el onboarding | FEAT-COM-016 | DRAFT |
| `GET /genres` | `listGenres` | Catálogo de géneros | FEAT-USR-023 | DRAFT |
| `GET /legal/documents` | `getLegalDocuments` | Documentos legales vigentes | FEAT-USR-024 | DRAFT |
| `GET /me/legal-acceptances` | `getMyLegalAcceptances` | Qué aceptó el usuario y cuándo | FEAT-USR-024 | DRAFT |
| `GET /me/context` | `getSessionContext` | Contexto de sesión para el layout | FEAT-USR-027 | DRAFT |
| `GET /me` | `getCurrentUser` | Datos de la cuenta propia | FEAT-USR-008 | PENDING |
| `PATCH /me` | `updateCurrentUser` | Editar datos personales | FEAT-USR-008 | PENDING |
| `PUT /me/password` | `changePassword` | Cambiar contraseña | FEAT-USR-008 | PENDING |
| `PUT /me/literary-preferences` | `updateLiteraryPreferences` | Preferencias literarias | FEAT-USR-009 | PENDING |
| `GET /me/settings` | `getAccountSettings` | Ajustes de cuenta | FEAT-USR-010/011/012 | PENDING |
| `PATCH /me/settings` | `updateAccountSettings` | MD, propuestas y notificaciones | FEAT-USR-010/011/012 | PENDING |
| `DELETE /me` | `deleteAccount` | Eliminar cuenta | FEAT-USR-013 | BLOCKED |
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

## `POST /auth/register`

**`operationId`:** `registerUser` · **Funcionalidad:** [`FEAT-USR-001`](../../features/user/FEAT-USR-001-register-with-email.md)

### Propósito

Crear una cuenta con email, nombre de usuario y contraseña.

### Autorización

Público. Se rechaza si la petición llega con una sesión activa.

### Reglas aplicadas

`RN-1` a `RN-8` de `FEAT-USR-001`.

### Entrada

`email`, `username`, `password` y opcionalmente `invitationToken`.

**No se acepta** ningún campo que determine créditos iniciales, rol o identidad: son
decisiones del servidor.

### Respuesta

`201 Created` con la cuenta creada. Si además devuelve una sesión depende de `S-1`.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `VALIDATION_FAILED` | 422 | Formato inválido o política de contraseña incumplida |
| `USERNAME_TAKEN` | 422 | Nombre de usuario ocupado |
| `EMAIL_ALREADY_REGISTERED` | 409 | Email con cuenta. Ver `RN-8` y `Q-2`: la respuesta no debe permitir enumerar emails |

### Efectos

Publica `UserRegistered`, que provoca de forma asíncrona la creación de la cuenta de créditos
**con saldo 0** y el envío del correo de activación (`FEAT-NOT-008`).

La cuenta se crea en `PENDING_ACTIVATION`: el usuario entra directamente al onboarding, pero
no puede ejecutar ninguna operación de escritura hasta activarla (`FEAT-USR-025`).

---

## `POST /auth/activate`

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

`200 OK`. La operación es idempotente: reactivar una cuenta ya activa devuelve éxito.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `ACTIVATION_TOKEN_EXPIRED` | 410 | El token caducó; se ofrece reenviar |
| `INVALID_ACTIVATION_TOKEN` | 404 | Token inexistente, usado o manipulado. No se distingue cuál |

### Efectos

Publica `AccountActivated`, que desencadena el abono de los **20 créditos de bienvenida**
(`FEAT-CRD-002`) y habilita todas las operaciones de escritura (`FEAT-USR-025`).

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
