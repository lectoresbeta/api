---
id: FEAT-USR-041
title: Cambiar o establecer la contraseña
context: User
concept: Account
actors: [User]
spec_status: APPROVED
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-22 (pestaña «Cuenta» de Configuración)
  - docs/ui/settings.md
endpoints:
  - PUT /me/password
events: [PasswordChanged]
depends_on: [FEAT-USR-001, FEAT-USR-002]
updated: 2026-09-24
---

# FEAT-USR-041 — Cambiar o establecer la contraseña

## Resumen

Dos campos en la pestaña «Cuenta»: **contraseña actual** y **nueva contraseña**.

## Faltan dos cosas en el formulario

| Ausente | Consecuencia |
|---|---|
| **Confirmación de la nueva contraseña** | Una errata deja al usuario fuera de su cuenta hasta que use «he olvidado mi contraseña» |
| **Los requisitos de la contraseña** | `FEAT-USR-001` los define; aquí no se muestran, así que el usuario los descubre al fallar |

Ninguna de las dos es una regla de backend, pero ambas cambian lo que el backend tiene que
devolver: los requisitos deben venir en el error, de forma que el cliente pueda mostrarlos
sin llevarlos codificados.

## Quien entró con Google deja la contraseña actual en blanco

`FEAT-USR-002` permite crear cuenta con Google. Esas cuentas **no tienen contraseña**, así
que no pueden aportar la «actual».

**Decidido (`S-8`): el campo se deja vacío.** El mismo formulario sirve para las dos
operaciones:

| Situación | Contraseña actual | Qué ocurre |
|---|---|---|
| Cuenta con contraseña | Obligatoria | Se sustituye |
| Cuenta sin contraseña | **En blanco** | Se **establece** por primera vez |

El backend acepta el campo vacío **solo** si la cuenta no tiene contraseña. En cuanto la
tiene, vuelve a ser obligatorio: no se puede usar este camino para saltarse `RN-1`.

### Lo que esto cuesta, dicho sin rodeos

En una cuenta de Google, **cualquiera con la sesión abierta puede ponerle contraseña sin
demostrar nada** y, a partir de ahí, entrar sin pasar por Google. Es un portátil sin bloquear
o una sesión robada, y no hay ningún paso que lo detenga.

Las dos defensas que quedan son el **aviso por correo** (`RN-4`) y el **cierre de las demás
sesiones** (`RN-3`). Por eso ninguna de las dos es opcional aquí: en este flujo concreto son
lo único que hay.

Si en algún momento se quiere cerrar ese hueco, la vía es pedir una **reautenticación con
Google** en lugar de la contraseña: mismo formulario, mismo campo vacío, y un paso de
confirmación con el proveedor antes de guardar (`S-40`).

## Reglas de negocio

- `RN-1` Cambiar la contraseña exige **la actual**, salvo que la cuenta no tenga ninguna
  (`RN-8`). Una sesión abierta no basta: es lo único que impide que quien se siente ante un
  portátil desbloqueado se quede con la cuenta.
- `RN-2` La nueva contraseña cumple las mismas reglas que en el registro (`FEAT-USR-001`
  `RN-3`).
- `RN-3` Al cambiarla, **se invalidan los tokens de refresco** de las demás sesiones. Con
  JWT eso significa que dejan de valer **en cuanto caduque su token de acceso, hasta 15
  minutos** ([`decision:0007`](../../decisions/0007-jwt-sessions.md)). Las operaciones de
  escritura, en cambio, se cortan de inmediato.
- `RN-4` Se **avisa por correo** del cambio. Es un aviso de seguridad, así que **no se puede
  desactivar** ([`FEAT-USR-039`](FEAT-USR-039-notification-preferences.md) `RN-3`).
- `RN-5` La contraseña se guarda **hasheada** con el algoritmo del proyecto. Nunca se registra
  en logs, ni en claro ni truncada.
- `RN-6` La nueva no puede ser igual a la actual.
- `RN-7` Establecer contraseña en una cuenta de Google **no desvincula** la cuenta de Google:
  quedan dos formas de entrar.
- `RN-8` El campo «contraseña actual» **solo puede ir vacío si la cuenta no tiene
  contraseña**. Una vez establecida, pasa a ser obligatorio. Esta comprobación la hace el
  servidor: un cliente que envíe el campo vacío contra una cuenta con contraseña recibe
  `403`, no un cambio.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Contraseña actual incorrecta | Se rechaza | `403` |
| Nueva contraseña no cumple los requisitos | Se rechaza | `422` con los requisitos |
| Nueva igual a la actual | Se rechaza | `422` |
| Cuenta sin contraseña, campo vacío | Se **establece** la contraseña | `204` |
| Cuenta **con** contraseña, campo vacío | Se rechaza | `403` |
| Intentos repetidos fallidos | Limitación de frecuencia | `429` |

`429` no es opcional: sin límite, el formulario de cambio se convierte en un sitio donde
probar contraseñas contra una sesión robada, sin las protecciones del login.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Cambiar o establecer | `PUT /me/password` | `changeMyPassword` |

La respuesta **no devuelve nada del usuario**. Devolver el perfil tras un cambio de
contraseña solo añade superficie a una respuesta que puede acabar en un log.

## Eventos

**Publica**

| Evento | Cuándo | Payload |
|---|---|---|
| `PasswordChanged` | Al cambiarla | `userId`, `changedAt`. **Nunca la contraseña, ni su hash** |

## Criterios de aceptación

- [ ] Sin la contraseña actual, el cambio se rechaza.
- [ ] Tras el cambio, los tokens de refresco de las demás sesiones quedan invalidados.
- [ ] Una escritura con un token de acceso anterior al cambio se rechaza de inmediato.
- [ ] Se envía aviso por correo aunque el usuario tenga las notificaciones desactivadas.
- [ ] Los requisitos incumplidos vienen en la respuesta de error.
- [ ] Una cuenta creada con Google puede **establecer** contraseña dejando la actual en blanco.
- [ ] Una cuenta que ya tiene contraseña **no** puede cambiarla dejando la actual en blanco.
- [ ] Establecerla no impide seguir entrando con Google.
- [ ] Ni la contraseña ni su hash aparecen en logs ni en eventos.
- [ ] Hay limitación de frecuencia.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| S-40 | ¿Se pedirá reautenticación con Google al establecer la primera contraseña? | Hoy no hay ningún paso que verifique quién está al teclado |
| S-30 | ¿Se puede desvincular Google tras establecer contraseña? | Si se permite, hay que impedir quedarse sin ninguna forma de entrar |
| S-31 | ¿Se comprueba la contraseña contra listas de contraseñas filtradas? | Mejora real y barata |

Resuelta: `S-8` (**campo vacío** en cuentas sin contraseña).

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `TODO`.
