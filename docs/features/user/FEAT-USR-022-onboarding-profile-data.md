---
id: FEAT-USR-022
title: Onboarding paso 1 — nombre y fecha de nacimiento
context: User
concept: Onboarding
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P0
sources:
  - figma:1800-13778 (1470:9456, error en 1679:8998)
  - docs/ui/account-creation.md
endpoints: [GET /me/onboarding, PUT /me/onboarding/profile]
events: []
depends_on: [FEAT-USR-001]
updated: 2026-09-24
---

# FEAT-USR-022 — Onboarding paso 1: nombre y fecha de nacimiento

## Resumen

Primer paso del onboarding. Recoge dos datos con visibilidad **opuesta**:

| Campo | Visibilidad |
|---|---|
| **Nombre** | **Público.** Es el referente con el que se identifica a un usuario en toda la plataforma |
| **Fecha de nacimiento** | **Privada.** Solo el titular y el equipo de LectoresBeta |

El diseño muestra el tooltip *«Esta información solo será visible para ti y el equipo de
LectoresBeta»* en ambos campos. **Solo corresponde a la fecha de nacimiento**; en el campo
Nombre hay que retirarlo, porque dice justo lo contrario de lo que ocurre.

Al no existir nombre de usuario, el Nombre es el **único** identificador visible de una
persona: aparece en el perfil, el catálogo, el muro, los rankings, los comentarios y las
tarjetas de sugerencia de autores.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Rellenar su propio onboarding | Sesión iniciada. **No requiere cuenta activada** |

## Precondiciones

- Cuenta creada, en `PENDING_ACTIVATION` o `ACTIVE`.
- Onboarding no completado.

## Identidad y edad

**`N-2` resuelta.** Quien identifica de forma única a una persona es el **nombre de usuario**
([`FEAT-USR-033`](FEAT-USR-033-username-assignment.md)), que es único por construcción y es lo
que aparece en la URL del perfil.

El **nombre público** de este paso del onboarding es un texto libre y **no es único**: dos
personas pueden llamarse «Ana García» sin que eso cree ninguna ambigüedad, porque para
distinguirlas está el nombre de usuario.

Confirmado el 2026-09-24: **el nombre público no es único**, y no hace falta que lo sea.

**`OB-7` resuelta.** La **fecha de nacimiento es obligatoria** y de ella se deriva la edad, que
**filtra automáticamente el contenido** según lo que exija la ley: una cuenta que no alcance
la mayoría de edad no ve obras marcadas `ADULTS_ONLY`
([`FEAT-WRK-017`](../work/FEAT-WRK-017-content-rating.md)).

Ese filtrado **no es una preferencia**: no se puede desactivar desde los ajustes de contenido
([`FEAT-USR-043`](FEAT-USR-043-content-preferences.md) `RN-4`).

Queda por fijar la **edad mínima para registrarse** (`OB-15`), que no es lo mismo que la
mayoría de edad y varía por jurisdicción.

## Reglas de negocio

- `RN-1` El nombre es obligatorio.
- `RN-2` La fecha de nacimiento es obligatoria y se envía en formato `YYYY-MM-DD`
  (la interfaz la presenta como `XX/XX/XXXX`).
- `RN-3` La fecha debe ser una fecha real y pasada. No se aceptan fechas futuras ni
  imposibles.
- `RN-4` **El nombre es público.** Es el dato con el que se identifica a un usuario en toda
  la plataforma y se devuelve en perfiles, listados, búsquedas, comentarios y sugerencias.
- `RN-4b` **La fecha de nacimiento es privada.** No se devuelve en ninguna respuesta dirigida
  a terceros, ni en perfiles, ni en listados, ni en búsquedas, ni en sugerencias.
- `RN-5` El estado del onboarding se persiste al completar cada paso, de modo que el usuario
  pueda abandonarlo y retomarlo donde lo dejó.
- `RN-6` El paso 1 es obligatorio: no se puede avanzar sin completarlo.
- `RN-7` La validación del servidor es independiente de la del cliente. Un cliente
  manipulado no puede guardar una fecha inválida.
- `RN-8` El saludo usa el **nombre de usuario** del usuario (`FEAT-USR-033`), que por defecto
  es la parte del email anterior a la `@`. Es un dato almacenado y único, no un texto
  calculado.
- `RN-9` Este paso funciona con la cuenta en `PENDING_ACTIVATION`: es una de las excepciones
  al bloqueo de escritura (`FEAT-USR-025`, `RN-5`).
- `RN-10` El nombre es editable después del onboarding (`FEAT-USR-008`). Al ser público,
  cambiarlo cambia cómo se ve a esa persona en todo el histórico: comentarios antiguos,
  publicaciones y rankings pasan a mostrar el nombre nuevo.
- `RN-11` El nombre **no es el identificador técnico**. La identidad sigue siendo `UserId`
  (UUID): las rutas de la API y los enlaces de perfil usan el UUID, nunca el nombre.

## Estado del onboarding

| Estado | Significado |
|---|---|
| `PROFILE_PENDING` | Falta el paso 1 |
| `GENRES_PENDING` | Falta el paso 2 |
| `SUGGESTIONS_PENDING` | Falta el paso 3, que es opcional |
| `COMPLETED` | Terminado |

Se expone en `GET /me/onboarding` para que el frontend sepa en qué paso retomar.

## Flujo principal

1. El usuario llega al paso 1 tras registrarse.
2. La pantalla le saluda por su **nombre de usuario**, asignado al registrarse a partir del
   email (`FEAT-USR-033`).
3. Introduce nombre y fecha de nacimiento.
4. El sistema valida ambos campos.
5. Los guarda y avanza el estado a `GENRES_PENDING`.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Fecha con formato inválido | Se rechaza. El diseño muestra «Formato incorrecto de fecha» | `422` con `code: INVALID_DATE_FORMAT` |
| Fecha futura o inexistente (30/02) | Se rechaza | `422` |
| Nombre vacío | Se rechaza | `422` |
| Nombre con caracteres o longitud no admitidos | Se rechaza | `422`. Reglas por definir (`N-1`) |
| Edad por debajo del mínimo legal | **Sin definir** (`OB-7`) | Pendiente |
| Onboarding ya completado | Se rechaza; para cambiar los datos se usa `FEAT-USR-008` | `409` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Consultar estado del onboarding | `GET /me/onboarding` | `getOnboardingState` |
| Guardar datos del paso 1 | `PUT /me/onboarding/profile` | `submitOnboardingProfile` |

`PUT` y no `POST`: el paso es idempotente, reenviar los mismos datos no cambia el resultado.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `user` | `name`, `birth_date`, `onboarding_status` |

`birth_date` es dato personal: no se registra en logs y no se expone en la API pública.

`name` se consulta en cada listado que muestre personas, así que necesita índice si se busca
por él (`FEAT-USR-017`).

## Diseño (Figma)

`1470:9456` (estado normal), `1679:8998` (error de fecha y tooltip).

Ver [`../../ui/account-creation.md`](../../ui/account-creation.md).

## Criterios de aceptación

- [ ] Guardar nombre y fecha válidos avanza el estado a `GENRES_PENDING`.
- [ ] Una fecha con formato incorrecto devuelve `422`.
- [ ] Una fecha futura devuelve `422`.
- [ ] Una fecha inexistente como `2000-02-30` devuelve `422`.
- [ ] Un nombre vacío devuelve `422`.
- [ ] `GET /users/{userId}` de otro usuario **sí** devuelve `name`.
- [ ] `GET /users/{userId}` de otro usuario **no** devuelve `birthDate` ni el email.
- [ ] Ningún endpoint accesible por terceros expone la fecha de nacimiento.
- [ ] Las rutas y los enlaces de perfil usan el `UserId`, no el nombre.
- [ ] El usuario puede completar este paso sin haber activado su cuenta.
- [ ] Tras cerrar sesión y volver, el onboarding se retoma en el paso 2.
- [ ] Un cliente que envíe una fecha inválida saltándose la validación de cliente recibe `422`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| OB-2 | ¿Qué nombre ve el resto de la plataforma? | **Resuelto:** el «Nombre» del paso 1, que es público. El tooltip de privacidad solo corresponde a la fecha de nacimiento |
| OB-1 | ¿De dónde sale el alias del saludo? | **Resuelto:** es el nombre de usuario, asignado desde el email (`FEAT-USR-033`) |
| N-1 | ¿Qué reglas sigue el nombre: longitud, caracteres admitidos, nombres reservados? | Validación |
| **N-2** | **¿El nombre debe ser único?** | Sin unicidad, dos personas homónimas son indistinguibles en comentarios y rankings. Con unicidad, se rechazan nombres reales legítimos. **Recomendación: no exigir unicidad**, y desambiguar en la interfaz con avatar y enlace al perfil |
| N-3 | ¿Se puede cambiar el nombre libremente y con qué frecuencia? | Cambiarlo reescribe cómo se ve a esa persona en todo el histórico (`RN-10`) |
| **OB-7** | ¿Hay edad mínima? ¿Se rechaza el registro por debajo de ella? | **Legal.** Si se pide la fecha de nacimiento, debe haber un motivo declarado |
| OB-10 | ¿Se persiste el paso a paso o el onboarding completo al final? | Asumido paso a paso en `RN-5`; confirmar |

## Estado

**Especificación:** `APPROVED` (2026-09-24). `N-2` y `OB-7` resueltas: la unicidad la aporta el nombre de
usuario y la fecha de nacimiento es obligatoria y filtra contenido. Queda `OB-15`, la edad
mínima de registro, que no impide implementar el paso.

**Implementación:** `DONE`.

`GET /api/v1/me/onboarding` y `PUT /api/v1/me/onboarding/profile`, cubiertos por
`tests/Functional/User/OnboardingTest.php`.

El detalle que merece recordarse: la fecha se valida **ida y vuelta**, no con un simple
`DateTimeImmutable`. Ese conversor acepta `2000-02-30` y lo convierte en el 1 de marzo, con lo
que se guardaría una fecha que nadie escribió y esa persona sería un día mayor de lo que dijo,
sin que nada lo delatase. Hay test.

Los dos pasos son además la **primera comprobación de extremo a extremo** de
[`FEAT-USR-025`](FEAT-USR-025-block-writes-until-activation.md): son escrituras que una cuenta
en `PENDING_ACTIVATION` sí puede hacer, y hasta ahora esa excepción existía en una lista y nada
la ejercía.

**Lo que este paso no incluye:** la edad mínima para registrarse (`OB-15`) sigue sin fijarse,
así que no se comprueba. La fecha solo tiene que ser real y pasada.
