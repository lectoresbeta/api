---
id: FEAT-USR-008
title: Editar el perfil — nombre, biografía y foto
context: User
concept: Profile
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-22 (pestaña «Perfil» de Configuración)
  - docs/ui/settings.md
endpoints:
  - GET /me/profile
  - PUT /me/profile
events: [UserProfileUpdated]
depends_on: [FEAT-USR-022, FEAT-USR-037]
updated: 2026-09-22
---

# FEAT-USR-008 — Editar el perfil

## Resumen

La pestaña «Perfil» permite cambiar tres cosas: **nombre**, **biografía** y **foto**.

| Campo | Control | Notas |
|---|---|---|
| Nombre | Texto | Es el nombre **público** del usuario (`OB-2`) |
| Biografía | Área de texto | Contador `0 / 100` |
| Foto de perfil | Avatar + zona de subida | [`FEAT-USR-037`](FEAT-USR-037-upload-profile-photo.md) |

La ficha original hablaba de «editar datos de usuario (email, nombre, contraseña, datos
personales)». El diseño los **reparte en pestañas distintas**, y conviene seguir ese reparto:
correo y contraseña son operaciones de seguridad
([`FEAT-USR-040`](FEAT-USR-040-change-email.md),
[`FEAT-USR-041`](FEAT-USR-041-change-password.md)), no edición de perfil.

## Qué no está aquí

Tres cosas que la documentación esperaba encontrar y no aparecen:

| Ausente | Dónde debería estar |
|---|---|
| **Nombre de usuario** | `FEAT-USR-034` lo especifica con alias y límite de 30 días, y `PUT /me/username` existe. **Falta la pantalla que lo llame** (`S-3`) |
| **Fecha de nacimiento** | Se pide en el onboarding (`FEAT-USR-022`). ¿No se puede corregir un error? (`S-27`) |
| **Preferencias literarias** | `FEAT-USR-009`. Tampoco están (`S-18`) |

`S-3` es la más seria: toda la maquinaria de alias de
[`decision:0005`](../../decisions/0005-username-with-temporary-aliases.md) —bloqueo de 30
días, enlaces antiguos que siguen resolviendo, purga diaria— existe **para hacer posible un
cambio que hoy no tiene interfaz**.

## Reglas de negocio

- `RN-1` El **nombre es público**. No es un dato privado, a diferencia de la fecha de
  nacimiento (`OB-2`).
- `RN-2` El nombre es obligatorio: no puede quedar vacío. Es lo que identifica al usuario en
  toda la interfaz.
- `RN-3` La **biografía es opcional** y está limitada a 100 (`S-4`).
- `RN-4` La biografía es **texto plano**. Admitir enlaces o formato la convierte en un sitio
  desde el que hacer spam.
- `RN-5` Cambiar el nombre **no cambia el nombre de usuario**. Son campos distintos: uno se
  lee, el otro se escribe en la URL.
- `RN-6` El nombre no tiene por qué ser único (`N-2`).
- `RN-7` Nombre y biografía pasan por las mismas reglas de contenido que cualquier texto
  publicado.

`RN-4` merece énfasis: la biografía es de los pocos campos libres que un usuario nuevo puede
rellenar antes de tener reputación alguna, lo que la convierte en el primer sitio donde
alguien intentará colocar enlaces.

## La biografía cabe en 100 caracteres

Cien caracteres son unas quince palabras. **Para una plataforma de escritores es muy poco**:
no llega para decir qué escribe uno y qué busca.

Además queda por aclarar si se cuentan **caracteres o palabras** —el resto del producto mide
en palabras (`R-5`)— y si esta biografía es la misma que la de la «página de autor» de
`FEAT-USR-015`, que describe «bio, foto y referencias». Si es la misma, cien caracteres la
vacían; si no, hay **dos biografías** y hay que decir cuál se ve dónde (`S-5`).

## Flujo principal

1. El usuario abre «Configuración › Perfil».
2. Modifica nombre, biografía o foto.
3. Guarda.
4. Se publica `UserProfileUpdated`.
5. Los read models que muestran el nombre y el avatar se actualizan.

El paso 5 no es menor: el nombre y la foto aparecen **copiados** en el muro, en los
comentarios, en el catálogo y en las notificaciones. O se resuelven en lectura, o hay que
propagarlos (`S-28`).

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Nombre vacío | Se rechaza | `422` |
| Biografía por encima del límite | Se rechaza | `422` |
| Cuenta sin activar | Ver abajo | — |
| Edición concurrente | Gana la primera, la segunda recibe conflicto | `409` con `If-Match` |

**Cuenta sin activar:** [`decision:0003`](../../decisions/0003-write-operations-require-activated-account.md)
bloquea las operaciones de escritura hasta activar el correo. Editar el propio perfil es un
caso límite —no produce contenido visible para otros, salvo por el nombre y la foto, que sí
lo son—. Conviene decidirlo explícitamente en vez de heredarlo (`S-29`).

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Consultar mi perfil editable | `GET /me/profile` | `getMyProfile` |
| Modificarlo | `PUT /me/profile` | `updateMyProfile` |

La foto **no viaja en este `PUT`**: tiene su propio flujo de subida
([`FEAT-USR-037`](FEAT-USR-037-upload-profile-photo.md)). Mezclar un fichero con dos campos
de texto obligaría a un `multipart` en el que un fallo de subida tiraría también el cambio
de nombre.

## Eventos

**Publica**

| Evento | Cuándo | Payload |
|---|---|---|
| `UserProfileUpdated` | Al guardar | `userId`, campos modificados |

Lo consume `Community` para sus read models.

## Modelo de datos afectado

Campos de `user`: `display_name`, `bio`. La foto la gestiona `FEAT-USR-037`.

## Criterios de aceptación

- [ ] El usuario puede cambiar nombre y biografía y ver el cambio reflejado en su perfil.
- [ ] Un nombre vacío se rechaza.
- [ ] La biografía admite el límite documentado y rechaza lo que lo supere.
- [ ] La biografía no interpreta formato ni enlaces.
- [ ] Cambiar el nombre no altera el nombre de usuario ni la URL del perfil.
- [ ] El nombre y el avatar nuevos aparecen donde ya se mostraban los anteriores.
- [ ] Dos ediciones concurrentes no producen cambios perdidos.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **S-3** | ¿Dónde se cambia el nombre de usuario? | `FEAT-USR-034` se queda sin interfaz |
| **S-4** | ¿100 de biografía es deliberado? ¿Caracteres o palabras? | Para escritores es muy poco |
| **S-5** | ¿Es la misma biografía que la «página de autor» de `FEAT-USR-015`? | O hay dos |
| S-27 | ¿Se puede corregir la fecha de nacimiento? | Se pide en el onboarding y no reaparece |
| S-18 | ¿Dónde se editan las preferencias literarias? | `FEAT-USR-009` sin interfaz |
| S-28 | ¿El nombre y el avatar se resuelven en lectura o se propagan a los read models? | Afecta a muro, comentarios y catálogo |
| S-29 | ¿Puede editar el perfil una cuenta sin activar? | `decision:0003` no lo resuelve explícitamente |

## Estado

**Especificación:** `DRAFT`.

**Implementación:** `TODO`.
