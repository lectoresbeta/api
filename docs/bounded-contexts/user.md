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
| `Account` | Ciclo de vida de la cuenta: alta, modificación, baja |
| `Authentication` | Credenciales, sesión, proveedores externos, recuperación |
| `Profile` | Datos públicos y preferencias literarias |
| `AuthorPage` | Página de autor y su personalización visual |
| `Invitation` | Invitaciones por email y su seguimiento |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `User` | `UserId` | Email único. Nombre de usuario único. Una cuenta eliminada no autentica. |
| `AuthorPage` | `UserId` | Pertenece a un único usuario |
| `PlatformInvitation` | `PlatformInvitationId` | Token único. Se consume una sola vez. |

### Value objects y enums

| Nombre | Reglas |
|---|---|
| `Email` | Formato válido, único, normalizado en minúsculas |
| `Username` | Único, longitud y caracteres por definir |
| `HashedPassword` | Nunca se expone ni se registra en logs |
| `LiteraryPreferences` | Conjunto de `Genre` |
| `AuthProvider` | `LOCAL`, `GOOGLE`, `FACEBOOK` |

## Eventos publicados

| Evento | Cuándo | Consumidores |
|---|---|---|
| `UserRegistered` | Se crea una cuenta | `Credits` (+20), `Notification` |
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
