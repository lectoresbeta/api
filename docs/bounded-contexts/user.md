# Bounded context: `User`

> Estado: `DRAFT` — Prefijo: `USR`

## Responsabilidad

Quién es cada persona en la plataforma: identidad, autenticación, datos de cuenta,
preferencias y presencia pública como autor.

## Qué posee

- Cuenta de usuario y credenciales.
- Autenticación local y mediante proveedores externos (Google, Facebook).
- Recuperación de contraseña.
- Preferencias de cuenta: mensajes directos, propuestas, notificaciones por email.
- Preferencias literarias.
- Perfil público y página de autor con su personalización.
- Invitaciones a la plataforma.
- Eliminación de cuenta.

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
| `Profile` | Datos públicos, nombre de usuario y sus alias, preferencias literarias |
| `AuthorPage` | Perfil público: portada, descripción, obras publicadas y premios |
| `Invitation` | Invitaciones por email y su seguimiento |
| `Legal` | Documentos legales vigentes y el registro de aceptaciones |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `User` | `UserId` | Email único. Una cuenta eliminada no autentica. El estado sigue `PENDING_ACTIVATION → ACTIVE → DELETED`. Solo `ACTIVE` puede escribir. |
| `AuthorPage` | `UserId` | Pertenece a un único usuario |
| `PublishedBook` | `PublishedBookId` | Libro editado **fuera** de la plataforma. Sin contenido, sin lectores beta y sin créditos. No confundir con `Work` |
| `PlatformInvitation` | `PlatformInvitationId` | Token único. Se consume una sola vez. |
| `AccountActivationToken` | `UserId` | Un único token vigente por cuenta. Se almacena con hash, nunca en claro. |
| `UsernameAlias` | `username` | Nombre de usuario anterior, vigente 30 días. Un alias vigente **ocupa el nombre**; uno caducado no resuelve ni ocupa, aunque su fila siga existiendo. El titular puede **recuperarlo** mientras siga vigente. Se borra al eliminarse la cuenta. |
| `LegalAcceptance` | `LegalAcceptanceId` | Inmutable. Registra documento, versión y fecha. |

### Value objects y enums

| Nombre | Reglas |
|---|---|
| `Email` | Formato válido, único, normalizado en minúsculas |
| `Username` | **Único** en toda la plataforma. `a–z`, `0–9` y `_`, de 3 a 30 caracteres, en minúsculas. Se asigna solo desde el email; editable una vez cada 30 días. **No es identidad**: esa es `UserId` |
| `HashedPassword` | ≥8 caracteres, una mayúscula, un número y un carácter especial. Nunca se expone ni se registra en logs |
| `Name` | **Dato público.** Nombre visible de la persona. No es el identificador técnico: ese sigue siendo `UserId` |
| `Description` | Dato público. Texto libre saneado |
| `AvatarUrl`, `CoverUrl` | Datos públicos. Imágenes sin metadatos EXIF |
| `BirthDate` | Fecha real y pasada. **Dato privado**: no se expone en la API pública |
| `LiteraryPreferences` | Conjunto de `Genre`, mínimo tres al completar el onboarding |
| `AccountStatus` | `PENDING_ACTIVATION`, `ACTIVE`, `DELETED` |
| `OnboardingStatus` | `PROFILE_PENDING`, `GENRES_PENDING`, `SUGGESTIONS_PENDING`, `COMPLETED` |
| `AuthProvider` | `LOCAL`, `GOOGLE`. `FACEBOOK` y `LINKEDIN` diferidos |

## Eventos publicados

| Evento | Cuándo | Consumidores |
|---|---|---|
| `UserRegistered` | Se crea una cuenta | `Credits` (crea la cuenta con **saldo 0**), `Notification` (correo de activación) |
| `AccountActivated` | El usuario activa su cuenta desde el correo | **`Credits`** (abona +20), `Notification`, `Feedback` (habilita recibir comentarios) |
| `ActivationEmailRequested` | Se pide reenviar el correo de activación | `Notification` |
| `LiteraryPreferencesUpdated` | El usuario fija o cambia sus géneros de interés | `Community` (sugerencias y recomendaciones) |
| `OnboardingCompleted` | Termina el onboarding | `Notification`, read models |
| `InvitedUserParticipated` | Un invitado deja su primer comentario | `Credits` (+5 al invitador) |
| `UserDeleted` | Se elimina la cuenta | Todos (limpieza y anonimización). En `User` borra además el nombre de usuario y sus alias, que quedan libres |
| `UserProfileUpdated` | Cambian datos públicos | `Community` (read models) |
| `UsernameChanged` | El usuario cambia su nombre de usuario | `Community` (read models que muestran el `@`) |

> `InvitedUserParticipated` requiere correlacionar la invitación con el primer comentario del
> invitado, que ocurre en `Feedback`. Diseño pendiente: ver `U-4`.

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
| U-15 | ¿Qué ocurre con el nombre y sus alias al eliminar la cuenta? | **Resuelta:** se borran y quedan libres de inmediato (`RN-13`) |
| U-16 | Al reciclarse el nombre de una cuenta eliminada, un enlace antiguo puede llevar a otra persona | Contrapartida asumida (`N-17`). Valorar un periodo de gracia |
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
