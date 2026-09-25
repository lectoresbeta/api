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
| `POST /api/v1/auth/activation/resend` | `resendActivationEmail` | Reenviar el correo de activación | FEAT-USR-021 | **Implementado** |
| `POST /api/v1/auth/password/forgotten` | `requestPasswordReset` | Pedir el enlace de recuperación | FEAT-USR-007 | **Implementado** |
| `POST /api/v1/auth/password/reset` | `resetPassword` | Fijar la contraseña con ese enlace | FEAT-USR-007 | **Implementado** |
| `PUT /api/v1/me/password` | `changeMyPassword` | Cambiar o establecer la contraseña | FEAT-USR-041 | **Implementado** |
| `POST /api/v1/me/email-change` | `requestEmailChange` | Pedir el cambio de correo | FEAT-USR-040 | **Implementado** |
| `POST /api/v1/me/email-change/confirm` | `confirmEmailChange` | Confirmarlo desde el correo nuevo | FEAT-USR-040 | **Implementado** |
| `GET /api/v1/me/context` | `getSessionContext` | Lo que el layout necesita, en una petición | FEAT-USR-027 | **Implementado** |
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
| `GET /api/v1/me/profile` | `getMyProfile` | Perfil editable: nombre, biografía, foto | FEAT-USR-008 | **Implementado** |
| `PATCH /api/v1/me/profile` | `updateMyProfile` | Editar nombre y biografía | FEAT-USR-008 | **Implementado** |
| `PATCH /me` | `updateCurrentUser` | Editar datos personales | FEAT-USR-008 | PENDING |
| `POST /me/email-change` | `requestEmailChange` | Solicitar cambio de correo | FEAT-USR-040 | DRAFT |
| `POST /me/email-change/confirm` | `confirmEmailChange` | Confirmarlo desde el correo nuevo | FEAT-USR-040 | DRAFT |
| `PUT /me/password` | `changeMyPassword` | Cambiar o **establecer** contraseña | FEAT-USR-041 | DRAFT |
| `GET /api/v1/me/literary-preferences` | `getMyLiteraryPreferences` | Mis géneros | FEAT-USR-009 | **Implementado** |
| `PUT /api/v1/me/literary-preferences` | `updateLiteraryPreferences` | Cambiarlos | FEAT-USR-009 | **Implementado** |
| `GET /me/settings` | `getAccountSettings` | Ajustes de cuenta | FEAT-USR-010/011 | PENDING |
| `GET /api/v1/me/privacy-settings` | `getMyPrivacySettings` | Ajustes de privacidad | FEAT-USR-038 | **Implementado** |
| `PUT /api/v1/me/privacy-settings` | `updateMyPrivacySettings` | Modificarlos | FEAT-USR-038 | **Implementado** |
| `POST /api/v1/me/onboarding/complete` | `completeOnboarding` | Terminar el onboarding | FEAT-COM-016 | **Implementado** |
| `GET /api/v1/me/content-preferences` | `getMyContentPreferences` | Qué he decidido no ver | FEAT-USR-043 | **Implementado** |
| `PUT /api/v1/me/content-preferences` | `updateMyContentPreferences` | Cambiarlo | FEAT-USR-043 | **Implementado** |
| `GET /me/notification-preferences` | `getMyNotificationPreferences` | Preferencias de aviso por canal | FEAT-USR-039 | DRAFT |
| `PUT /me/notification-preferences` | `updateMyNotificationPreferences` | Modificarlas | FEAT-USR-039 | DRAFT |
| `PATCH /me/settings` | `updateAccountSettings` | MD y propuestas de LB | FEAT-USR-010/011 | PENDING |
| `DELETE /me` | `deleteMyAccount` | Eliminar cuenta | FEAT-USR-013 | BLOCKED |
| `GET /api/v1/users/{userId}` | `getUserProfile` | Perfil público por identificador | FEAT-USR-014 | **Implementado** |
| `GET /api/v1/profiles/{username}` | `getProfileByUsername` | Perfil por nombre de usuario o alias | FEAT-USR-035 | **Implementado** |
| `PUT /api/v1/me/username` | `changeUsername` | Cambiar el nombre de usuario | FEAT-USR-034 | **Implementado** |
| `GET /usernames/{username}/availability` | `checkUsernameAvailability` | Comprobar si un nombre está libre | FEAT-USR-033 | DRAFT |
| — | — | La cabecera del perfil propio la sirven `getMyProfile` y `updateMyProfile`, arriba, **con sus cuatro contadores**. Le faltan portada y avatar | FEAT-USR-028 | PARTIAL |
| `PUT /api/v1/me/profile/avatar` | `updateAvatar` | Subir o reencuadrar la foto | FEAT-USR-037 | **Implementado** |
| `DELETE /api/v1/me/profile/avatar` | `deleteAvatar` | Quitar la foto | FEAT-USR-037 | **Implementado** |
| `GET /api/v1/me/profile/avatar/original` | `getMyAvatarOriginal` | Mi foto sin recortar, para el editor | FEAT-USR-037 | **Implementado** |
| `PUT /me/profile/cover` | `updateCover` | Cambiar portada | FEAT-USR-028 | DRAFT |
| `GET /api/v1/users/{userId}/published-books` | `listPublishedBooks` | La bibliografía de un autor | FEAT-USR-029 | **Implementado** |
| `POST /api/v1/me/published-books` | `addPublishedBook` | Añadir una obra publicada | FEAT-USR-029 | **Implementado** |
| `PATCH /api/v1/me/published-books/{publishedBookId}` | `updatePublishedBook` | Editarla | FEAT-USR-029 | **Implementado** |
| `DELETE /api/v1/me/published-books/{publishedBookId}` | `deletePublishedBook` | Quitarla | FEAT-USR-029 | **Implementado** |
| `PUT /api/v1/me/published-books/{publishedBookId}/cover` | `updatePublishedBookCover` | Subir su portada | FEAT-USR-029 | **Implementado** |
| `DELETE /api/v1/me/published-books/{publishedBookId}/cover` | `deletePublishedBookCover` | Quitar su portada | FEAT-USR-029 | **Implementado** |
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
| Contenido sensible | `/me/content-preferences` | Qué no se quiere ver; **no** quién puede hacer qué |

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


---

## `GET` y `PUT /api/v1/me/content-preferences`

**`operationId`:** `getMyContentPreferences`, `updateMyContentPreferences` · **Funcionalidad:**
[`FEAT-USR-043`](../../features/user/FEAT-USR-043-content-preferences.md)

### Propósito

Qué contenidos sensibles **no quiere ver** esta persona. Es la otra mitad de
[`FEAT-WRK-017`](../../features/work/FEAT-WRK-017-content-rating.md): el autor declara qué hay
y el lector decide qué quiere.

No confundir con el **filtro de edad**
([`FEAT-USR-044`](../../features/user/FEAT-USR-044-age-based-content-filtering.md)): lo no apto
para menores se filtra con independencia de lo que haya aquí, y no se desactiva. Eso no es una
preferencia, es una regla, y por eso `ADULTS_ONLY` no está entre los valores admitidos.

### Autorización

Solo el titular, y **no hay forma de preguntar por las de otro**. No es un olvido: si un autor
pudiera saber cuánta gente ha excluido su obra sabría cuánta audiencia pierde por etiquetar
bien, y con ese número tendría un incentivo directo para etiquetar mal (`RN-7`).

### Reglas aplicadas

- El `PUT` **sustituye la lista entera**, como la clasificación de una obra: lo que se envía
  es cómo queda.
- Los valores son los del catálogo cerrado de `FEAT-WRK-017`. Uno que no esté devuelve
  `422 UNKNOWN_CONTENT_WARNING` con `unknownWarnings`, y **no se guarda nada**: una exclusión
  a medias deja a alguien creyendo que ha filtrado más de lo que ha filtrado.
- Una lista vacía es una decisión válida y es además el estado por defecto: **no se filtra
  nada** mientras nadie elija (`RN-3`).
- El filtrado **lo hace el servidor** en cada consulta (`RN-2`). Un filtro de cliente
  significaría que el contenido viaja hasta el navegador de quien pidió no verlo.
- Lo excluido **no cuenta en el total** de un listado: no se esconde una tarjeta, se devuelve
  una lista distinta.

### Qué no está todavía

Se aplica en el **catálogo**. El muro, las recomendaciones, «también te puede interesar» y el
perfil de un autor lo heredarán cuando existan: la exclusión ya está guardada y disponible por
contrato, así que lo que falta es cada pantalla, no la decisión.

El **enlace directo** (`RN-5`) —advertir y pedir confirmación en vez de ocultar— espera a que
existan los enlaces públicos.

### Efectos

Ninguno fuera de `User`. `ContentPreferencesChanged` no se publica: nadie lo necesita todavía
y un hecho que nadie escucha es un contrato que hay que mantener a cambio de nada.

---

## `GET /api/v1/users/{userId}` y `GET /api/v1/profiles/{username}`

**`operationId`:** `getUserProfile`, `getProfileByUsername` · **Funcionalidades:**
[`FEAT-USR-014`](../../features/user/FEAT-USR-014-view-public-profile.md),
[`FEAT-USR-035`](../../features/user/FEAT-USR-035-resolve-profile-by-username.md)

### Propósito

El perfil de otra persona. Las dos rutas devuelven **lo mismo**: piden el mismo recurso por
caminos distintos, y dos formas obligarían al cliente a saber por cuál entró.

### Autorización

**Públicos.** Un perfil que su dueño no ha restringido es una URL que se comparte, y exigir
sesión para abrirla haría inútil compartirla.

La sesión se lee si la hay, y sirve para una sola cosa: que su titular se vea siempre a sí
mismo. Es lo que permite deshacer el ajuste — quien restringe su perfil sigue entrando a
abrirlo.

### Reglas aplicadas

- **Nunca el correo ni la fecha de nacimiento.**
- Se resuelve primero entre los nombres en uso y solo después entre los **alias vigentes**. Un
  alias caducado no resuelve aunque su fila siga ahí.
- Los alias que dejó una cuenta eliminada bloquean el nombre y **nunca resuelven**.
- `canonicalUsername` va siempre; `resolvedVia` dice `USER_ID`, `USERNAME` o `ALIAS`.
- **No redirige.** Con `301` sería más cómodo y este endpoint sirve datos, no páginas: quien
  construye la barra de direcciones es el cliente.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `PROFILE_NOT_FOUND` | 404 | No existe, el identificador está mal escrito, el alias caducó, la cuenta está eliminada, o su titular ha restringido el perfil |

**Una sola respuesta para las cinco**, y la última es la que obliga: un `403` confirmaría que
la cuenta existe, y para un ajuste cuya razón de ser es no ser encontrado eso lo deja sin
efecto.

### Qué no está todavía

Contadores y estado de la relación —si le sigo, si me sigue, si hay bloqueo—, que son de
`Community`. Y las pestañas, que se piden aparte y paginadas.


---

## `GET` y `PATCH /api/v1/me/profile`

**`operationId`:** `getMyProfile`, `updateMyProfile` · **Funcionalidad:**
[`FEAT-USR-008`](../../features/user/FEAT-USR-008-edit-profile.md)

### Propósito

El nombre y la biografía que una persona muestra de sí misma. No el correo ni la contraseña
—son operaciones de seguridad y tienen su propia pantalla— ni la fecha de nacimiento.

### Autorización

Solo el titular. El `PATCH` **exige cuenta activada**; el `GET` no, porque es solo lectura y
la pantalla tiene que poder abrirse para enseñar el botón de activar.

Que escribir exija activación es una decisión, no una herencia: la biografía es texto libre
que aparece en un perfil público, y dejar publicarlo a una cuenta sin verificar es justo lo
que la regla de activación existe para impedir.

### Reglas aplicadas

- `PATCH` y no `PUT`: el mismo recurso se edita campo a campo desde el perfil y en bloque
  desde Configuración. **Lo que no se envía se queda como estaba; `description: null` la
  borra**, y la diferencia la marca la presencia de la clave, no su valor.
- El nombre **no puede quedar vacío**: es lo que identifica a la persona en toda la interfaz.
  Hasta 80 caracteres, y no tiene por qué ser único.
- La biografía admite **300 caracteres**, medidos **después** de retirar el marcado.
- La biografía se guarda como **texto plano**. El marcado se retira en vez de rechazarse, y
  la respuesta devuelve lo que ha quedado guardado.
- **El nombre de usuario y la foto no viajan aquí.** Cada uno tiene su endpoint, y no por
  purismo: mezclados, una biografía se quedaría sin guardar porque el nombre de usuario está
  ocupado o porque falló una subida.
- El `GET` devuelve además **`usernameChangeableOn`**: desde cuándo se puede volver a cambiar
  el nombre de usuario (`FEAT-USR-034`). Va en la lectura que abre la pantalla para que el
  campo pueda salir ya desactivado; enterarse con un `429` después de escribir un nombre es
  enterarse tarde.
- Y **`counters`**, las cuatro cifras de la cabecera de «Mi perfil» (`FEAT-USR-028`). Las dos
  pantallas piden el mismo recurso, y el `PATCH` devuelve exactamente la misma forma que el
  `GET`: quien acaba de guardar no tiene por qué recargar para recuperar lo que ya tenía.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `INVALID_VALUE` | 422 | Nombre vacío o de más de 80 caracteres; biografía de más de 300 ya limpia |
| `ACCOUNT_NOT_ACTIVATED` | 403 | Escribir sin haber activado la cuenta |

### Los contadores

`following`, `followers`, `works` y `corrections`. **Ninguno es de `User`**: los cuenta
`Community`, `Work` y `Feedback`, cada uno por su contrato publicado, y se ensamblan al
responder. Aquí no se consulta ninguna tabla ajena.

**`null` significa «no se ha podido saber», y no es lo mismo que cero.** Si un contexto no
responde, su cifra viaja nula y el perfil se devuelve igual: un contador caído no tumba la
pantalla. Un cliente que trate `null` como `0` enseñará un cero donde debería dejar un hueco.

Dos advertencias sobre lo que cuentan:

- `followers` y `following` cuentan **a todo el mundo**, también a quien tiene el perfil
  cerrado, así que pueden ser mayores que las filas de `listSubscribers` y
  `listAuthorSubscriptions`. Filtrar la cifra la haría distinta para cada visitante;
- `works` cuenta las obras **de cualquier estado**, porque es el perfil propio y los
  borradores son suyos.

### Efectos

Publica `UserProfileUpdated` con **los valores nuevos y no un diff**: quien lo consume no
quiere saber qué cambió sino con qué quedarse, porque el nombre y el avatar aparecen copiados
en el muro, en los comentarios y en el catálogo.

Nunca el correo ni la fecha de nacimiento.

---

## `PUT /api/v1/me/username`

**`operationId`:** `changeUsername` · **Funcionalidad:**
[`FEAT-USR-034`](../../features/user/FEAT-USR-034-change-username.md)

### Propósito

Cambiar el nombre de usuario, **una vez cada 30 días**. Lo que de verdad hace la operación no
es asignar el nombre nuevo sino **reservar el anterior**: el nombre que se deja no queda
libre, se convierte en alias de la cuenta durante 30 días.

Esa reserva mantiene vivos los enlaces ya compartidos, sí, pero sobre todo impide que otra
persona ocupe el nombre y **herede el tráfico dirigido a alguien distinto**. Es también la
razón del plazo: cada cambio bloquea un nombre, así que sin límite una sola cuenta podría
retener tantos como quisiera.

Tiene endpoint propio aunque la pantalla lo enseñe junto al nombre y la biografía
([`settings.md`](../../ui/settings.md)): un fallo aquí no debe llevarse por delante lo que sí
se podía guardar.

### Autorización

Solo el titular, y **con la cuenta activada**: el nombre de usuario aparece en cada comentario
y en cada URL.

### Reglas aplicadas

- El nombre nuevo debe estar **libre**: ni en uso ni retenido por un alias vigente, sea de
  quien sea y sea cual sea su motivo.
- **Recuperar un nombre propio que siga reservado se permite aunque no hayan pasado los 30
  días**, y renueva el plazo. Al recuperarlo su fila de alias desaparece y el nombre que se
  abandona ocupa su lugar: sigue habiendo un solo alias vigente.
- El plazo que la recuperación renueva vale para **cualquier** cambio, **incluida otra
  recuperación**. Sin esta segunda mitad se podría alternar entre dos nombres
  indefinidamente, rompiendo en cada vuelta los enlaces que el plazo protege.
- Pedir el nombre que ya se tiene **no es un cambio**: se acepta sin efecto, sin crear alias y
  sin gastar el cupo.
- El formato es el mismo que el del nombre asignado automáticamente
  ([`FEAT-USR-033`](../../features/user/FEAT-USR-033-username-assignment.md)), lista de
  reservados incluida.
- El nombre de usuario **no es identidad**: el `UserId` no cambia y ninguna referencia interna
  lo usa.

### Respuesta

`username`, `previousUsername`, `aliasExpiresAt` y `changeableOn`. Los dos del medio son nulos
cuando no ha habido cambio; **`changeableOn` va siempre**, para que el formulario pueda
desactivarse sin fallar primero.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `USERNAME_TAKEN` | 409 | El nombre está en uso **o** retenido por un alias vigente |
| `USERNAME_RESERVED` | 422 | Es uno de los nombres que nadie puede tener |
| `INVALID_VALUE` | 422 | El formato no encaja |
| `USERNAME_CHANGE_TOO_SOON` | 429 | Aún no han pasado 30 días. Lleva **`availableOn`** |
| `ACCOUNT_NOT_ACTIVATED` | 403 | La cuenta no está activada |

Que el nombre en uso y el retenido por un alias devuelvan **el mismo código** es deliberado:
distinguirlos contaría que alguien tuvo ese nombre y lo dejó hace menos de un mes, que es
información sobre una persona y no sobre la disponibilidad de una palabra. El reservado sí se
distingue, porque ahí no hay nadie a quien proteger.

### Efectos

Publica `UsernameChanged` con los dos nombres y la caducidad del alias. **Recuperar publica el
mismo hecho**: para quien lo consume es un cambio más.

---

## `GET` y `PUT /api/v1/me/literary-preferences`

**`operationId`:** `getMyLiteraryPreferences`, `updateLiteraryPreferences` ·
**Funcionalidad:** [`FEAT-USR-009`](../../features/user/FEAT-USR-009-literary-preferences.md)

### Propósito

Los géneros que le interesan a alguien. **Son los mismos** que eligió el paso 2 del
onboarding (`FEAT-USR-023` `RN-6`): un solo dato con dos puertas, no dos copias que puedan
discrepar — porque discreparían, y las recomendaciones acabarían contradiciendo lo que la
persona cree haber elegido sin que nada lo detectara.

### Autorización

Solo las propias. Leer no exige cuenta activada; **escribir sí**. No hay forma de consultar
los géneros de otra persona por aquí: son parte de su perfil, y qué enseña un perfil lo
deciden `FEAT-USR-014` y sus ajustes de privacidad.

### Reglas aplicadas

- `PUT` y no `PATCH`: el cuerpo es la selección entera y **sustituye** a la anterior. Quien
  quita un género espera que desaparezca.
- Mínimo **tres**, los mismos que en el onboarding. Un mínimo que solo rigiera el primer día
  no sería un mínimo.
- Los duplicados se normalizan, no se rechazan, con una consecuencia que conviene conocer:
  `['POETRY','poetry']` **no** llega al mínimo, porque es un género.
- Solo géneros del catálogo, y un código desconocido se rechaza **nombrándolo**.
- **Un género retirado que ya era tuyo se conserva; uno retirado que no lo era se rechaza.**
  Conservar no es elegir, y un guardado no debe caerse por un campo que quien guarda no
  estaba editando.

### Respuesta

La lista de géneros con `code` y `name`, **la misma forma que `listGenres`**, en el orden del
catálogo. Lleva el nombre y no solo el código porque un género retirado ya no aparece en el
catálogo: cruzar ambas respuestas dejaría sin nombre justo lo que hay que seguir enseñando.

De ahí sale también cómo reconocer uno retirado sin un campo más: está en las preferencias de
alguien y no en `GET /genres`.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `NOT_ENOUGH_GENRES` | 422 | Menos de tres, ya normalizados |
| `UNKNOWN_GENRE` | 422 | Algún código no está disponible. El mensaje los nombra |
| `ACCOUNT_NOT_ACTIVATED` | 403 | Escribir sin haber activado la cuenta |

### Efectos

Publica `LiteraryPreferencesUpdated` con la **selección entera**, no con lo que cambió: quien
lo consume quiere con qué quedarse, y aplicar una secuencia de diferencias daría un conjunto
equivocado el primer día que se pierda un mensaje. Es el mismo hecho que publica el
onboarding.

---

## `PUT` y `DELETE /api/v1/me/profile/avatar`, y `GET .../avatar/original`

**`operationId`:** `updateAvatar`, `deleteAvatar`, `getMyAvatarOriginal` ·
**Funcionalidad:** [`FEAT-USR-037`](../../features/user/FEAT-USR-037-upload-profile-photo.md)

### Propósito

La foto de perfil. **Subir y reencuadrar son la misma operación**: llega una imagen cuadrada
nueva y sustituye a la anterior; la distinción entre «Cambiar» y «Editar» es de interfaz.

### Autorización

Solo el titular, con la cuenta activada. La foto sin recortar la sirve únicamente quien la
subió.

### Reglas aplicadas

- **El servidor reescribe siempre la imagen**: normaliza el formato, redimensiona y elimina
  los metadatos. Que el navegador la haya recortado antes no exime — recortar y sanear son
  cosas distintas, y los metadatos de una foto llevan **dónde se tomó**.
- El tipo se decide **por el contenido**, nunca por la extensión ni por el `Content-Type`:
  los dos los escribe quien sube el fichero. Se admiten JPEG, PNG y WebP; lo guardado es WebP
  cuadrado.
- Máximo **2 MB por fichero**, aplicado por el servidor.
- **El servidor no recorta ni gira.** `crop` se guarda para reabrir el editor donde se dejó, y
  no se aplica nunca. De ahí una ventaja que conviene no perder: nunca hay que interpretar la
  orientación EXIF, que es la fuente clásica de fotos tumbadas.
- Se guardan **dos ficheros**: la recortada, que se ve, y la original, material de trabajo del
  editor. Al reencuadrar basta con mandar la recortada.
- El nombre del fichero se descarta: no se usa como ruta ni se devuelve.
- Subir otra **borra la anterior**; eliminar **borra las dos**. Dejar la original huérfana
  sería guardar una imagen personal que su dueño cree haber borrado.
- Eliminar es idempotente.

### Dónde viven los ficheros

En el puerto `FileStorage`, nunca en la base de datos, y se sirven por
`GET /api/v1/media/{key}`, que es **público**: un avatar aparece en perfiles que se abren sin
sesión.

**La original no se sirve por ahí.** Vive en otra carpeta y el endpoint abierto solo entrega
las públicas, así que no es alcanzable ni conociendo su clave. Podría haber bastado con que
las claves sean impredecibles; no basta, porque una clave acaba en un log o en un historial.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `UNSUPPORTED_FILE_TYPE` | 422 | No es una imagen de un formato admitido. **HEIC —el formato por defecto de la cámara de iOS— cae aquí hoy** |
| `INVALID_IMAGE` | 422 | Lo parece y no se puede leer |
| `FILE_TOO_LARGE` | 413 | Más de 2 MB |
| `ACCOUNT_NOT_ACTIVATED` | 403 | La cuenta no está activada |
| `PROFILE_NOT_FOUND` | 404 | Al pedir la original cuando no hay ninguna |

### Efectos

Publica `UserProfileUpdated` con la **recortada y nunca la original**, para que los read
models que copian el avatar se actualicen.

---

## Las obras publicadas del autor

**Funcionalidad:** [`FEAT-USR-029`](../../features/user/FEAT-USR-029-published-books.md)

Seis operaciones sobre la bibliografía que un autor añade a su perfil para acreditar su
trayectoria: libros ya editados **fuera** de la plataforma.

### Una obra publicada no es una `Work`

Es lo que hay que tener claro antes de leer nada más. Comparten la palabra «obra» y nada más:

| | `Work` (relato) | `PublishedBook` |
|---|---|---|
| Qué es | Manuscrito inédito | Libro editado y a la venta fuera |
| Contenido | Sí, y es el activo que hay que proteger | No: metadatos y un enlace |
| Lectores beta, correcciones, créditos | Sí | **No** |
| Quién la ve | Su autor y quien tenga acceso | Cualquiera: es promoción |
| Cuenta en `counters.works` | Sí | **No** (`P-17`) |

No se puede leer, ni comentar, ni pedirle acceso (`RN-3`). Es la regla que impide que el
concepto contamine el resto: en cuanto algo «publicado» admitiera correcciones habría que
decidir si cuesta créditos.

### Quién puede qué

| Operación | Sesión | Quién |
|---|---|---|
| `listPublishedBooks` | **No** | Cualquiera, si el perfil se puede ver |
| Las cinco de escritura | Sí, y **activada** | Solo su dueño |

El listado responde `404` cuando el perfil no se puede ver —cuenta eliminada o privacidad
restringida (`FEAT-USR-038`)—. Contestar por su cuenta sería un camino lateral para confirmar
que una cuenta existe justo cuando su titular ha pedido que no se sepa.

Editar o borrar la obra de otro responde **`403` y no `404`**, al revés que casi todo lo
demás: la obra se enseña en un perfil abierto, así que fingir que no existe sería mentirle a
quien la acaba de ver, sin ocultarle nada.

### Los datos

Solo el **título** es obligatorio (`RN-6`): quien cita una obra descatalogada no tiene
editorial que poner ni enlace al que mandar a nadie.

La **editorial es texto libre** y no se valida contra ningún catálogo (`RN-9`). El ejemplo del
propio diseño dice «Amazon», que no es un sello sino una plataforma de autopublicación:
validarla dejaría fuera justo al autor que más usa el campo.

El **enlace de compra** se valida como dirección absoluta `http` o `https`, y el servidor
**no la visita**. Viaja acompañado de `purchaseUrlIsExternal`, que dice que sale de la
plataforma: es lo que necesita saber quien lo pinta para abrirlo aparte y sin arrastrar la
sesión.

El **año** se acepta entre 1450 y el año que viene. El tope está para atrapar el error de
teclado —un `19` o un `202`—, no para discutirle a nadie su bibliografía; y llega a el año
que viene porque un libro se anuncia antes de salir.

Un perfil admite **50 obras** (`P-16`). Un perfil no es un catálogo.

### El orden

Lo decide el autor con `position` (`RN-7`). Hasta que decide algo, manda el **año
descendente**, y las obras sin año van al final.

Las dos mitades se sostienen porque al añadir una obra solo se coloca **esa**: reordenar la
lista entera por año en cada alta desharía en silencio lo que el autor acababa de arrastrar.
Por lo mismo, cambiarle el año a una obra ya colocada no la mueve de sitio.

Las posiciones son consecutivas desde cero y sin huecos, también después de borrar.

### La portada

Endpoint propio, y no un campo del `PATCH`: **PHP solo desmonta un cuerpo `multipart` en las
peticiones `POST`**, así que una portada dentro del `PATCH` llegaría vacía. Separadas, además,
el año no se queda sin guardar porque falló una subida.

Le aplican las reglas generales de [`file-uploads.md`](../conventions/file-uploads.md):
reescritura siempre, sin metadatos, tipo decidido por el contenido, máximo 2 MB. Se guarda con
el lado mayor en 900 px, en la carpeta pública `book-covers/`.

**No se recorta a proporción de libro.** Las portadas reales no miden todas lo mismo, y
recortar la de alguien para que encaje en una cuadrícula es estropearla: 2:3 es una
recomendación de diseño (`P-18`).

Sin portada viaja `coverUrl: null` y el marcador por defecto lo pone la interfaz (`P-20`).
Devolver aquí una imagen de relleno le quitaría al cliente la única forma de distinguir «no
hay portada» de «esta es la portada».

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `VALIDATION_FAILED` | 422 | Falta el título, algo no cabe, el año no es creíble o no venía portada |
| `INVALID_PURCHASE_URL` | 422 | El enlace de compra no es una dirección absoluta `http` o `https` |
| `PUBLISHED_BOOK_LIMIT_REACHED` | 409 | Ya hay 50 |
| `PUBLISHED_BOOK_NOT_FOUND` | 404 | No existe |
| `NOT_YOUR_PUBLISHED_BOOK` | 403 | Es de otro |
| `ACCOUNT_NOT_ACTIVATED` | 403 | La cuenta no está activada |
| `UNSUPPORTED_FILE_TYPE` / `INVALID_IMAGE` | 422 | La portada no es una imagen admitida, o no se puede leer |
| `FILE_TOO_LARGE` | 413 | La portada pasa de 2 MB |

### Efectos

**Ninguno fuera de `User`.** No mueve créditos, no cuenta como relato y no publica ningún
evento: un evento «por si acaso» es un contrato que luego hay que mantener.
