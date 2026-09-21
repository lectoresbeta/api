# Endpoints — `User`

> Estado: **esqueleto**. Las rutas son una propuesta; solo se consideran contrato cuando su
> funcionalidad esté en `APPROVED` y exista su esquema en `openapi/`.

## Resumen

| Método y ruta | `operationId` | Propósito | Funcionalidad | Estado |
|---|---|---|---|---|
| `POST /auth/register` | `registerUser` | Registro con email y contraseña | FEAT-USR-001 | DRAFT |
| `GET /auth/oauth/{provider}` | `startOAuth` | Iniciar autenticación externa | FEAT-USR-002/003/005/006 | PENDING |
| `POST /auth/oauth/{provider}/callback` | `completeOAuth` | Completar autenticación externa | FEAT-USR-002/003/005/006 | PENDING |
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
| `GET /me` | `getCurrentUser` | Datos de la cuenta propia | FEAT-USR-008 | PENDING |
| `PATCH /me` | `updateCurrentUser` | Editar datos personales | FEAT-USR-008 | PENDING |
| `PUT /me/password` | `changePassword` | Cambiar contraseña | FEAT-USR-008 | PENDING |
| `PUT /me/literary-preferences` | `updateLiteraryPreferences` | Preferencias literarias | FEAT-USR-009 | PENDING |
| `GET /me/settings` | `getAccountSettings` | Ajustes de cuenta | FEAT-USR-010/011/012 | PENDING |
| `PATCH /me/settings` | `updateAccountSettings` | MD, propuestas y notificaciones | FEAT-USR-010/011/012 | PENDING |
| `DELETE /me` | `deleteAccount` | Eliminar cuenta | FEAT-USR-013 | BLOCKED |
| `GET /users/{userId}` | `getUserProfile` | Perfil público | FEAT-USR-014 | PENDING |
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

Publica `UserRegistered`, que provoca de forma asíncrona el abono de 20 créditos
(`FEAT-CRD-002`) y el envío del correo de activación (`FEAT-NOT-008`).

La cuenta se crea en `PENDING_ACTIVATION`, pero el usuario entra directamente al onboarding.

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

Publica `AccountActivated`.

---

## Nota sobre datos privados

El onboarding recoge nombre y fecha de nacimiento con el aviso explícito de que *«solo será
visible para ti y el equipo de LectoresBeta»*.

En consecuencia, **ningún endpoint accesible por terceros** —`GET /users/{userId}`,
`GET /users`, `GET /authors/{userId}/page`, las sugerencias de autores o cualquier listado—
devuelve `birthDate` ni el email. Es una regla de contrato, no una recomendación.
