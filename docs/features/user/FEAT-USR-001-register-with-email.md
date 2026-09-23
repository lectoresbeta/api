---
id: FEAT-USR-001
title: Registro con email y contraseña
context: User
concept: Account
actors: [Guest]
spec_status: APPROVED
impl_status: TODO
priority: P0
sources:
  - _sources/use-cases.pdf#p3
  - _sources/credit-system.pdf#p1
  - figma:1800-13778 (1470:9482)
  - docs/ui/account-creation.md
endpoints: [POST /auth/register]
events: [UserRegistered]
depends_on: [FEAT-USR-024, FEAT-USR-025]
updated: 2026-09-24
---

# FEAT-USR-001 — Registro con email y contraseña

## Resumen

Una persona sin cuenta se registra indicando **email y contraseña**, y aceptando las
condiciones de uso y la política de privacidad. La cuenta se crea en estado
`PENDING_ACTIVATION` y se le envía un correo para activarla, pero el usuario pasa
directamente al onboarding sin esperar a ese correo.

**No se abonan créditos en el registro.** Los 20 créditos de bienvenida se abonan al activar
la cuenta (`FEAT-USR-020`), y hasta entonces la cuenta no puede ejecutar ninguna operación de
escritura (`FEAT-USR-025`). Si el registro procede de una invitación, se conserva el vínculo
con quien invitó para recompensarle cuando la persona invitada participe (`FEAT-CRD-005`).

## Lo que cambió al ver el diseño

| Antes (según `use-cases.pdf`) | Ahora (según Figma `1470:9482`) |
|---|---|
| El registro pedía email, contraseña y nombre de usuario | El formulario solo pide email y contraseña. **El nombre de usuario existe, pero se asigna solo** desde el email |
| Sin política de contraseña definida | Política visible y explícita, ver `RN-3` |
| Sin aceptación de términos | Casilla obligatoria de condiciones y privacidad (`FEAT-USR-024`) |
| Verificación de email sin decidir | Se envía correo de activación, **pero no bloquea el onboarding** |
| Google y Facebook | El diseño muestra Google, Facebook y LinkedIn, pero **en esta fase solo se implementa Google** |

El saludo del onboarding —«Casi lo tienes, **beatrizalonso**!»— muestra el **nombre de
usuario** recién asignado, que por defecto es la parte del email anterior a la `@`.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Guest` | Registrarse | Sin sesión iniciada |
| `User` | Nada | Una sesión activa no se registra de nuevo |

## Precondiciones

- El email no pertenece a ninguna cuenta existente.

## Reglas de negocio

- `RN-1` El email es único en la plataforma y se normaliza a minúsculas.
- `RN-2` El registro **no pide nombre de usuario, pero sí lo asigna**: se genera a partir de
  la parte del email anterior a la `@`, normalizada, añadiendo `_1`, `_2`… si ya está
  ocupado. Es único y el usuario podrá cambiarlo después (`FEAT-USR-033`,
  [`decision:0005`](../../decisions/0005-username-with-temporary-aliases.md)).
- `RN-3` La contraseña debe cumplir, tal como anuncia el formulario:
  - al menos 8 caracteres;
  - al menos una mayúscula, un número y un carácter especial (`!@#$%^&*`).
- `RN-4` La política se valida **en el servidor**. Los indicadores en vivo del formulario son
  una ayuda visual, no una garantía.
- `RN-5` La contraseña se almacena cifrada mediante el mecanismo de hashing configurado.
  Nunca se guarda ni se registra en claro.
- `RN-6` No hay campo de confirmación de contraseña: el formulario ofrece «Mostrar» para
  revisarla.
- `RN-7` Es obligatorio aceptar condiciones de uso y política de privacidad (`FEAT-USR-024`).
- `RN-8` La cuenta se crea en `PENDING_ACTIVATION` y se emite el correo de activación
  (`FEAT-NOT-008`).
- `RN-9` El usuario accede al onboarding inmediatamente, sin esperar a activar la cuenta.
- `RN-10` **El registro no abona créditos.** Los 20 créditos de bienvenida se abonan cuando
  la cuenta se activa: `Credits` los aplica al consumir `AccountActivated`, no
  `UserRegistered`. `User` no conoce la cantidad. Ver [`decision:0003`](../../decisions/0003-write-operations-require-activated-account.md).
- `RN-15` Mientras la cuenta esté en `PENDING_ACTIVATION`, todas las operaciones de escritura
  están bloqueadas (`FEAT-USR-025`).
- `RN-11` Si el registro llega con un token de invitación válido, se asocia el invitador a la
  cuenta creada. El crédito por invitación **no se otorga aquí**: se otorga cuando la persona
  invitada deja su primer comentario.
- `RN-12` Un token de invitación se consume una sola vez.
- `RN-13` Un token de invitación inválido o ya usado **no impide el registro**: se ignora.
- `RN-14` La respuesta de error no revela si un email concreto ya está registrado.

`RN-14` evita que el endpoint sirva para enumerar qué personas usan la plataforma. Ver `Q-2`
para cómo conciliarlo con una experiencia usable.

## Flujo principal

1. La persona introduce email y contraseña, y acepta condiciones y privacidad.
2. El sistema valida el formato del email y la política de contraseña.
3. Comprueba que el email está libre.
4. Cifra la contraseña.
5. Crea la cuenta en `PENDING_ACTIVATION` y registra la aceptación legal con su versión.
6. Si hay token de invitación válido, lo consume y registra el vínculo con el invitador.
7. Publica `UserRegistered`.
8. `Notification` envía el correo de activación (`FEAT-NOT-008`).
9. `Credits` crea la cuenta de créditos **con saldo cero**.
10. El usuario entra en el onboarding (`FEAT-USR-022`).

Los pasos 8 y 9 son **asíncronos**. El registro se completa sin esperarlos.

Los créditos de bienvenida llegan más tarde, al activar la cuenta.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Email ya registrado | Se rechaza sin revelar la causa exacta (`RN-14`) | `409` o `422` genérico — ver `Q-2` |
| Email con formato inválido | Se rechaza | `422` |
| Contraseña que incumple la política | Se rechaza indicando los requisitos incumplidos | `422` con `code: WEAK_PASSWORD` |
| Sin aceptar condiciones | Se rechaza | `422` con `code: TERMS_NOT_ACCEPTED` |
| Token de invitación inválido o usado | Se ignora, el registro continúa (`RN-13`) | `201` |
| Petición con sesión activa | Se rechaza | `409` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Registrar cuenta | `POST /auth/register` | `registerUser` |

Documento: [`../../api/endpoints/user.md`](../../api/endpoints/user.md).
Esquemas: `openapi/paths/auth.yaml`.

Qué devuelve el registro —solo la cuenta creada, o también una sesión iniciada— depende de
`S-1`. Dado que el usuario pasa directamente al onboarding, **lo más coherente con el diseño
es que el registro devuelva ya una sesión**: de lo contrario habría que pedirle iniciar
sesión entre el registro y el paso 1, que no es lo que muestran las pantallas.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `UserRegistered` | Tras persistir la cuenta | `userId`, `email`, `username`, `registeredAt`, `invitedBy?`, `authProvider: LOCAL`, `status: PENDING_ACTIVATION` |

**Consume**: ninguno.

## Efectos en créditos

El hecho publicado es `UserRegistered`. `Credits` lo interpreta **creando la cuenta de
créditos con saldo cero**. No abona nada.

El abono de los 20 créditos de bienvenida se produce al consumir `AccountActivated`
(`FEAT-CRD-002`). Es una barrera deliberada contra el registro masivo de cuentas falsas, que
es justo lo que el crédito por invitación (`FEAT-CRD-005`) incentiva a intentar.

`User` **no dice cuántos créditos**. Si mañana la bienvenida pasa a 30, solo cambia `Credits`.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `user` | Nuevo registro con `status = PENDING_ACTIVATION` y `username` asignado |
| `legal_acceptance` | Aceptación de condiciones y privacidad, con versión y fecha |
| `account_activation_token` | Token de activación |
| `platform_invitation` | Marcado como consumido, si aplica |

Índice único sobre `email` normalizado.

## Diseño (Figma)

`1470:9482` — «Crea una cuenta».

Ver [`../../ui/account-creation.md`](../../ui/account-creation.md).

## Criterios de aceptación

- [ ] Un registro con email y contraseña válidos crea la cuenta y devuelve `201`.
- [ ] La cuenta se crea en estado `PENDING_ACTIVATION`.
- [ ] Una contraseña de 7 caracteres se rechaza con `422`.
- [ ] Una contraseña sin mayúscula, sin número o sin carácter especial se rechaza con `422`.
- [ ] La validación de contraseña se aplica aunque el cliente no la haya comprobado.
- [ ] Un registro sin aceptar las condiciones se rechaza con `422`.
- [ ] La contraseña nunca se almacena ni se registra en claro.
- [ ] Un email ya registrado no permite crear una segunda cuenta.
- [ ] La respuesta de error no permite averiguar si un email concreto está registrado.
- [ ] El email se normaliza: `Usuario@Ejemplo.com` y `usuario@ejemplo.com` son la misma cuenta.
- [ ] Se asigna un nombre de usuario único derivado del email (`FEAT-USR-033`).
- [ ] Dos altas con el mismo email local producen nombres de usuario distintos.
- [ ] Se publica `UserRegistered` exactamente una vez por registro correcto.
- [ ] El payload de `UserRegistered` no contiene la contraseña ni su hash.
- [ ] Tras procesarse el evento, el usuario tiene una cuenta de créditos con **saldo 0**.
- [ ] El registro no abona en ningún caso los créditos de bienvenida.
- [ ] La cuenta recién creada no puede ejecutar operaciones de escritura.
- [ ] Se envía el correo de activación.
- [ ] El usuario puede entrar al onboarding sin haber activado la cuenta.
- [ ] Un token de invitación válido queda consumido y asociado a la nueva cuenta.
- [ ] Un token de invitación inválido no impide el registro.
- [ ] El invitador **no** recibe créditos en el momento del registro.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| Q-1 | ¿El registro devuelve además una sesión iniciada? | El diseño sugiere que sí; depende de `S-1` |
| OB-2 | ¿Qué nombre se muestra públicamente? | **Resuelto:** el «Nombre» que se pide en el paso 1 del onboarding (`FEAT-USR-022`). Es público y es el referente para identificar a un usuario |
| OB-1 | ¿De dónde sale el alias del saludo? | **Resuelto:** de la parte del email anterior a la `@`. Solo para presentación |
| OB-3 | ¿Qué puede hacer una cuenta sin activar? ¿Recibe créditos? | **Resuelto:** nada de escritura, y los créditos llegan al activar. Ver `decision:0003` |
| Q-5 | ¿Qué reglas sigue el nombre de usuario? | **Resuelto:** existe y se especifica en `FEAT-USR-033` |
| Q-2 | ¿Cómo se concilia no revelar emails registrados (`RN-14`) con una experiencia usable? | Compromiso entre seguridad y usabilidad |
| Q-6 | ¿Se piden las preferencias literarias en el registro? | **Resuelto:** no. Van en el onboarding (`FEAT-USR-023`) |
| Q-7 | ¿Hay aceptación de términos? | **Resuelto:** sí, casilla obligatoria (`FEAT-USR-024`) |
| Q-4 | ¿Cuál es la política de contraseñas? | **Resuelto:** ver `RN-3` |
| Q-3 | ¿Se requiere verificación del email? | **Resuelto:** sí. No bloquea el onboarding, pero sí toda operación de escritura y el abono de créditos |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `TODO`.
