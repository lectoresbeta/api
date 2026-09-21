---
id: FEAT-USR-025
title: Bloquear las operaciones de escritura hasta activar la cuenta
context: User
concept: Account
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - conversation:2026-09-21
  - docs/ui/account-creation.md
  - decision:0003
endpoints: []
events: [AccountActivated]
depends_on: [FEAT-USR-020]
updated: 2026-09-21
---

# FEAT-USR-025 — Bloquear las operaciones de escritura hasta activar la cuenta

## Resumen

Una cuenta en `PENDING_ACTIVATION` puede navegar y completar el onboarding, pero **no puede
escribir nada en la plataforma**: ni comentar, ni recibir comentarios, ni publicar en el
muro, ni ninguna otra operación que cree o modifique contenido.

Es la barrera que convierte la verificación del email en el coste de entrada al sistema.
Sin ella, registrar cuentas falsas sería gratis y el crédito por invitación (`FEAT-CRD-005`)
sería explotable en masa.

## Actores y autorización

| Estado de la cuenta | Lectura | Onboarding | Escritura |
|---|---|---|---|
| `PENDING_ACTIVATION` | Sí | Sí | **No** |
| `ACTIVE` | Sí | Sí | Sí |
| `DELETED` | No | No | No |

## Reglas de negocio

- `RN-1` Toda operación que cree, modifique o elimine contenido requiere `AccountStatus:
  ACTIVE`.
- `RN-2` La comprobación es **de servidor y centralizada**. No se replica endpoint por
  endpoint ni se delega en el frontend.
- `RN-3` Una operación bloqueada responde `403` con `code: ACCOUNT_NOT_ACTIVATED`. La
  respuesta indica que la cuenta necesita activarse, para que la interfaz pueda ofrecer el
  reenvío del correo (`FEAT-USR-021`).
- `RN-4` **Las obras de un autor sin activar no admiten comentarios.** El bloqueo no es solo
  sobre lo que el usuario hace, también sobre lo que recibe.
- `RN-5` Los pasos del onboarding son la única excepción: escriben datos de perfil y deben
  funcionar en `PENDING_ACTIVATION`.
- `RN-6` La gestión de la propia cuenta —cambiar contraseña, reenviar activación, eliminar la
  cuenta— sigue disponible.
- `RN-7` Al activarse la cuenta todas las operaciones quedan habilitadas de inmediato, sin
  que el usuario tenga que hacer nada más.

`RN-4` merece atención: una cuenta sin activar que publicase una obra generaría solicitudes
de lectores beta que nunca podrían comentar, y consumiría trabajo ajeno sin poder pagarlo en
créditos. Bloquear la recepción evita ese estado inconsistente.

## Operaciones bloqueadas

| Área | Operaciones |
|---|---|
| `Work` | Crear, editar y eliminar obras y fragmentos; configurar visibilidad, modalidad de acceso y cuestionario; generar enlaces públicos |
| `Reading` | Solicitar acceso de LB, aceptar o rechazar solicitudes, invitar, crear grupos, proponer writing buddy |
| `Feedback` | Dejar feedback, valorar obras, responder al cuestionario, contestar, valorar y ocultar comentarios |
| `Feedback` (recepción) | **Recibir comentarios sobre las obras propias** (`RN-4`) |
| `Community` | Publicar en el muro, comentar, reaccionar, apoyar, suscribirse a autores, enviar mensajes directos |
| `User` | Invitar personas a la plataforma, editar la página de autor |

## Operaciones permitidas

| Área | Operaciones |
|---|---|
| Onboarding | Los tres pasos completos (`FEAT-USR-022`, `FEAT-USR-023`, `FEAT-COM-016`) |
| Lectura | Catálogo de obras, perfiles públicos, muro, rankings, géneros |
| Cuenta | Ver y editar datos propios, cambiar contraseña, reenviar activación, eliminar cuenta |
| Créditos | Consultar saldo e historial, que estarán a cero hasta activar |

> El paso 3 del onboarding permite seguir autores, que en otro contexto es escritura. Se
> admite porque forma parte del onboarding (`RN-5`) y porque seguir a alguien no produce
> contenido ni consume créditos ajenos.

## Dónde vive la regla

Es una regla de autorización transversal, no de un caso de uso concreto. Vive como **política
única** aplicada en el borde HTTP, no repetida en cada controlador. Ver
[`../../architecture/06-security-and-authorization.md`](../../architecture/06-security-and-authorization.md)
y [`decision:0003`](../../decisions/0003-write-operations-require-activated-account.md).

`RN-4` es el caso que no encaja en ese borde: no depende de quién llama, sino del estado del
autor de la obra. `Feedback` necesita conocer si el autor está activo, y lo hace por el
camino habitual entre contextos —un evento de integración y una proyección local—, nunca
consultando las tablas de `User`.

## Criterios de aceptación

- [ ] Una cuenta `PENDING_ACTIVATION` que intenta crear una obra recibe `403` con `ACCOUNT_NOT_ACTIVATED`.
- [ ] Lo mismo al intentar dejar feedback, publicar en el muro o enviar un mensaje directo.
- [ ] Una cuenta `PENDING_ACTIVATION` completa los tres pasos del onboarding sin bloqueo.
- [ ] Una cuenta `PENDING_ACTIVATION` puede leer el catálogo y los perfiles públicos.
- [ ] Una cuenta `PENDING_ACTIVATION` puede cambiar su contraseña y pedir el reenvío de activación.
- [ ] Nadie puede dejar feedback sobre una obra cuyo autor está sin activar.
- [ ] Tras activar la cuenta, todas las operaciones funcionan sin ningún paso adicional.
- [ ] La comprobación está implementada una sola vez, no repetida en cada controlador.
- [ ] El error distingue «cuenta sin activar» de «sin permiso», para que la interfaz pueda ofrecer el reenvío.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| A-1 | ¿Caduca una cuenta que nunca se activa? ¿Se purga pasado un tiempo? | Acumulación de cuentas muertas |
| A-2 | ¿Qué ve el usuario sin activar al intentar escribir: aviso persistente o error al enviar? | Diseño pendiente |

## Estado

**Especificación:** `DRAFT`. El alcance del bloqueo está decidido; falta el diseño de cómo se
comunica al usuario (`A-2`).

**Implementación:** `TODO`.
