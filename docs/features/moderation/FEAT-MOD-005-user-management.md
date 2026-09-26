---
id: FEAT-MOD-005
title: Gestión de usuarios desde el backoffice
context: Moderation
concept: Administration
actors: [Admin, Moderator]
spec_status: APPROVED
impl_status: PARTIAL
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
updated: 2026-09-25
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

## Reclamar en nombre de un usuario

Cuando alguien tiene el botón de reclamar bloqueado, escribe por correo
([`FEAT-MOD-001`](FEAT-MOD-001-submit-claim.md) `RN-6d`). Un moderador **registra esa
reclamación en su nombre** desde el backoffice.

- `RN-12` Una reclamación creada en nombre de otro **queda marcada como tal**, con quién la
  registró y por qué vía. No se disfraza de reclamación ordinaria.
- `RN-13` El usuario **la ve en su sección de reclamaciones**
  ([`FEAT-MOD-010`](FEAT-MOD-010-my-claims.md)) y puede hablar con el moderador desde ahí, aun
  estando bloqueado para presentar nuevas.
- `RN-14` **Quien la registra no puede resolverla**: sigue aplicando el conflicto de interés
  ([`FEAT-MOD-002`](FEAT-MOD-002-review-claim.md) `RN-1`).
- `RN-15` Consume el **cupo mensual del usuario** y cuenta para su bloqueo acumulativo si se
  desestima (`MOD-45`). La vía es más lenta, no más barata.

`RN-14` no es evidente y sí importante: registrar una reclamación no es una decisión, pero
quien la ha redactado a partir de un correo **ya se ha formado una opinión**. Debe resolverla
otro.

`RN-12` mantiene honesta la trazabilidad. Si estas reclamaciones fueran indistinguibles de las
ordinarias, nadie podría medir después cuánta moderación entra por la puerta de atrás.

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
| Buscar usuarios | `GET /api/v1/admin/users` | `Moderator` |
| Ficha de usuario | `GET /api/v1/admin/users/{userId}` | `Moderator` |
| Imponer sanción | `POST /api/v1/admin/sanctions` | `Moderator` |
| Levantar sanción | `POST /api/v1/admin/sanctions/{sanctionId}/lift` | `Moderator` |
| Ajustar créditos | `POST /api/v1/admin/users/{userId}/credit-adjustment` | **`Admin`** |
| Reclamar en nombre de un usuario | `POST /api/v1/admin/claims/on-behalf` | `Moderator` |

Las dos de sanciones **no son las que esta ficha proponía**, y no se duplican: existen ya desde
[`FEAT-MOD-006`](FEAT-MOD-006-sanctions.md), que es donde vive el catálogo. Una sanción no
cuelga del usuario en la URL porque lo que se crea es la sanción, y levantarla es un hecho
nuevo y no un borrado —`POST .../lift` y no `DELETE`—: un movimiento de moderación no se
deshace, se compensa.

## Criterios de aceptación

- [x] Toda acción del backoffice, incluidas las consultas, queda en el registro de auditoría.
- [x] Ninguna sanción ni ajuste se acepta sin motivo.
- [x] Un ajuste de créditos produce un movimiento nuevo, nunca edita uno existente.
- [x] Los ajustes manuales se contabilizan aparte en la invariante contable.
- [x] Un `Moderator` no puede ajustar créditos.
- [x] Las sanciones temporales caducan sin intervención. *De [`FEAT-MOD-006`](FEAT-MOD-006-sanctions.md).*
- [x] El usuario sancionado recibe motivo y duración. *De [`FEAT-MOD-006`](FEAT-MOD-006-sanctions.md).*
- [x] El backoffice no permite navegar obra inédita ajena fuera de lo reclamado.
- [x] Una reclamación creada en nombre de otro queda marcada con quién la registró.
- [x] Quien la registra no puede resolverla.
- [x] El usuario la ve en su sección aunque esté bloqueado para presentar nuevas.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| MOD-18 | ¿Puede el backoffice leer el contenido de una corrección no reclamada? | Es contenido privado entre dos personas |
| MOD-47 | ¿Qué hace el moderador si el correo no aporta nada reclamable? | No registrarla también es una decisión, y no queda traza |
| MOD-19 | ¿Se avisa al usuario de que su ficha ha sido consultada? | Transparencia frente a operatividad |
| MOD-20 | ¿Hay exportación de datos de un usuario a petición suya? | Obligación legal en varias jurisdicciones |

Resuelta: `MOD-1`, el catálogo de sanciones, que es
[`FEAT-MOD-006`](FEAT-MOD-006-sanctions.md).

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `PARTIAL` (2026-09-25).

Cuatro cosas que la ficha no preveía y que la implementación obligó a decidir:

- **el saldo de créditos no está en la ficha del usuario**, y su ausencia es la decisión más
  importante de esta funcionalidad. La ficha lo lista como uno de los datos que el backoffice
  compone, y componerlo es imposible: `Credits` no publica contratos
  ([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md)), así que
  nadie puede preguntárselo. Lo sirve él en su propia superficie de administración
  ([`FEAT-CRD-012`](../credits/FEAT-CRD-012-economy-health.md)) y quien pinta la pantalla hace
  dos llamadas. Abrir esa puerta para ahorrarse una llamada habría costado el aislamiento
  entero del contexto;
- **`User` publica un contrato nuevo, y es el más ancho que tiene.**
  `AdministrableAccounts` reparte **direcciones de correo** hacia una pantalla, que es
  justamente lo que las otras puertas de ese contexto se cuidan de no hacer:
  `RegisteredUsers::idOfEmail()` dice expresamente que no se use desde un endpoint. Se abre
  porque el caso es el contrario —quien pregunta ya conoce la dirección, se la ha escrito la
  persona a la que va a atender— y lo que la contrapesa es `RN-1`: toda consulta queda
  auditada;
- **reclamar en nombre de otro salta el bloqueo y no el cupo.** La ficha dice que consume el
  cupo mensual (`RN-15`) y no dice qué pasa con el bloqueo acumulativo, y aplicarlo aquí
  vaciaría de sentido la vía entera: quien tiene el botón bloqueado no podría reclamar por
  ningún camino. El cupo se mantiene porque la vía es más lenta, no más barata;
- **hay un tope por ajuste** de 1.000 créditos, que la ficha no menciona. Un ajuste manual es
  la única vía que crea créditos de la nada, y un cero de más tecleado a las tres de la mañana
  no debería poder desequilibrar la economía. Quien necesite más hace dos, y los dos quedan
  auditados.

Y una decisión de forma: **ordenar un ajuste responde `202` y no `200`**. Cuando contesta, el
ajuste está ordenado y no aplicado —lo aplica `Credits` al recibir el hecho—, y decir `200`
sería mentir sobre algo que quien lo ordena va a comprobar mirando el saldo.

**Falta**, y por eso la implementación es `PARTIAL`:

- **eliminar una cuenta desde el backoffice** (`RN-7`), que sigue a
  [`FEAT-USR-013`](../user/FEAT-USR-013-delete-account.md) y está `BLOCKED`;
- **la gestión de roles desde la ficha**, que existe pero por su propio endpoint
  ([`FEAT-MOD-004`](FEAT-MOD-004-moderator-role.md)) y no está compuesta aquí;
- **lo que el moderador hace si el correo no aporta nada reclamable** (`MOD-47`): no
  registrarla también es una decisión, y hoy no deja traza.

  **No es trabajo pendiente, es una pregunta sin responder** (anotado el 2026-09-26). Registrar
  una no-reclamación exige decidir qué se guarda de un correo que nunca llegó a ser un
  expediente, quién puede consultarlo y cuánto se conserva — y es material que acusa a alguien,
  así que la respuesta cambia lo que se construye. La vía de registrar **en nombre de otro** ya
  existe ([`FEAT-MOD-001`](FEAT-MOD-001-submit-claim.md) `RN-6d`); lo que falta es decidir si
  desestimar también se registra, y con qué.
