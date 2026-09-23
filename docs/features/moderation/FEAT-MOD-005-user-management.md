---
id: FEAT-MOD-005
title: Gestión de usuarios desde el backoffice
context: Moderation
concept: Administration
actors: [Admin, Moderator]
spec_status: DRAFT
impl_status: TODO
priority: P2
sources:
  - conversation:2026-09-23 (backoffice de administración)
endpoints:
  - GET /admin/users
  - GET /admin/users/{userId}
  - POST /admin/users/{userId}/sanctions
  - POST /admin/users/{userId}/credit-adjustment
events: [SanctionImposed, SanctionLifted, CreditAdjustmentOrdered]
depends_on: [FEAT-MOD-004]
updated: 2026-09-23
---

# FEAT-MOD-005 — Gestión de usuarios desde el backoffice

## Resumen

Buscar usuarios, ver su ficha, imponer sanciones y ordenar ajustes manuales de créditos.

## El backoffice mira, no posee

`Moderation` **no es dueño de los usuarios**: lo es `User`. Lo que el backoffice ofrece es una
**vista compuesta** sobre varios contextos y la capacidad de **ordenar** acciones que ejecutan
ellos.

| Dato de la ficha | De dónde viene |
|---|---|
| Identidad, estado de la cuenta, fechas | `User` |
| Saldo y movimientos de créditos | `Credits` |
| Obras publicadas | `Work` |
| Correcciones dadas y recibidas | `Feedback` |
| Reclamaciones presentadas y recibidas | `Moderation` |
| Sanciones | `Moderation` |

La distinción importa: **una sanción la registra `Moderation` y la aplica `User`.** Si
`Moderation` marcase la cuenta directamente, sería un segundo dueño del estado del usuario.

## Reglas de negocio

- `RN-1` **Toda acción queda auditada**, incluidas las consultas. Saber quién miró la ficha de
  un usuario importa tanto como saber quién la cambió.
- `RN-2` Toda sanción y todo ajuste llevan **motivo obligatorio** y la reclamación que los
  originó, si la hay.
- `RN-3` Un ajuste de créditos es **un movimiento nuevo** con motivo `MANUAL_ADJUSTMENT`, que
  `Credits` aplica al recibir `CreditAdjustmentOrdered`. Nunca se edita un movimiento
  existente.
- `RN-4` Un ajuste manual **rompe la invariante contable si no se contabiliza como grifo**.
  Hay que registrarlo como emisión o absorción deliberada, no como una transferencia
  ([`FEAT-CRD-012`](../credits/FEAT-CRD-012-economy-health.md)).
- `RN-5` **No se expone contenido inédito sin necesidad.** El backoffice muestra la obra
  reclamada, no la biblioteca entera de un usuario.
- `RN-6` `Moderator` puede **ver e imponer sanciones**; solo `Admin` puede **ajustar créditos**
  y gestionar roles.
- `RN-7` Eliminar una cuenta desde el backoffice sigue la misma política que hacerlo el propio
  usuario: **anonimizar**, no borrar
  ([`FEAT-USR-013`](../user/FEAT-USR-013-delete-account.md)).

`RN-4` es el que más fácilmente se pasa por alto. Un ajuste manual crea o destruye créditos de
la nada; si no se registra como tal, la invariante contable empezará a fallar y nadie sabrá
por qué.

`RN-5` es una regla de contención: la plataforma custodia obra inédita, y un backoffice que
permite navegarla entera es un riesgo mucho mayor que el problema que resuelve.

## Sanciones

El catálogo **no está definido** (`MOD-1`). Lo que se puede fijar ya:

- `RN-8` Toda sanción tiene **tipo, alcance, motivo y vigencia**.
- `RN-9` Las temporales **caducan solas**, sin intervención.
- `RN-10` Se puede **levantar** una sanción antes de tiempo, con motivo.
- `RN-11` Al usuario **se le comunica** la sanción, su motivo y su duración. Una sanción que
  el usuario no entiende no corrige nada: solo le hace irse.

Familias plausibles, a decidir en `MOD-1`:

| Familia | Ejemplo |
|---|---|
| Aviso | Sin efecto funcional, queda registrado |
| Suspensión parcial | No puede corregir, o no puede publicar, durante `N` días |
| Suspensión total | No puede entrar durante `N` días |
| Expulsión | Permanente |

## Contrato de API

| Operación | Método y ruta | Rol |
|---|---|---|
| Buscar usuarios | `GET /admin/users` | `Moderator` |
| Ficha de usuario | `GET /admin/users/{userId}` | `Moderator` |
| Imponer sanción | `POST /admin/users/{userId}/sanctions` | `Moderator` |
| Levantar sanción | `DELETE /admin/sanctions/{sanctionId}` | `Moderator` |
| Ajustar créditos | `POST /admin/users/{userId}/credit-adjustment` | **`Admin`** |

## Criterios de aceptación

- [ ] Toda acción del backoffice, incluidas las consultas, queda en el registro de auditoría.
- [ ] Ninguna sanción ni ajuste se acepta sin motivo.
- [ ] Un ajuste de créditos produce un movimiento nuevo, nunca edita uno existente.
- [ ] Los ajustes manuales se contabilizan aparte en la invariante contable.
- [ ] Un `Moderator` no puede ajustar créditos.
- [ ] Las sanciones temporales caducan sin intervención.
- [ ] El usuario sancionado recibe motivo y duración.
- [ ] El backoffice no permite navegar obra inédita ajena fuera de lo reclamado.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **MOD-1** | ¿Qué catálogo de sanciones? | Sin él no hay nada que imponer |
| MOD-18 | ¿Puede el backoffice leer el contenido de una corrección no reclamada? | Es contenido privado entre dos personas |
| MOD-19 | ¿Se avisa al usuario de que su ficha ha sido consultada? | Transparencia frente a operatividad |
| MOD-20 | ¿Hay exportación de datos de un usuario a petición suya? | Obligación legal en varias jurisdicciones |

## Estado

**Especificación:** `DRAFT`. `MOD-1` bloquea la parte de sanciones.

**Implementación:** `TODO`.
