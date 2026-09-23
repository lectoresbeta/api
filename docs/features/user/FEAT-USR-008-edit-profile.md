---
id: FEAT-USR-008
title: Editar el perfil — nombre, nombre de usuario, biografía, géneros y foto
context: User
concept: Profile
actors: [User]
spec_status: APPROVED
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-22 (pestaña «Perfil» de Configuración)
  - docs/ui/settings.md
endpoints:
  - GET /me/profile
  - PATCH /me/profile
events: [UserProfileUpdated]
depends_on: [FEAT-USR-022, FEAT-USR-034, FEAT-USR-009, FEAT-USR-037]
updated: 2026-09-24
---

# FEAT-USR-008 — Editar el perfil

## Resumen

La pestaña «Perfil» reúne todo lo que el usuario muestra de sí mismo:

| Campo | Control | Quién lo especifica |
|---|---|---|
| Nombre | Texto | Esta ficha. Es el nombre **público** (`OB-2`) |
| **Nombre de usuario** | Texto | [`FEAT-USR-034`](FEAT-USR-034-change-username.md), con sus reglas propias |
| Biografía | Área de texto, **300 caracteres** | Esta ficha |
| **Preferencias literarias** | Géneros | `FEAT-USR-009` |
| Foto de perfil | Avatar + modal de recorte | [`FEAT-USR-037`](FEAT-USR-037-upload-profile-photo.md) |

Las capturas solo muestran nombre, biografía y foto; producto confirma que la pestaña incluye
también el nombre de usuario y las preferencias literarias. Con eso, `FEAT-USR-034` y
`FEAT-USR-009` dejan de estar sin interfaz.

La ficha original hablaba de «editar datos de usuario (email, nombre, contraseña, datos
personales)». El diseño los **reparte en pestañas distintas**, y conviene seguir ese reparto:
correo y contraseña son operaciones de seguridad
([`FEAT-USR-040`](FEAT-USR-040-change-email.md),
[`FEAT-USR-041`](FEAT-USR-041-change-password.md)), no edición de perfil.

## Una pestaña, cinco campos con reglas muy distintas

Es lo que hace incómoda esta pantalla: presenta como un formulario uniforme cosas que no lo
son.

| Campo | Al guardar |
|---|---|
| Nombre | Se sustituye |
| Biografía | Se sustituye |
| Géneros | Se sustituyen |
| Foto | Flujo propio de subida |
| **Nombre de usuario** | **Bloquea al usuario 30 días y reserva el anterior como alias** |

El último no se parece a los demás. Guardar una biografía es inocuo; guardar un nombre de
usuario tiene consecuencias que duran un mes y que la pantalla no advierte.

De ahí dos reglas que no serían necesarias si la pestaña tuviera un solo tipo de campo:
`RN-8` y `RN-9`.

Sigue sin haber sitio para la **fecha de nacimiento**, que se pide en el onboarding y no
reaparece (`S-27`).

## Reglas de negocio

- `RN-1` El **nombre es público**. No es un dato privado, a diferencia de la fecha de
  nacimiento (`OB-2`).
- `RN-2` El nombre es obligatorio: no puede quedar vacío. Es lo que identifica al usuario en
  toda la interfaz.
- `RN-3` La **biografía es opcional** y está limitada a **300 caracteres**.
- `RN-4` La biografía es **texto plano**. Admitir enlaces o formato la convierte en un sitio
  desde el que hacer spam.
- `RN-5` Cambiar el nombre **no cambia el nombre de usuario**. Son campos distintos: uno se
  lee, el otro se escribe en la URL.
- `RN-6` El nombre no tiene por qué ser único (`N-2`).
- `RN-7` Nombre y biografía pasan por las mismas reglas de contenido que cualquier texto
  publicado.
- `RN-8` El **nombre de usuario se valida y se cambia por su propio camino**
  ([`FEAT-USR-034`](FEAT-USR-034-change-username.md)), aunque la pantalla lo muestre junto a
  los demás campos. Sus reglas —una vez cada 30 días, alias del anterior— no son negociables
  por el hecho de compartir formulario.
- `RN-9` Si el nombre de usuario pedido no está libre o el usuario aún no puede cambiarlo,
  **el resto del guardado no se pierde**: nombre, biografía y géneros se guardan igualmente y
  el error señala solo el campo que falló (`S-37`).
- `RN-10` Las **preferencias literarias** son las mismas del onboarding
  ([`FEAT-USR-023`](FEAT-USR-023-onboarding-select-genres.md)), incluido su mínimo de tres
  géneros. Esta pantalla las edita, no define reglas nuevas.

`RN-4` merece énfasis: la biografía es de los pocos campos libres que un usuario nuevo puede
rellenar antes de tener reputación alguna, lo que la convierte en el primer sitio donde
alguien intentará colocar enlaces.

## La biografía: 300 caracteres, y es la descripción del perfil

La maqueta mostraba un contador `0 / 100`. Cien caracteres son unas quince palabras: para una
plataforma de escritores no llega ni para decir qué escribe uno y qué busca. **Se amplía a
300** (`S-4`, resuelta), unas cincuenta palabras, tres o cuatro líneas bajo la foto.

**Se mide en caracteres, no en palabras.** Es una excepción deliberada respecto al resto del
producto, que mide en palabras (`R-5`), y tiene un motivo: aquellos límites miden **esfuerzo**
—lo que cuesta escribir una corrección—, mientras que este mide **espacio**, el que ocupa el
texto bajo una foto de perfil. Para el espacio, la unidad correcta es el carácter.

**Es el mismo texto que la «Descripción» del perfil**, la que se edita en línea bajo la foto
([`FEAT-USR-028`](FEAT-USR-028-own-profile-header.md)). Un solo campo con dos puntos de
edición:

| Dónde | Cómo |
|---|---|
| Mi perfil | Edición en línea |
| Configuración › Perfil | Junto al resto del formulario |

Ambos escriben en el mismo sitio, con el mismo límite y el mismo saneado. Si la edición en
línea admitiera más texto, el usuario podría escribir algo que después no puede guardar desde
Configuración.

Queda un resto de `S-5`: `FEAT-USR-015` habla de una «página de autor» con «bio, foto y
referencias». Si esa bio es esta, ya está resuelto; si es un texto largo aparte, son dos
campos y deben llamarse distinto.

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
| Modificarlo | `PATCH /me/profile` | `updateMyProfile` |
| Cambiar el nombre de usuario | `PUT /me/username` | `changeUsername` |
| Preferencias literarias | `PUT /me/literary-preferences` | `updateLiteraryPreferences` |

Es `PATCH` y no `PUT` porque el mismo recurso se edita **campo a campo** desde el perfil
(`FEAT-USR-028`) y **en bloque** desde Configuración. Un `PUT` obligaría a la edición en línea
a reenviar campos que no está tocando.

**El nombre de usuario mantiene su endpoint.** Un solo «Guardar» en la interfaz no obliga a
una sola llamada, y aquí conviene que sean dos: el cambio de nombre de usuario tiene
validaciones propias, un límite temporal y efectos que duran 30 días. Mezclarlo en el mismo
`PATCH` significaría que una biografía no se guarda porque el nombre de usuario está ocupado
(`RN-9`).

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

- [ ] El usuario puede cambiar nombre, biografía y géneros, y ver el cambio en su perfil.
- [ ] La biografía admite 300 caracteres y los muestra bajo la foto en «Mi perfil».
- [ ] Editar la descripción en línea y editarla desde Configuración modifican el mismo campo.
- [ ] Un nombre de usuario ocupado no impide guardar el resto de la pestaña.
- [ ] Un nombre vacío se rechaza.
- [ ] La biografía admite el límite documentado y rechaza lo que lo supere.
- [ ] La biografía no interpreta formato ni enlaces.
- [ ] Cambiar el nombre no altera el nombre de usuario ni la URL del perfil.
- [ ] El nombre y el avatar nuevos aparecen donde ya se mostraban los anteriores.
- [ ] Dos ediciones concurrentes no producen cambios perdidos.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **S-37** | Si el nombre de usuario falla, ¿se guarda el resto? | `RN-9` propone que sí |
| S-27 | ¿Se puede corregir la fecha de nacimiento? | Se pide en el onboarding y no reaparece |
| S-28 | ¿El nombre y el avatar se resuelven en lectura o se propagan a los read models? | Afecta a muro, comentarios y catálogo |
| S-29 | ¿Puede editar el perfil una cuenta sin activar? | `decision:0003` no lo resuelve explícitamente |
| S-5 | ¿La «página de autor» tiene un texto aparte de la biografía? | Si lo tiene, son dos campos |
| S-39 | ¿Avisa la interfaz antes de un cambio de nombre de usuario? | Bloquea 30 días sin decirlo |

Resueltas: `S-3` (el nombre de usuario **sí** se cambia aquí), `S-18` (las preferencias
literarias también) y `S-4` (**300 caracteres**, y es la descripción del perfil).

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `TODO`.
