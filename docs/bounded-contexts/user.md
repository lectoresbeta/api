# Bounded context: `User`

> Estado: `DRAFT` — Prefijo: `USR`

## Responsabilidad

Quién es cada persona en la plataforma: identidad, autenticación, datos de cuenta,
preferencias y presencia pública como autor.

## Qué posee

- Cuenta de usuario y credenciales.
- Autenticación local y mediante proveedores externos (Google, Facebook).
- Recuperación de contraseña.
- Preferencias de cuenta: mensajes directos, propuestas, notificaciones por canal.
- **Ajustes de privacidad** y su aplicación como reglas de autorización (`FEAT-USR-038`).
- Preferencias literarias.
- Perfil público y página de autor con su personalización.
- Invitaciones a la plataforma.
- Eliminación de cuenta.
- Preferencias de contenido sensible (`FEAT-USR-043`).
- El **estado de la cuenta**, incluido `BLOCKED` por expulsión, que aplica al recibir
  `SanctionImposed` de `Moderation`.

## Qué NO posee

| No es suyo | Es de |
|---|---|
| El saldo de créditos | `Credits` |
| Las obras del autor | `Work` |
| Las suscripciones a autores | `Community` |
| Los mensajes directos (solo el ajuste que los habilita) | `Community` |
| La entrega de notificaciones (solo la preferencia) | `Notification` |

## Conceptos

| Concepto | Responsabilidad |
|---|---|
| `Account` | Ciclo de vida de la cuenta: alta, activación, modificación, baja |
| `Authentication` | Credenciales, sesión, proveedores externos, recuperación |
| `Onboarding` | Los tres pasos posteriores al registro y su estado |
| `Profile` | Datos públicos, nombre de usuario y sus alias, preferencias literarias. Resuelve el perfil ajeno por identificador, por nombre y por alias vigente |
| `AuthorPage` | Perfil público: portada, descripción, obras publicadas y premios |
| `Invitation` | Invitaciones por email y su seguimiento |
| `Legal` | Documentos legales vigentes y el registro de aceptaciones |
| `Privacy` | Quién puede ver el perfil, comentar los textos y mandar mensajes. **Reglas de autorización**, no preferencias |
| `Preferences` | Qué avisos recibe el usuario y por qué canal; apariencia |

> El nombre de usuario y sus alias sobreviven al borrado de la cuenta durante 30 días, así
> que `username_alias` no puede depender de que la fila de `user` siga existiendo: lleva
> `user_id` anulable y un `reason`.

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `User` | `UserId` | Email único. Una cuenta eliminada no autentica. El estado sigue `PENDING_ACTIVATION → ACTIVE → DELETED`. Solo `ACTIVE` puede escribir. En `DELETED` **no queda ningún dato personal**: la cuenta se anonimiza y el `UserId` sobrevive como identificador vacío al que siguen apuntando correcciones y movimientos de créditos. |
| `AuthorPage` | `UserId` | Pertenece a un único usuario |
| `PublishedBook` | `PublishedBookId` | Libro editado **fuera** de la plataforma. Sin contenido, sin lectores beta y sin créditos. No confundir con `Work` |
| `PlatformInvitation` | `PlatformInvitationId` | Token único. Se consume una sola vez. |
| `AccountActivationToken` | `UserId` | Un único token vigente por cuenta. Se almacena con hash, nunca en claro. |
| `UsernameAlias` | `username` | Nombre de usuario reservado 30 días, por un cambio de nombre o por el borrado de la cuenta. Un alias vigente **ocupa el nombre**; uno caducado no resuelve ni ocupa, aunque su fila siga existiendo. El de un cambio de nombre resuelve al perfil y su titular puede **recuperarlo**; el de una cuenta eliminada solo bloquea. |
| `LegalAcceptance` | `LegalAcceptanceId` | Inmutable. Registra documento, versión y fecha. |
| `EmailChangeRequest` | `EmailChangeRequestId` | Una vigente por cuenta. Token hasheado, caduca y se consume una sola vez. **Hasta confirmarse, el correo válido sigue siendo el anterior.** |
| `UserPrivacySettings` | `UserId` | Un registro por usuario, con valores por defecto explícitos. Un ajuste ausente **no** significa «todo permitido». Actúa como **techo**: una obra puede ser más restrictiva, nunca más permisiva. |

### Value objects y enums

| Nombre | Reglas |
|---|---|
| `Email` | Formato válido, único, normalizado en minúsculas |
| `Username` | **Único** en toda la plataforma. `a–z`, `0–9` y `_`, de 3 a 30 caracteres, en minúsculas. Se asigna solo desde el email; editable una vez cada 30 días, y hay una lista corta de nombres reservados que nadie puede tener. **No es identidad**: esa es `UserId` |
| `HashedPassword` | ≥8 caracteres, una mayúscula, un número y un carácter especial. Nunca se expone ni se registra en logs |
| `Name` | **Dato público.** Nombre visible de la persona. No es el identificador técnico: ese sigue siendo `UserId` |
| `Description` | Dato público. Texto libre saneado |
| `AvatarUrl`, `CoverUrl` | Datos públicos. Imágenes sin metadatos EXIF |
| `BirthDate` | Fecha real y pasada. **Dato privado**: no se expone en la API pública |
| `LiteraryPreferences` | Conjunto de `Genre`, **mínimo tres**, tanto al completar el onboarding como al editarlas después: un mínimo que solo rigiera el primer día no sería un mínimo. Un género retirado del catálogo no se puede elegir, pero quien ya lo tenía lo conserva |
| `AccountStatus` | `PENDING_ACTIVATION`, `ACTIVE`, `DELETED` |
| `OnboardingStatus` | `PROFILE_PENDING`, `GENRES_PENDING`, `SUGGESTIONS_PENDING`, `COMPLETED` |
| `AuthProvider` | `LOCAL`, `GOOGLE`. `FACEBOOK` y `LINKEDIN` diferidos |

## Eventos publicados

| Evento | Cuándo | Consumidores |
|---|---|---|
| `UserRegistered` | Se crea una cuenta | `Credits` (crea la cuenta con **saldo 0**), `Notification` (correo de activación) |
| `AccountActivated` | El usuario activa su cuenta desde el correo | **`Credits`** (abona +10), `Notification`, `Feedback` (habilita recibir comentarios) |
| `ActivationEmailRequested` | Se pide reenviar el correo de activación | `Notification` |
| `LiteraryPreferencesUpdated` | El usuario fija o cambia sus géneros de interés | `Community` (sugerencias y recomendaciones) |
| `OnboardingCompleted` | Termina el onboarding | `Notification`, read models |
| `InvitedUserParticipated` | Un invitado deja su primer comentario | `Credits` (+5 al invitador) |
| `UserDeleted` | Se elimina la cuenta | Todos. **Cada contexto anonimiza lo suyo**: `User` no borra filas ajenas. En `User` convierte su nombre de usuario en alias bloqueado durante 30 días |
| `UserProfileUpdated` | Cambian datos públicos | `Community` (read models) |
| `UsernameChanged` | El usuario cambia su nombre de usuario, o recupera uno suyo | `Community` (read models que muestran el `@`) |
| `EmailChangeRequested` | Se pide cambiar el correo | `Notification` (confirmación al nuevo, **aviso al anterior**) |
| `EmailChanged` | Se confirma el cambio | `Notification`. Cierra las demás sesiones |
| `PasswordChanged` | Se cambia la contraseña | `Notification` (aviso de seguridad). **Nunca lleva la contraseña ni su hash** |
| `PrivacySettingsChanged` | Cambian los ajustes de privacidad | `Community` (read models de visibilidad) |
| `NotificationPreferencesChanged` | Cambian las preferencias de aviso | `Notification` |

> Los avisos de **cambio de correo y de contraseña** son de seguridad: `Notification` los
> entrega **aunque el usuario tenga todas las notificaciones desactivadas**. Son
> transaccionales, no notificaciones (`FEAT-USR-039` `RN-3`).

> `InvitedUserParticipated` requiere correlacionar la invitación con el primer comentario del
> invitado, que ocurre en `Feedback`. Diseño pendiente: ver `U-4`.

## Contratos publicados

| Contrato | Responde | Quién pregunta |
|---|---|---|
| `ActivationLinkProvider` | El enlace de activación, en el momento de enviar el correo | `Notification` |
| `ReaderMaturity` | **Un booleano**: ¿tiene edad? Ni la fecha de nacimiento ni la edad | `Work`, `Feedback` |
| `GenreCatalogue` | Cuáles de estos códigos de temática **no** existen | `Work` |
| `RegisteredUsers` | **Un booleano**: ¿existe este usuario? Nada más de él | `Reading`, `Community` |
| `ReaderDirectory` | Hasta N personas cuyo nombre o `@usuario` encajan, como tarjeta de perfil | `Reading` |
| `AuthorAudience` | **Un booleano**: ¿acepta este autor comentarios de esta persona? Nunca el ajuste | `Feedback` |
| `VisibleProfiles` | De estas personas, **las que quien pregunta puede ver**, como tarjeta de perfil | `Community` |

Todos son de lectura y devuelven lo justo
([`decision:0014`](../decisions/0014-published-contracts-between-contexts.md)). `ReaderMaturity`
es el que más importa: la fecha de nacimiento es dato privado y **no sale de aquí**, así que lo
que cruza la frontera es la respuesta a la única pregunta que los demás necesitan hacer.

`RegisteredUsers` nació con [`FEAT-RDG-004`](../features/reading/FEAT-RDG-004-invite-beta-reader.md):
invitar a un identificador inventado crearía una invitación que nadie puede aceptar. Responde
si existe y **nada más** — ni nombre, ni perfil, ni estado de la cuenta. Una cuenta eliminada
responde `false`, que es la respuesta correcta: la invitación no llegaría a ninguna parte.

`ReaderDirectory` es **el índice de personas**, y vive aquí porque aquí viven los perfiles.
Que `Reading` lo consuma en vez de construir el suyo es lo que evita dos emparejamientos de
nombres sobre las mismas personas; [`FEAT-USR-017`](../features/README.md) expondrá este mismo
servicio por su propio endpoint.

Tres reglas viajan **con la implementación y no con quien llama**, y es deliberado: nunca se
busca por correo —un buscador que acepta una dirección responde sin querer a «¿esta persona
tiene cuenta aquí?»—, solo aparecen cuentas activas, y el techo de privacidad de
[`FEAT-USR-038`](../features/user/FEAT-USR-038-privacy-settings.md) se aplica aquí. La última
se aplica aquí, y ya funciona: quien tenga el perfil en `NOBODY` no aparece.

`AuthorAudience` es el **techo** de [`FEAT-USR-038`](../features/user/FEAT-USR-038-privacy-settings.md):
el perfil pone el máximo y cada obra puede bajarlo, nunca subirlo. Devuelve un booleano y no
el ajuste, y eso es lo que permitió que `FOLLOWERS` significara «nadie» mientras no existía el
grafo de seguidores y pasara a decir lo que dice **cambiando un solo sitio**, con
[`FEAT-COM-010`](../features/community/FEAT-COM-010-subscribe-to-author.md).

`VisibleProfiles` está preguntado **al revés de como parecería natural**, igual que
`GenreCatalogue`: no «dame estos perfiles» sino «dame los que esta persona puede ver, de
estos». Así la regla de privacidad viaja con la implementación y no con quien llama, que es lo
que la hace cumplirse siempre — un consumidor que recibiera los perfiles y tuviera que
filtrarlos después es un consumidor que algún día no lo hace, y lo que se le escaparía es
justo quien pidió no ser encontrado ([`FEAT-COM-027`](../features/community/FEAT-COM-027-following-and-followers.md)).

Responde **por lotes**: existe para pintar una página entera de una lista, y una llamada por
fila sería un N+1 escondido detrás de un contrato.

### La copia del grafo de seguidores

`User` mantiene su propia tabla de quién sigue a quién (`user_ctx.author_follower`),
alimentada por `AuthorSubscribed` y `AuthorUnsubscribed` de `Community`. No es un modelo
propio del seguimiento: lleva el par y la fecha, lo justo para responder «¿me sigue?».

Existe por la **regla 4** de [`decision:0014`](../decisions/0014-published-contracts-between-contexts.md):
un contrato no llama al de otro contexto mientras responde. `AuthorAudience` es un contrato
publicado, y `Community` ya llama a `RegisteredUsers` para dejar seguir a alguien — un
contrato en sentido contrario cerraría el ciclo de llamadas que esa regla evita.

El precio es que las audiencias `FOLLOWERS` son **consistentes en diferido**: entre seguir a
alguien y entrar en su audiencia pasa lo que tarde la cola. Al revés no, y por eso el hecho de
dejar de seguir viaja por el mismo camino y no se olvida nunca.

## Contratos que consulta

| Contrato | De quién | Para qué |
|---|---|---|
| `SubscriptionCounts` | `Community` | Los contadores de seguidos y seguidores de la cabecera del perfil |
| `AuthoredWorkCount` | `Work` | El contador de relatos |
| `DeliveredCorrectionCount` | `Feedback` | El contador de correcciones |

Los tres son para lo mismo: las cuatro cifras de `FEAT-USR-028`, que **no son de `User`**. Se
ensamblan en `Infrastructure` al responder, y ninguno de los tres puede tumbar la pantalla —
una cifra que no se pueda leer viaja como `null`, que no es lo mismo que cero.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| U-1 | ¿Se verifica el email al registrarse? (`S-3`) | Fraude con invitaciones |
| U-2 | ¿Se puede vincular varios proveedores externos a la misma cuenta? | Modelo de credenciales |
| U-3 | ¿Qué ocurre con obras, feedback y créditos al eliminar la cuenta? (`V-4`, `J-7`) | Bloquea `FEAT-USR-012` |
| U-4 | ¿Quién detecta que un invitado "ha participado": `User` escuchando a `Feedback`, o `Feedback` publicándolo? | Define el productor de `InvitedUserParticipated` |
| U-5 | ¿La personalización de la página de autor tiene límites (temas cerrados o CSS libre)? | Riesgo de seguridad si es libre |
| U-8 | ¿Qué nombre se muestra públicamente? (`OB-2`) | **Resuelto:** el `Name` del onboarding. El aviso de privacidad solo afecta a la fecha de nacimiento |
| U-6 | ¿Existe `Username`? | **Resuelto:** no. No se usa el concepto |
| U-10 | ¿El `Name` debe ser único? | Sin unicidad, dos homónimos son indistinguibles. Recomendación: no exigirla y desambiguar con avatar y enlace al perfil |
| U-7 | ¿Qué puede hacer una cuenta `PENDING_ACTIVATION`? | **Resuelto:** leer y completar el onboarding. Ver `decision:0003` |
| U-9 | ¿Hay edad mínima para registrarse? (`OB-7`) | Legal: se recoge la fecha de nacimiento sin motivo declarado |
| U-11 | ¿Existe un `@identificador` público? | **Resuelta:** sí. Ver [`decision:0005`](../decisions/0005-username-with-temporary-aliases.md) |
| U-12 | ¿Qué es la insignia «0 Level»? | **Resuelta:** error del diseño. No hay sistema de niveles |
| U-14 | ¿Puede el usuario recuperar su propio alias sin esperar 30 días? | **Resuelta:** sí, mientras siga vigente. Renueva el plazo (`FEAT-USR-034` `RN-1b`) |
| U-15 | ¿Qué ocurre con el nombre al eliminar la cuenta? | **Resuelta:** queda bloqueado 30 días como alias que no resuelve (`RN-13`) |
| U-16 | ¿Los alias previos de una cuenta eliminada se extienden a 30 días desde el borrado? | Hoy conservan su caducidad original (`N-19`) |
| U-13 | ¿«Mi perfil» y la «página de autor» son la misma pantalla? | Si no, hay dos perfiles que mantener (`P-5`) |

### Datos privados

Nunca se exponen en el perfil público ni en ningún listado, búsqueda o sugerencia:

- fecha de nacimiento;
- email;
- el «Nombre» del paso 1 del onboarding, mientras `OB-2` no aclare lo contrario.

Como tampoco existe nombre de usuario, hoy **no hay ningún nombre que se pueda mostrar
públicamente**. Es el hueco que `OB-2` tiene que cerrar.

El diseño lo dice de forma explícita en el tooltip de ambos campos: *«Esta información solo
será visible para ti y el equipo de LectoresBeta.»*
