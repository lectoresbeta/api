---
id: FEAT-USR-041
title: Cambiar o establecer la contraseña
context: User
concept: Account
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-22 (pestaña «Cuenta» de Configuración)
  - docs/ui/settings.md
endpoints:
  - PUT /me/password
events: [PasswordChanged]
depends_on: [FEAT-USR-001, FEAT-USR-002]
updated: 2026-09-22
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

## Quien entró con Google no tiene contraseña

`FEAT-USR-002` permite crear cuenta con Google. Esas cuentas **no tienen contraseña**, así
que pedirles la «actual» es pedirles algo que no existe.

Lo que necesitan es lo contrario: **establecer** una por primera vez, normalmente para poder
entrar también con correo y contraseña.

| Situación | Qué se le pide | Qué hace |
|---|---|---|
| Cuenta con contraseña | La actual y la nueva | La sustituye |
| Cuenta solo de Google | Reautenticación con Google | La **establece** |

Son dos operaciones distintas tras el mismo formulario, y la interfaz solo contempla una
(`S-8`).

## Reglas de negocio

- `RN-1` Cambiar la contraseña exige **la actual**. Una sesión abierta no basta: es lo único
  que impide que quien se siente ante un portátil desbloqueado se quede con la cuenta.
- `RN-2` La nueva contraseña cumple las mismas reglas que en el registro (`FEAT-USR-001`
  `RN-3`).
- `RN-3` Al cambiarla, **se cierran las demás sesiones**. Si el motivo del cambio es una
  sospecha, dejarlas abiertas anula el gesto.
- `RN-4` Se **avisa por correo** del cambio. Es un aviso de seguridad, así que **no se puede
  desactivar** ([`FEAT-USR-039`](FEAT-USR-039-notification-preferences.md) `RN-3`).
- `RN-5` La contraseña se guarda **hasheada** con el algoritmo del proyecto. Nunca se registra
  en logs, ni en claro ni truncada.
- `RN-6` La nueva no puede ser igual a la actual.
- `RN-7` Establecer contraseña en una cuenta de Google **no desvincula** la cuenta de Google:
  quedan dos formas de entrar.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Contraseña actual incorrecta | Se rechaza | `403` |
| Nueva contraseña no cumple los requisitos | Se rechaza | `422` con los requisitos |
| Nueva igual a la actual | Se rechaza | `422` |
| Cuenta sin contraseña | Flujo de establecer (`S-8`) | — |
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
- [ ] Tras el cambio, las demás sesiones dejan de ser válidas.
- [ ] Se envía aviso por correo aunque el usuario tenga las notificaciones desactivadas.
- [ ] Los requisitos incumplidos vienen en la respuesta de error.
- [ ] Una cuenta creada con Google puede **establecer** contraseña sin aportar una anterior.
- [ ] Establecerla no impide seguir entrando con Google.
- [ ] Ni la contraseña ni su hash aparecen en logs ni en eventos.
- [ ] Hay limitación de frecuencia.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **S-8** | ¿Qué ve una cuenta de Google en esta pestaña? | Hoy se le pide algo que no tiene |
| S-30 | ¿Se puede desvincular Google tras establecer contraseña? | Si se permite, hay que impedir quedarse sin ninguna forma de entrar |
| S-31 | ¿Se comprueba la contraseña contra listas de contraseñas filtradas? | Mejora real y barata |

## Estado

**Especificación:** `DRAFT`. `S-8` bloquea `APPROVED`.

**Implementación:** `TODO`.
