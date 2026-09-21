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
| `Profile` | Datos públicos y preferencias literarias |
| `AuthorPage` | Página de autor y su personalización visual |
| `Invitation` | Invitaciones por email y su seguimiento |
| `Legal` | Documentos legales vigentes y el registro de aceptaciones |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `User` | `UserId` | Email único. Una cuenta eliminada no autentica. El estado sigue `PENDING_ACTIVATION → ACTIVE → DELETED`. |
| `AuthorPage` | `UserId` | Pertenece a un único usuario |
| `PlatformInvitation` | `PlatformInvitationId` | Token único. Se consume una sola vez. |
| `AccountActivationToken` | `UserId` | Un único token vigente por cuenta. Se almacena con hash, nunca en claro. |
| `LegalAcceptance` | `LegalAcceptanceId` | Inmutable. Registra documento, versión y fecha. |

### Value objects y enums

| Nombre | Reglas |
|---|---|
| `Email` | Formato válido, único, normalizado en minúsculas |
| `Username` | Existencia sin confirmar: el registro del diseño no lo pide (`OB-1`) |
| `HashedPassword` | ≥8 caracteres, una mayúscula, un número y un carácter especial. Nunca se expone ni se registra en logs |
| `BirthDate` | Fecha real y pasada. **Dato privado**: no se expone en la API pública |
| `LiteraryPreferences` | Conjunto de `Genre`, mínimo tres al completar el onboarding |
| `AccountStatus` | `PENDING_ACTIVATION`, `ACTIVE`, `DELETED` |
| `OnboardingStatus` | `PROFILE_PENDING`, `GENRES_PENDING`, `SUGGESTIONS_PENDING`, `COMPLETED` |
| `AuthProvider` | `LOCAL`, `GOOGLE`, `FACEBOOK`, `LINKEDIN` |

## Eventos publicados

| Evento | Cuándo | Consumidores |
|---|---|---|
| `UserRegistered` | Se crea una cuenta | `Credits` (+20), `Notification` (correo de activación) |
| `AccountActivated` | El usuario activa su cuenta desde el correo | `Notification`, y `Credits` si los créditos se atan a la activación (`OB-3`) |
| `ActivationEmailRequested` | Se pide reenviar el correo de activación | `Notification` |
| `LiteraryPreferencesUpdated` | El usuario fija o cambia sus géneros de interés | `Community` (sugerencias y recomendaciones) |
| `OnboardingCompleted` | Termina el onboarding | `Notification`, read models |
| `InvitedUserParticipated` | Un invitado deja su primer comentario | `Credits` (+5 al invitador) |
| `UserDeleted` | Se elimina la cuenta | Todos (limpieza y anonimización) |
| `UserProfileUpdated` | Cambian datos públicos | `Community` (read models) |

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
| U-6 | ¿Existe `Username`? El registro del diseño no lo pide, pero el onboarding saluda con un alias (`OB-1`) | **Bloqueante** para `FEAT-USR-001` y `FEAT-USR-022` |
| U-7 | ¿Qué puede hacer una cuenta `PENDING_ACTIVATION`? (`OB-3`) | Superficie de abuso, y dónde se abonan los créditos |
| U-8 | ¿Es «Nombre» el nombre real privado o el nombre público de autor? (`OB-2`) | Determina qué devuelve el perfil público |
| U-9 | ¿Hay edad mínima para registrarse? (`OB-7`) | Legal: se recoge la fecha de nacimiento sin motivo declarado |

### Datos privados

Nunca se exponen en el perfil público ni en ningún listado, búsqueda o sugerencia:

- fecha de nacimiento;
- email;
- el «Nombre» del paso 1 del onboarding, mientras `OB-2` no aclare lo contrario.

El diseño lo dice de forma explícita en el tooltip de ambos campos: *«Esta información solo
será visible para ti y el equipo de LectoresBeta.»*
