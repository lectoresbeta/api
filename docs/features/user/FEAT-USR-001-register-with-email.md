---
id: FEAT-USR-001
title: Registro con email y contraseña
context: User
concept: Account
actors: [Guest]
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - _sources/use-cases.pdf#p3
  - _sources/credit-system.pdf#p1
  - figma:1800-13778 (1470:9482)
  - docs/ui/account-creation.md
endpoints: [POST /auth/register]
events: [UserRegistered]
depends_on: [FEAT-USR-024]
updated: 2026-09-21
---

# FEAT-USR-001 — Registro con email y contraseña

## Resumen

Una persona sin cuenta se registra indicando **email y contraseña**, y aceptando las
condiciones de uso y la política de privacidad. La cuenta se crea en estado
`PENDING_ACTIVATION` y se le envía un correo para activarla, pero el usuario pasa
directamente al onboarding sin esperar a ese correo.

Al crearse la cuenta se abonan **20 créditos de bienvenida**, cantidad pensada para que
pueda recibir un comentario de un relato pequeño sin haber aportado nada todavía. Si el
registro procede de una invitación, se conserva el vínculo con quien invitó para
recompensarle cuando la persona invitada participe (`FEAT-CRD-005`).

## Lo que cambió al ver el diseño

| Antes (según `use-cases.pdf`) | Ahora (según Figma `1470:9482`) |
|---|---|
| El registro pedía email, contraseña y nombre de usuario | **No hay campo de nombre de usuario.** Solo email y contraseña |
| Sin política de contraseña definida | Política visible y explícita, ver `RN-3` |
| Sin aceptación de términos | Casilla obligatoria de condiciones y privacidad (`FEAT-USR-024`) |
| Verificación de email sin decidir | Se envía correo de activación, **pero no bloquea el onboarding** |
| Google y Facebook | Google, Facebook y **LinkedIn** (`FEAT-USR-019`) |

La desaparición del nombre de usuario deja un cabo suelto: la pantalla siguiente saluda con
«Casi lo tienes, **beatrizalonso**!». Ese alias no lo ha introducido nadie. Ver `OB-1`.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Guest` | Registrarse | Sin sesión iniciada |
| `User` | Nada | Una sesión activa no se registra de nuevo |

## Precondiciones

- El email no pertenece a ninguna cuenta existente.

## Reglas de negocio

- `RN-1` El email es único en la plataforma y se normaliza a minúsculas.
- `RN-2` El registro **no pide nombre de usuario**. Si el sistema necesita un alias, se
  deriva o se pide más adelante: sin resolver (`OB-1`).
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
- `RN-10` Una cuenta nueva recibe 20 créditos. **El abono lo decide y lo ejecuta `Credits`**
  al consumir el evento correspondiente. `User` no conoce la cantidad. Si ese evento es
  `UserRegistered` o `AccountActivated` depende de `OB-3`.
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
9. `Credits` crea la cuenta de créditos y abona los 20 de bienvenida.
10. El usuario entra en el onboarding (`FEAT-USR-022`).

Los pasos 8 y 9 son **asíncronos**. El registro se completa sin esperarlos.

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
| `UserRegistered` | Tras persistir la cuenta | `userId`, `email`, `registeredAt`, `invitedBy?`, `authProvider: LOCAL`, `status: PENDING_ACTIVATION` |

**Consume**: ninguno.

## Efectos en créditos

El hecho publicado es `UserRegistered`. `Credits` lo interpreta creando la cuenta y abonando
los créditos de bienvenida (`FEAT-CRD-002`).

`User` **no dice cuántos créditos**. Si mañana la bienvenida pasa a 30, solo cambia `Credits`.

Pendiente de `OB-3`: si los créditos se abonan al registrarse o al activar la cuenta. Abonar
al activar encarece el registro masivo de cuentas falsas, que es justo lo que el crédito por
invitación (`FEAT-CRD-005`) incentiva a intentar.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `user` | Nuevo registro con `status = PENDING_ACTIVATION` |
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
- [ ] Se publica `UserRegistered` exactamente una vez por registro correcto.
- [ ] El payload de `UserRegistered` no contiene la contraseña ni su hash.
- [ ] Tras procesarse el evento, el usuario tiene una cuenta de créditos con saldo 20.
- [ ] Se envía el correo de activación.
- [ ] El usuario puede entrar al onboarding sin haber activado la cuenta.
- [ ] Un token de invitación válido queda consumido y asociado a la nueva cuenta.
- [ ] Un token de invitación inválido no impide el registro.
- [ ] El invitador **no** recibe créditos en el momento del registro.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **OB-1** | **¿De dónde sale el alias con el que saluda el onboarding, si el registro no pide nombre de usuario?** | **Bloqueante.** Define si existe `username`, cuándo se fija y cómo se garantiza su unicidad |
| **OB-3** | ¿Qué puede hacer una cuenta sin activar? ¿Recibe los créditos de bienvenida? | Define dónde se abona y cuánta superficie de abuso queda abierta |
| Q-1 | ¿El registro devuelve además una sesión iniciada? | El diseño sugiere que sí; depende de `S-1` |
| Q-2 | ¿Cómo se concilia no revelar emails registrados (`RN-14`) con una experiencia usable? | Compromiso entre seguridad y usabilidad |
| Q-6 | ¿Se piden las preferencias literarias en el registro? | **Resuelto:** no. Van en el onboarding (`FEAT-USR-023`) |
| Q-7 | ¿Hay aceptación de términos? | **Resuelto:** sí, casilla obligatoria (`FEAT-USR-024`) |
| Q-4 | ¿Cuál es la política de contraseñas? | **Resuelto:** ver `RN-3` |
| Q-3 | ¿Se requiere verificación del email? | **Resuelto en parte:** se envía activación, pero no bloquea el onboarding. Qué bloquea exactamente es `OB-3` |
| Q-5 | ¿Qué reglas sigue el nombre de usuario? | **Suspendido** hasta resolver `OB-1` |

## Estado

**Especificación:** `DRAFT`. El diseño ha resuelto la política de contraseña, la aceptación
de términos y el alcance del formulario. Para llegar a `APPROVED` faltan `OB-1` (el alias),
`OB-3` (qué permite una cuenta sin activar) y `S-1` (mecanismo de sesión).

**Implementación:** `TODO`.
