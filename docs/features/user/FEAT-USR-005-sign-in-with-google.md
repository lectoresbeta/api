---
id: FEAT-USR-005
title: Login con cuenta de Google
context: User
concept: Authentication
actors: [Guest]
spec_status: APPROVED
impl_status: DONE
priority: P0
sources:
  - _sources/use-cases.pdf#p3
  - conversation:2026-09-25
endpoints: [GET /auth/oauth/{provider}, POST /auth/oauth/{provider}/callback]
events: []
depends_on: [FEAT-USR-002, FEAT-USR-004]
updated: 2026-09-25
---

# FEAT-USR-005 — Login con cuenta de Google

## Resumen

Entrar en una cuenta que ya existe usando Google, sin contraseña.

**Comparte endpoint con el alta** (`FEAT-USR-002`), y no por ahorro: para el servidor son la
misma operación hasta el último momento. Llega un código, se canjea por una identidad, y solo
entonces se sabe si esa identidad ya tiene cuenta. Separarlos en dos endpoints obligaría al
cliente a saber de antemano algo que solo se puede averiguar después de hablar con Google.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `Guest` | Iniciar sesión con su cuenta de Google | Que esa identidad ya esté enlazada a una cuenta |

## Qué distingue esto del alta

Una sola cosa, y está en el servidor: **si la identidad que devuelve Google ya está
enlazada**.

| | Ya enlazada | No enlazada |
|---|---|---|
| Qué es | Un login | Un alta, o un enlace |
| Aceptación legal | **No se pide** (`RN-1`) | Obligatoria |
| Respuesta | `200` | `201`, o `409` si el correo ya tiene cuenta y Google no lo da por verificado |
| `isNewAccount` | `false` | `true` |

`RN-1` es lo que hace que esto merezca ficha propia: **la aceptación se pide una vez, al crear
la cuenta**. Volver a pedirla en cada entrada convertiría un trámite legal en un peaje, y un
peaje que se pulsa sin leer no demuestra nada.

## Reglas de negocio

- `RN-1` Iniciar sesión **no exige volver a aceptar** las condiciones ni la política de
  privacidad.
- `RN-2` La cuenta se busca por **proveedor e identificador externo**, nunca por el correo. El
  correo de una cuenta de Google puede cambiar y el `sub` que Google entrega no; buscar por
  correo perdería la cuenta de alguien el día que se cambia de dirección.
- `RN-3` Una cuenta que **no puede autenticar** —expulsada, suspendida o eliminada— tampoco
  entra por aquí. Entrar por otra puerta no es una forma de saltarse una sanción.
- `RN-4` Quien se dio de alta con Google y **no tiene contraseña** no puede entrar por el
  formulario de contraseña, y el error es **indistinguible** del de una contraseña incorrecta
  (`FEAT-USR-004` `RN-2`): decir «esa cuenta usa Google» revelaría que la cuenta existe.
- `RN-5` Entrar **deja constancia** de cuándo (`lastSignedInAt`). Renovar la sesión cuenta
  como entrar: quien tiene la aplicación abierta está usándola.
- `RN-6` Esa constancia es **una fecha y nada más**. Ni dirección, ni navegador, ni
  localización: son datos personales que esta funcionalidad no necesita y que habría que
  custodiar, justificar y borrar.
- `RN-7` No se almacena ninguna credencial del proveedor (`FEAT-USR-002` `RN-5`).

## Flujo principal

1. La persona pulsa «Google» y el cliente pide `GET /auth/oauth/google`.
2. Abre la dirección que recibe, y guarda el `state` para compararlo a la vuelta.
3. Google devuelve un código al cliente.
4. El cliente lo manda a `POST /auth/oauth/google/callback`, **sin versiones legales**.
5. El servidor canjea el código, encuentra la identidad enlazada y abre sesión.
6. Responde `200` con la sesión e `isNewAccount: false`.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| La identidad no está enlazada y el correo tampoco existe | Es un alta, y entonces sí hacen falta las versiones legales | `422 TERMS_NOT_ACCEPTED` |
| El correo ya tiene cuenta y Google lo da por verificado | Se enlaza y se entra | `200`, con `linkedToExistingAccount` |
| El correo ya tiene cuenta y Google **no** lo verifica | No se enlaza nada | `409 EMAIL_ALREADY_REGISTERED` |
| La cuenta está expulsada o suspendida | No entra | `403 ACCOUNT_BLOCKED` |
| Google no responde | Mensaje genérico, sin detalle técnico | `502 AUTH_PROVIDER_UNAVAILABLE` |

## Contrato de API

Los de `FEAT-USR-002`; esta ficha no añade ninguno. Lo que añade es la garantía de que la
rama de login **no pide aceptación**, que es una regla y no un esquema.

## Por qué se guarda el último acceso

No lo pedía el diseño, y es lo único que esta ficha aporta al código. Tres razones, en orden
de peso:

| Para qué | Por qué hace falta |
|---|---|
| **Saber quién está dormido** | Hoy `FEAT-CRD-019` deduce la inactividad del historial de créditos, que es un sustituto imperfecto: quien lee mucho y no corrige nunca parece dormido sin estarlo |
| **Atender a una persona** | Quien mira una cuenta en el backoffice (`FEAT-MOD-005`) necesita saber si sigue usándose antes de decidir nada sobre ella |
| **Seguridad** | «Último acceso» es lo primero que mira alguien que sospecha que han entrado en su cuenta |

Se guarda en `OpenSession`, que es el **único sitio** por el que se abre una sesión: la entra
con contraseña, la de Google y la renovación pasan todas por ahí. Ponerlo en cada uno de los
tres sería garantizar que al cuarto se le olvida.

**No se publica ningún evento.** Un hecho por cada entrada de cada persona es mucho tráfico
para algo que hoy no consume nadie, y el día que `Credits` quiera alimentar su ventana de
inactividad con esto hará falta un hecho pensado para eso —«alguien lleva N días sin
entrar»— y no un goteo de entradas. Queda como `G-3`.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `user` | `last_signed_in_at`, anulable: una cuenta recién creada no ha entrado todavía |

## Criterios de aceptación

- [x] Quien ya entró con Google vuelve a entrar sin aceptar nada.
- [x] Entrar dos veces con la misma cuenta de Google no crea una segunda cuenta.
- [x] Una cuenta expulsada no entra con Google.
- [x] Quien se dio de alta con Google no entra por el formulario de contraseña, y el error no
      revela por qué.
- [x] Entrar deja constancia de la fecha.
- [x] Renovar la sesión también.
- [x] La constancia no guarda dirección, navegador ni localización.
- [x] El backoffice enseña el último acceso de una cuenta.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| G-3 | ¿Debería `Credits` alimentar su ventana de inactividad (`FEAT-CRD-019` `RN-2c`) con el último acceso en vez de con el historial de movimientos? | Haría falta un hecho de «lleva N días sin entrar», no uno por entrada. Hoy la aproximación por movimientos sobrestima la inactividad de quien lee y no corrige |
| G-4 | ¿Se le enseña a la persona su propio último acceso? | Es lo primero que se mira ante una sospecha, y hoy solo lo ve el backoffice |

## Estado

**Especificación:** `APPROVED` (2026-09-25).

**Implementación:** `DONE` (2026-09-25). El grueso del comportamiento venía ya de
`FEAT-USR-002`; lo que esta ficha añade es la constancia del acceso y la garantía, escrita
como regla y comprobada en pruebas, de que la rama de login no pide aceptación legal.
