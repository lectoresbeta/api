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
endpoints: [POST /auth/register]
events: [UserRegistered]
depends_on: []
updated: 2026-09-21
---

# FEAT-USR-001 — Registro con email y contraseña

## Resumen

Una persona sin cuenta se registra en Lectores Beta indicando su email, un nombre de usuario
y una contraseña. Al completarse el registro recibe automáticamente **20 créditos de
bienvenida**, cantidad pensada para que pueda recibir un comentario de un relato pequeño sin
haber aportado nada todavía.

Si el registro procede de una invitación, se conserva el vínculo con quien invitó para poder
recompensarle cuando la persona invitada participe (`FEAT-CRD-005`).

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Guest` | Registrarse | Sin sesión iniciada |
| `User` | Nada | Una sesión activa no se registra de nuevo |

## Precondiciones

- El email no pertenece a ninguna cuenta existente.
- El nombre de usuario está libre.

## Reglas de negocio

- `RN-1` El email es único en la plataforma y se normaliza a minúsculas.
- `RN-2` El nombre de usuario es único.
- `RN-3` La contraseña se almacena cifrada mediante el mecanismo de hashing configurado.
  Nunca se guarda ni se registra en claro.
- `RN-4` Una cuenta recién creada recibe 20 créditos. **El abono lo decide y lo ejecuta
  `Credits`** al consumir `UserRegistered`. `User` no conoce la cantidad.
- `RN-5` Si el registro llega con un token de invitación válido, se asocia el invitador a la
  cuenta creada. El crédito por invitación **no se otorga aquí**: se otorga cuando la persona
  invitada deja su primer comentario.
- `RN-6` Un token de invitación se consume una sola vez.
- `RN-7` Un token de invitación inválido o ya usado **no impide el registro**: se ignora.
- `RN-8` La respuesta de error no revela si un email concreto ya está registrado.

`RN-8` evita que el endpoint de registro sirva para enumerar qué personas usan la plataforma.
Ver `Q-2` para cómo se concilia con una buena experiencia de usuario.

## Flujo principal

1. La persona envía email, nombre de usuario y contraseña, y opcionalmente un token de
   invitación.
2. El sistema valida el formato de los datos.
3. El sistema comprueba que el email y el nombre de usuario están libres.
4. El sistema cifra la contraseña.
5. El sistema crea la cuenta.
6. Si hay token de invitación válido, lo consume y registra el vínculo con el invitador.
7. El sistema publica `UserRegistered`.
8. `Credits` recibe el evento, crea la cuenta de créditos y abona 20 créditos.
9. `Notification` recibe el evento y envía la bienvenida.

Los pasos 8 y 9 son **asíncronos**. El registro se completa sin esperarlos.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Email ya registrado | Se rechaza sin revelar la causa exacta (`RN-8`) | `409` o `422` genérico — ver `Q-2` |
| Nombre de usuario ocupado | Se rechaza indicando el campo | `422` |
| Email con formato inválido | Se rechaza | `422` |
| Contraseña que no cumple la política | Se rechaza indicando los requisitos | `422` |
| Token de invitación inválido o usado | Se ignora, el registro continúa (`RN-7`) | `201` |
| Petición con sesión activa | Se rechaza | `409` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Registrar cuenta | `POST /auth/register` | `registerUser` |

Documento: [`../../api/endpoints/user.md`](../../api/endpoints/user.md).
Esquemas: `openapi/paths/auth.yaml`.

Qué devuelve el registro —solo la cuenta creada, o también una sesión iniciada— depende de
`S-1`. Ver `Q-1`.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `UserRegistered` | Tras persistir la cuenta | `userId`, `email`, `username`, `registeredAt`, `invitedBy` (opcional), `authProvider: LOCAL` |

**Consume**: ninguno.

## Efectos en créditos

El hecho publicado es `UserRegistered`. `Credits` lo interpreta creando la cuenta y abonando
los créditos de bienvenida (`FEAT-CRD-002`).

`User` **no dice cuántos créditos**. Si mañana la bienvenida pasa a 30, solo cambia `Credits`.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `user` | Nuevo registro |
| `platform_invitation` | Marcado como consumido, si aplica |

Índices: índice único sobre `email` normalizado e índice único sobre `username`.

## Diseño (Figma)

Pendiente. Aspectos que el diseño debe resolver: qué campos se piden en el formulario, si
hay aceptación de términos, y cómo se comunica el saldo inicial de créditos.

## Criterios de aceptación

- [ ] Un registro con datos válidos crea la cuenta y devuelve `201`.
- [ ] La contraseña nunca se almacena ni se registra en claro.
- [ ] Un email ya registrado no permite crear una segunda cuenta.
- [ ] La respuesta de error no permite averiguar si un email concreto está registrado.
- [ ] Un nombre de usuario ocupado se rechaza con `422`.
- [ ] Se publica `UserRegistered` exactamente una vez por registro correcto.
- [ ] El payload de `UserRegistered` no contiene la contraseña ni su hash.
- [ ] Tras procesarse el evento, el usuario tiene una cuenta de créditos con saldo 20.
- [ ] Un token de invitación válido queda consumido y asociado a la nueva cuenta.
- [ ] Un token de invitación inválido no impide el registro.
- [ ] El invitador **no** recibe créditos en el momento del registro.
- [ ] El email se normaliza: `Usuario@Ejemplo.com` y `usuario@ejemplo.com` son la misma cuenta.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| Q-1 | ¿El registro devuelve además una sesión iniciada? | Depende de `S-1` |
| Q-2 | ¿Cómo se concilia no revelar emails registrados (`RN-8`) con una experiencia usable? | Compromiso entre seguridad y usabilidad |
| Q-3 | ¿Se requiere verificación del email antes de poder actuar? (`S-3`, `U-1`) | Fraude con invitaciones y créditos |
| Q-4 | ¿Cuál es la política de contraseñas? | Validación |
| Q-5 | ¿Qué reglas sigue el nombre de usuario (longitud, caracteres, reservados)? | Validación |
| Q-6 | ¿Se piden las preferencias literarias durante el registro o después? | Depende del diseño |
| Q-7 | ¿Hay aceptación de términos y política de privacidad? | Requisito legal probable |

## Estado

**Especificación:** `DRAFT`. Para llegar a `APPROVED` hacen falta la decisión sobre sesiones
(`S-1`), la política de verificación de email (`Q-3`) y la página de Figma del registro.

**Implementación:** `TODO`.
