---
id: FEAT-USR-007
title: Recuperar la contraseña
context: User
concept: Account
actors: [Guest]
spec_status: APPROVED
impl_status: DONE
priority: P0
sources:
  - docs/ui/account-creation.md
  - conversation:2026-09-25
endpoints:
  - POST /auth/password/forgotten
  - POST /auth/password/reset
events: [PasswordResetRequested, PasswordChanged]
depends_on: [FEAT-USR-001, FEAT-USR-020]
updated: 2026-09-25
---

# FEAT-USR-007 — Recuperar la contraseña

## Resumen

**Hoy quien olvida su contraseña se queda fuera para siempre.** No hay ningún camino de
vuelta: el correo es la identidad de la cuenta, y sin poder demostrar que se controla ese
buzón no hay forma de recuperar nada.

Son dos operaciones, y conviene no confundirlas: **pedir** el enlace, que no exige nada, y
**usarlo**, que fija la contraseña nueva.

Es la pareja de [`FEAT-USR-041`](FEAT-USR-041-change-password.md). Aquella cambia la
contraseña **desde dentro**, demostrando quién eres con la contraseña actual; esta la cambia
**desde fuera**, demostrándolo con el buzón. Las dos acaban en el mismo sitio y aplican las
mismas reglas.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Guest` | Pedir el enlace | Solo con el correo. Sin sesión |
| `Guest` | Fijar la contraseña nueva | Con un enlace vigente y sin usar |

**Ninguna de las dos exige sesión, y no puede exigirla**: quien no recuerda su contraseña no
puede iniciarla. Es la misma razón por la que el reenvío de activación
([`FEAT-USR-021`](FEAT-USR-021-resend-activation-email.md)) tampoco la exige.

## Pedir el enlace no dice nada

- `RN-1` La respuesta es **siempre la misma**, exista o no esa cuenta. Un «ese correo no está
  registrado» convertiría el formulario en un comprobador de quién tiene cuenta.
- `RN-2` Un correo **mal formado** responde igual. Un `422` ahí también sería una diferencia
  observable entre direcciones.
- `RN-3` Hay **limitación de frecuencia por dirección y por origen**, con la misma forma que
  `FEAT-USR-021`: un endpoint público que dispara correo con el dominio de la plataforma en el
  remitente es un amplificador de spam.
- `RN-4` Los contadores **se consumen en cada petición**, exista o no la cuenta. Si solo
  contaran los envíos de verdad, agotar el límite diría que esa dirección está registrada, y
  `RN-1` se caería por la puerta de atrás.

## El enlace

- `RN-5` El token se guarda **solo como hash**. Quien pueda leer esa tabla no puede entrar en
  ninguna cuenta.
- `RN-6` **Vive una hora**, mucho menos que el de activación, que vive dos días. No es
  simetría: un enlace de activación abre una cuenta recién creada y vacía, y uno de
  restablecimiento **se queda con una cuenta que ya tiene historial, créditos y obra
  inédita**. La ventana de un enlace filtrado —en un buzón compartido, en un historial de
  navegación, en un reenvío— debe ser la menor que siga siendo usable.
- `RN-7` Es de **un solo uso**. Se consume al fijar la contraseña.
- `RN-8` Cada petición **invalida la anterior**: solo vale el enlace del último correo. Dos
  vivos a la vez doblarían la ventana de `RN-6`.

## Usar el enlace

- `RN-9` La contraseña nueva cumple las mismas reglas que en el registro
  ([`FEAT-USR-001`](FEAT-USR-001-register-with-email.md) `RN-3`), y los requisitos incumplidos
  vienen en el error, igual que en `FEAT-USR-041` `RN-2`.
- `RN-10` Al fijarla **se invalidan los tokens de refresco de todas las sesiones**, incluida
  la de quien lo hace si la tuviera. Quien recupera su contraseña muchas veces lo hace porque
  sospecha que alguien más entró; dejar viva esa sesión sería dejar dentro justo a quien se
  quiere echar.
- `RN-11` Se **avisa por correo** de que la contraseña ha cambiado. Es un aviso de seguridad y
  **no se puede desactivar** ([`FEAT-USR-039`](FEAT-USR-039-notification-preferences.md)
  `RN-3`). Es la única forma de que alguien se entere de que le han cambiado la contraseña.
- `RN-12` Un token inválido y uno ya usado responden **lo mismo**. Solo la caducidad se
  distingue, porque es la única que le dice algo útil a quien está delante: pide otro.
- `RN-13` Ni la contraseña ni su hash aparecen en logs, ni en eventos, ni en la respuesta.

## Una cuenta sin activar que recupera su contraseña queda activada

`RN-14`. **Decidido aquí**, y es la regla menos evidente de la ficha.

Activar la cuenta sirve para demostrar que ese buzón existe y es tuyo. Un enlace de
restablecimiento se manda a ese mismo buzón, y usarlo demuestra exactamente lo mismo. Exigir
después el otro enlace sería pedir dos veces la misma prueba.

Sin esta regla queda un callejón incómodo: alguien recupera la contraseña, entra, y sigue sin
poder escribir sin entender por qué.

No vale al revés: activar la cuenta **no** cambia ninguna contraseña.

## Una cuenta de Google también puede usarlo

`RN-15`. Quien se dio de alta con Google no tiene contraseña, y este camino se la
**establece** — igual que `FEAT-USR-041` con el campo «actual» en blanco.

No abre ningún hueco nuevo: quien controla el buzón ya podía hacerlo por allí. Y no desvincula
Google (`FEAT-USR-041` `RN-7`): quedan dos formas de entrar.

## Flujo principal

```text
1. «He olvidado mi contraseña» ──▶ POST /auth/password/forgotten
2. Siempre 202, diga lo que diga la base de datos
3. Si procede: invalida el token anterior, emite uno nuevo
                       │
                       ▼  cola
4. Notification pide el enlace por el contrato publicado y manda el correo
5. La persona abre el enlace ──▶ POST /auth/password/reset
6. Contraseña fijada · sesiones cerradas · cuenta activada si no lo estaba
7. Correo de aviso: «tu contraseña ha cambiado»
```

El token **no viaja en el evento**: es una credencial viva, y una cola que persiste, reintenta
y aparca mensajes no es sitio para ella
([`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md)). Se pide por
el contrato publicado en el momento de enviar, exactamente como el de activación.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Correo no registrado | No se envía nada | `202`, indistinguible |
| Correo mal formado | No se envía nada | `202`, indistinguible |
| Cuenta eliminada | No se envía nada | `202`, indistinguible |
| Pedirlo antes del intervalo mínimo | Se rechaza | `429` con `code: PASSWORD_RESET_TOO_SOON` y `Retry-After` |
| Superado el máximo del periodo | Se rechaza | `429` con `code: PASSWORD_RESET_LIMIT_REACHED` |
| Token inexistente, ya usado o invalidado | Se rechaza | `404` con `code: INVALID_PASSWORD_RESET_TOKEN` |
| Token caducado | Se rechaza | `410` con `code: PASSWORD_RESET_TOKEN_EXPIRED` |
| Contraseña nueva débil | Se rechaza | `422` con los requisitos incumplidos |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Pedir el enlace | `POST /auth/password/forgotten` | `requestPasswordReset` |
| Fijar la contraseña | `POST /auth/password/reset` | `resetPassword` |

`POST` en las dos: la primera dispara un envío y la segunda consume un token de un solo uso.
Ninguna es idempotente, así que ninguna es `PUT`.

**Fijar la contraseña no devuelve una sesión.** Sería cómodo, pero convertiría un enlace de
correo en un inicio de sesión completo: quien lo interceptase entraría sin escribir nada.
Después hay que iniciar sesión, que es donde están los límites y los avisos del login.

## Eventos

**Publica**

| Evento | Cuándo | Payload |
|---|---|---|
| `PasswordResetRequested` | Se pide el enlace, y procede mandarlo | `userId`, `requestedAt`. **Ni el correo ni el token** |
| `PasswordChanged` | Se fija la contraseña | `userId`, `changedAt`. **Nunca la contraseña ni su hash** |

`PasswordChanged` es el mismo hecho que publica `FEAT-USR-041`: para quien lo consume, que la
contraseña se cambiara desde dentro o desde fuera da igual.

## Modelo de datos afectado

Tabla nueva `password_reset_token`, con la misma forma que `account_activation_token`: hash,
creación, caducidad, uso e invalidación. Tabla aparte y no una columna más en aquella, porque
son dos credenciales con vidas distintas (`RN-6`) y mezclarlas haría que invalidar una tocase
a la otra.

## Criterios de aceptación

- [x] Pedirlo para un correo sin registrar responde exactamente igual que para uno registrado.
- [x] El enlace fija la contraseña nueva, y con ella se puede iniciar sesión.
- [x] La contraseña anterior deja de servir.
- [x] El enlace no vale dos veces.
- [x] Pedirlo otra vez invalida el enlace anterior.
- [x] Un enlace caducado se distingue de uno inválido; uno usado, no.
- [x] Fijar la contraseña cierra las demás sesiones.
- [x] Se envía aviso por correo del cambio.
- [x] Una contraseña débil se rechaza con los requisitos incumplidos.
- [x] Una cuenta sin activar queda activada al completar el restablecimiento.
- [x] Hay limitación de frecuencia, con los dos códigos distinguibles.
- [x] Ni la contraseña ni su hash aparecen en la respuesta, en un evento o en un log.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| P-20 | ¿Una hora es el plazo correcto? | `RN-6` razona el orden de magnitud, no la cifra |
| P-21 | ¿Se avisa también al pedir el enlace, y no solo al completarlo? | Avisaría de un intento que no prosperó. Hoy solo se avisa del cambio (`RN-11`) |
| P-22 | ¿Se comprueba la contraseña nueva contra listas filtradas? | Es `S-31` de `FEAT-USR-041`, y la respuesta debería ser la misma en las dos |

## Estado

**Especificación:** `APPROVED` (2026-09-25).

**Implementación:** `DONE` (2026-09-25).

Dos cosas que la ficha no anticipaba y que la implementación ha tenido que resolver:

**Una contraseña débil no consume el enlace.** La política se comprueba antes de tocar nada,
así que quien se equivoca escribiendo la nueva vuelve a intentarlo con el mismo correo
delante en lugar de tener que pedir otro. Parece un detalle y es la diferencia entre un
formulario usable y uno que castiga una errata.

**Restablecer publica también `AccountActivated`** cuando la cuenta no lo estaba (`RN-14`).
`Credits` abona la bienvenida al recibir ese hecho, y no tiene por qué saber por qué camino
llegó la activación — para él es la misma cosa.

`PasswordChanged` lleva `viaReset`, que no estaba en la ficha. No decide si se avisa —siempre
se avisa— sino qué dice el aviso: «has cambiado tu contraseña» y «tu contraseña se ha
restablecido» no son la misma frase para quien no hizo ninguna de las dos cosas.

Y una puerta nueva en `User`: el contrato `MailingAddresses`, que dice a qué dirección
escribir cuando el aviso **no lleva enlace**. Los que sí lo llevan reciben la dirección de
paso al emitir la credencial. Es la puerta más delicada de ese contexto —reparte direcciones
de correo— y se abre porque el correo operativo *es* la defensa de la cuenta: sin él, a quien
le roban una no se entera.

Cifras en `config/packages/framework.yaml` (`P-20`): una hora de vida, 60 segundos de
intervalo, 3 enlaces al día por dirección y 20 a la hora por origen. El tope diario es más
bajo que el del reenvío de activación a propósito.
