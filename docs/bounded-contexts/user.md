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
| `User` | `UserId` | Email único. Una cuenta eliminada no autentica. El estado sigue `PENDING_ACTIVATION → ACTIVE → DELETED`. Solo `ACTIVE` puede escribir. |
| `AuthorPage` | `UserId` | Pertenece a un único usuario |
| `PlatformInvitation` | `PlatformInvitationId` | Token único. Se consume una sola vez. |
| `AccountActivationToken` | `UserId` | Un único token vigente por cuenta. Se almacena con hash, nunca en claro. |
| `LegalAcceptance` | `LegalAcceptanceId` | Inmutable. Registra documento, versión y fecha. |

### Value objects y enums

| Nombre | Reglas |
|---|---|
| `Email` | Formato válido, único, normalizado en minúsculas |
| ~~`Username`~~ | **No existe.** La plataforma no maneja nombre de usuario. El alias del saludo se deriva del email y no se persiste |
| `HashedPassword` | ≥8 caracteres, una mayúscula, un número y un carácter especial. Nunca se expone ni se registra en logs |
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
| **U-8** | **Sin nombre de usuario y con el «Nombre» declarado privado, ¿qué nombre se muestra públicamente?** (`OB-2`) | **Bloqueante.** Afecta al perfil, al catálogo, al muro, a los rankings y a las sugerencias |
| U-6 | ¿Existe `Username`? | **Resuelto:** no. No se usa el concepto |
| U-7 | ¿Qué puede hacer una cuenta `PENDING_ACTIVATION`? | **Resuelto:** leer y completar el onboarding. Ver `decision:0003` |
| U-9 | ¿Hay edad mínima para registrarse? (`OB-7`) | Legal: se recoge la fecha de nacimiento sin motivo declarado |

### Datos privados

Nunca se exponen en el perfil público ni en ningún listado, búsqueda o sugerencia:

- fecha de nacimiento;
- email;
- el «Nombre» del paso 1 del onboarding, mientras `OB-2` no aclare lo contrario.

Como tampoco existe nombre de usuario, hoy **no hay ningún nombre que se pueda mostrar
públicamente**. Es el hueco que `OB-2` tiene que cerrar.

El diseño lo dice de forma explícita en el tooltip de ambos campos: *«Esta información solo
será visible para ti y el equipo de LectoresBeta.»*
