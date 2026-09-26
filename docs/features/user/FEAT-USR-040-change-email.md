---
id: FEAT-USR-040
title: Cambiar el correo de la cuenta
context: User
concept: Account
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - conversation:2026-09-22 (pestaña «Cuenta» de Configuración)
  - docs/ui/settings.md
endpoints:
  - POST /me/email-change
  - POST /me/email-change/confirm
events: [EmailChangeRequested, EmailChanged]
depends_on: [FEAT-USR-020, FEAT-USR-033]
updated: 2026-09-24
---

# FEAT-USR-040 — Cambiar el correo de la cuenta

## Resumen

La pestaña «Cuenta» muestra el correo como un campo editable más. **No lo es.**

El correo es la identidad de la cuenta: sirve para iniciar sesión, es por donde se recupera
la contraseña y fue el origen del nombre de usuario por defecto
([`FEAT-USR-033`](FEAT-USR-033-username-assignment.md)). Cambiarlo es un **flujo en dos
pasos con verificación**, no una edición.

## Por qué no puede ser un campo que se guarda

Si el cambio fuese inmediato, quien consiguiera una sesión ajena —un portátil sin bloquear,
una sesión robada— podría apuntar la cuenta a su propio buzón y, acto seguido, pedir
«he olvidado mi contraseña». **La toma de control sería completa y el titular no se
enteraría.**

Todo lo que sigue existe para cerrar ese camino.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Pedir el cambio de **su** correo | Sesión iniciada y cuenta activada |
| `User` | Confirmarlo | Desde el enlace enviado al correo **nuevo** |

## Reglas de negocio

- `RN-1` El cambio requiere **la contraseña actual** (`S-7`). En cuentas creadas con Google,
  una reautenticación equivalente.
- `RN-2` El correo nuevo **debe verificarse**. Hasta entonces **el correo válido sigue siendo
  el anterior**: es con el que se inicia sesión y al que llegan los avisos.
- `RN-3` Se **avisa al correo anterior** de que se ha solicitado el cambio. Es la única
  defensa de quien ha perdido el control de su sesión, así que este aviso **no se puede
  desactivar** desde las preferencias de notificación.
- `RN-4` El correo nuevo no puede estar en uso por otra cuenta.
- `RN-5` El enlace de confirmación **caduca** y es de un solo uso.
- `RN-6` Solo puede haber **una solicitud de cambio vigente**. Una nueva anula la anterior.
- `RN-7` El cambio **no re-deriva el nombre de usuario**. Ya está asignado y es estable; el
  correo solo lo originó la primera vez.
- `RN-8` Al confirmarse, **se invalidan los tokens de refresco** de las demás sesiones. Con
  JWT el corte completo tarda hasta 15 minutos
  ([`decision:0007`](../../decisions/0007-jwt-sessions.md)); las escrituras se cortan ya.
- `RN-9` El cambio **no altera el estado de activación** de la cuenta: quien ya estaba
  activado sigue estándolo. La verificación del correo nuevo es parte del propio flujo.

`RN-3` y `RN-8` son las dos que convierten esto en un mecanismo de seguridad y no en un
trámite. Sin ellas, el flujo verifica que el correo nuevo existe, que es justo lo que un
atacante puede demostrar.

## Flujo principal

1. El usuario escribe el correo nuevo y su contraseña actual.
2. El sistema comprueba la contraseña y que el correo esté libre.
3. Se registra una solicitud de cambio pendiente.
4. Se envía un enlace de confirmación **al correo nuevo**.
5. Se envía un aviso **al correo anterior**.
6. El usuario abre el enlace.
7. El correo se sustituye, se cierran las demás sesiones y la solicitud se consume.

Entre 3 y 6 la cuenta sigue funcionando con normalidad **con el correo antiguo**. No hay
estado intermedio degradado.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Contraseña incorrecta | Se rechaza | `403` |
| Correo con formato inválido | Se rechaza | `422` |
| Correo ya en uso | Ver abajo | `422` genérico |
| Correo igual al actual | Se rechaza | `422` |
| Enlace caducado | Se rechaza, se puede pedir de nuevo | `410` |
| Enlace ya usado | Se rechaza | `410` |
| Segunda solicitud | Anula la primera | `202` |
| Cuenta de Google sin contraseña | Reautenticación equivalente (`S-8`) | — |

**Sobre «correo ya en uso»:** decirlo tal cual convierte el formulario en un comprobador de
quién tiene cuenta en la plataforma. Es el mismo problema que en el registro
([`FEAT-USR-001`](FEAT-USR-001-register-with-email.md)) y merece el mismo tratamiento:
respuesta genérica, y si acaso un aviso por correo a la dirección afectada.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Solicitar el cambio | `POST /me/email-change` | `requestEmailChange` |
| Confirmarlo | `POST /me/email-change/confirm` | `confirmEmailChange` |

La solicitud devuelve `202`: lo que ocurre a continuación es un correo, no una respuesta.

La confirmación **no requiere sesión iniciada** —el enlace puede abrirse en otro
dispositivo—, pero el token es de un solo uso y va ligado a la solicitud concreta.

## Eventos

**Publica**

| Evento | Cuándo | Payload |
|---|---|---|
| `EmailChangeRequested` | Al solicitar | `userId`, `requestId`, `expiresAt`. **Nunca el correo nuevo en claro hacia otros contextos** |
| `EmailChanged` | Al confirmar | `userId`, `changedAt` |

`Notification` necesita las direcciones para enviar los dos correos, pero el resto de
contextos no tiene nada que hacer con ellas. Un evento que reparte direcciones de correo por
la cola es una filtración esperando a ocurrir.

## Modelo de datos afectado

`email_change_request`: `id`, `user_id`, `new_email`, `token_hash`, `expires_at`,
`consumed_at`.

El token se guarda **hasheado**, como el de activación: quien lea la tabla no debe poder
usarlo.

Índice único parcial por `user_id` sobre las solicitudes vigentes, que es la forma de
garantizar `RN-6` bajo concurrencia.

## Criterios de aceptación

- [x] El correo no cambia hasta que se confirma desde el correo nuevo.
- [x] Mientras hay una solicitud pendiente, se sigue pudiendo iniciar sesión con el anterior.
- [x] El correo anterior recibe aviso de la solicitud.
- [x] Ese aviso se envía **aunque el usuario tenga todas las notificaciones desactivadas**.
- [x] Sin la contraseña actual, la solicitud se rechaza.
- [x] Al confirmar se invalidan los tokens de refresco de las demás sesiones.
- [x] El nombre de usuario no cambia.
- [x] Un correo ya registrado no se revela como tal.
- [x] El token está hasheado en base de datos, caduca y es de un solo uso.
- [x] Una segunda solicitud invalida la primera.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **S-8** | En cuentas de Google, ¿qué sustituye a la contraseña actual? | Hoy el formulario pide algo que no existe |
| S-22 | ¿Cuánto dura el enlace de confirmación? | Propuesta: lo mismo que el de activación |
| S-23 | ¿Puede revertirse el cambio desde el aviso al correo antiguo? | Sería la defensa más fuerte, y añade otro flujo |
| S-24 | ¿Se limita la frecuencia de cambios de correo? | Sin límite, es un vector de abuso barato |

`S-23` merece una decisión consciente: un enlace de «yo no he pedido esto» en el aviso al
correo anterior es lo que convierte el aviso en algo accionable. Sin él, el titular se entera
pero no puede hacer nada por sí mismo.

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `DONE` (2026-09-25), con una excepción declarada.

**`S-8` sigue abierta, así que este camino está cerrado para las cuentas sin contraseña.** Una
creada con Google no tiene contraseña actual que aportar, y abrirle el paso sin nada que
demostrar convertiría una sesión robada en un cambio de dueño — que es justo lo que `RN-1`
existe para impedir. Responde `CURRENT_PASSWORD_REQUIRED` hasta que se decida qué sustituye a
la contraseña.

`S-22` resuelta: el enlace dura lo mismo que el de activación, dos días. Confirmar una
dirección nueva es la misma clase de prueba, y quien pide el cambio puede no mirar ese buzón
hasta el día siguiente.

`RN-8` se implementa cerrando **todas** las sesiones y no solo «las demás», por lo mismo que
en [`FEAT-USR-041`](FEAT-USR-041-change-password.md): la petición no trae nada que identifique
la sesión desde la que llega. Aquí además importa menos, porque quien confirma suele estar en
otro dispositivo.

**El aviso a la dirección anterior lleva la nueva enmascarada** (`a****@ejemplo.com`). Quien
lo recibe necesita reconocerla o no reconocerla, y para eso no hace falta escribirla entera;
si ese aviso acaba en un buzón ajeno, tampoco hace falta regalar la dirección a la que están
intentando llevarse la cuenta.

Detalle de implementación que conviene conocer: **anular la solicitud anterior y crear la
nueva van en dos transacciones**. El índice único parcial solo admite una viva por cuenta, y
en una sola transacción Doctrine puede insertar antes de actualizar y chocar consigo mismo. El
hueco es inocuo: lo peor es quedarse sin ninguna solicitud viva, y pedirlo otra vez lo
arregla.

`S-23` —revertir desde el aviso— y `S-24` —limitar la frecuencia— siguen abiertas.
