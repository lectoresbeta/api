# Endpoints — `Moderation`

La moderación de la plataforma. Ficha del contexto:
[`moderation.md`](../../bounded-contexts/moderation.md).

**Este documento cubre por ahora las sanciones y la conversación con las partes.** El resto de operaciones del contexto
—reclamaciones, su resolución, el rol de moderador y el registro de auditoría— tienen su
contrato en [`openapi/paths/moderation.yaml`](../../../openapi/paths/moderation.yaml) y se
irán trayendo aquí.

## Operaciones

| Método y ruta | `operationId` | Propósito | Ficha | Estado |
|---|---|---|---|---|
| `GET /api/v1/admin/claims` | `listClaims` | La cola de reclamaciones | FEAT-MOD-002, FEAT-MOD-008 | **Implementado** |
| `POST /api/v1/admin/claims/{claimId}/review` | `reviewClaim` | Tomar y resolver | FEAT-MOD-002 | **Implementado** |
| `POST /api/v1/admin/sanctions` | `imposeSanction` | Sancionar a alguien | FEAT-MOD-006 | **Implementado** |
| `POST /api/v1/admin/sanctions/{sanctionId}/lift` | `liftSanction` | Levantar la sanción | FEAT-MOD-006 | **Implementado** |
| `POST /api/v1/admin/claims/{claimId}/messages` | `writeToClaimParty` | Escribir a una parte | FEAT-MOD-009 | **Implementado** |
| `GET /api/v1/me/claims/{claimId}/messages` | `listMyClaimThread` | Mi hilo con moderación | FEAT-MOD-009 | **Implementado** |
| `POST /api/v1/me/claims/{claimId}/messages` | `replyToModeration` | Responder | FEAT-MOD-009 | **Implementado** |
| `GET /api/v1/admin/users` | `searchUsers` | Buscar usuarios, **también por correo** | FEAT-MOD-005 | **Implementado** |
| `GET /api/v1/admin/users/{userId}` | `getUserSheet` | Ficha compuesta de un usuario | FEAT-MOD-005 | **Implementado** |
| `POST /api/v1/admin/users/{userId}/credit-adjustment` | `orderCreditAdjustment` | **Ordenar** un ajuste manual. Solo `Admin` | FEAT-MOD-005 | **Implementado** |
| `POST /api/v1/admin/claims/on-behalf` | `submitClaimOnBehalf` | Registrar una reclamación por alguien | FEAT-MOD-005 | **Implementado** |

---

## `POST /api/v1/admin/sanctions` y `.../{sanctionId}/lift`

**`operationId`:** `imposeSanction`, `liftSanction` · **Funcionalidad:**
[`FEAT-MOD-006`](../../features/moderation/FEAT-MOD-006-sanctions.md)

### Propósito

Las cuatro familias de sanción, y nada más. Un catálogo corto es una virtud: cuantos más
matices tenga, más difícil es que dos moderadores sancionen lo mismo de la misma manera.

### Autorización

Solo moderación, y lo comprueba el firewall **contra la base de datos en cada petición**: el
token no lleva roles.

### La frontera

**`Moderation` registra la sanción; `User` la aplica.** Este contexto no toca la cuenta de
nadie: publica `SanctionImposed` y `User` decide qué significa en su modelo. Si marcara la
cuenta directamente habría dos dueños del estado del usuario, y el día que discreparan no
habría forma de saber cuál manda.

### Las cuatro no se parecen

| | Entra | Escribe | Caduca sola |
|---|---|---|---|
| `WARNING` | Sí | Sí | — |
| `PARTIAL_SUSPENSION` | **Sí** | No | **Sí** |
| `FULL_SUSPENSION` | No | No | **No** |
| `EXPULSION` | No | No | No |

Que la suspensión parcial deje entrar no es una concesión menor: es lo que permite a la persona
leer la sanción, entender por qué la tiene y ver cuándo termina. Una suspensión que además
cierra la puerta no corrige nada, solo hace que se vaya.

La **expulsión bloquea pero no anonimiza**, y conserva el correo. No se puede a la vez borrar a
alguien y recordarlo para impedirle volver: si anonimizase, la sanción más grave del catálogo
sería la más fácil de esquivar.

### Reglas aplicadas

- El **motivo es obligatorio**: al usuario se le comunica, y una sanción que no se entiende no
  corrige nada.
- Una suspensión parcial **siempre lleva plazo**; las demás no lo admiten, para que nadie ponga
  una fecha a una expulsión y crea que caduca.
- **Ninguna mueve créditos.** Devolver el crédito repara al perjudicado y la sanción corrige al
  infractor: una reclamación puede producir lo primero sin lo segundo, y al revés.
- Toda sanción queda en el **registro de auditoría** con la identidad de quien la impuso. Un
  poder que se ejerce sin dejar nombre es un poder que nadie puede revisar después.
- **Cualquiera se puede levantar, incluida la expulsión.** Al no anonimizar, no destruye nada
  que impida volver atrás.
- **Levantar no es idempotente**, al revés que casi todo en esta API: lo ya levantado responde
  `409`, porque registrar dos veces el mismo acto administrativo dejaría en el historial algo
  que no ocurrió.

### Cómo llega a cortar

Las **escrituras se bloquean de inmediato**, porque comprueban el estado de la cuenta en cada
petición y no solo la firma del token. Las **lecturas tardan hasta quince minutos**, que es lo
que vive un token ([`decision:0007`](../../decisions/0007-jwt-sessions.md)).

### Efectos

Publica `SanctionImposed` con tipo, motivo y hasta cuándo, y `SanctionLifted` al levantarla.
`User` los consume para aplicar el efecto.

**No se publica nada cuando una sanción caduca sola**: las de plazo fijo terminan por la fecha
que `User` ya conoce, y anunciar cada vencimiento exigiría un proceso que recorriera la tabla
buscándolos — un temporizador que puede no ejecutarse, para decir algo que ya se sabía.

### Qué no está todavía

- El **aviso al usuario**. El hecho ya lleva tipo, motivo y plazo; quien se lo cuenta es
  `Notification`, que no lo escucha aún. Es la pieza que hace que una sanción corrija en vez de
  solo castigar.
- La **congelación de la deuda** durante una suspensión parcial. Es decisión de `Credits`, y sin
  ella quien tenga saldo negativo queda atrapado: corregir es la única forma de saldarlo y la
  sanción se lo impide.
- La **cola de asuntos vivos** del backoffice, que evita que una suspensión indefinida acabe
  siendo una expulsión que nadie decidió.


---

## La conversación con las partes

**`operationId`:** `writeToClaimParty`, `listMyClaimThread`, `replyToModeration` ·
**Funcionalidad:** [`FEAT-MOD-009`](../../features/moderation/FEAT-MOD-009-moderator-conversation.md)

### Propósito

Antes de decidir, el moderador puede hablar con cada parte **por separado**: pedir aclaraciones
al reclamante, dar al reclamado la oportunidad de explicarse.

### Tiene forma de estrella, no de sala

```text
        Reclamante
             │  (hilo privado)
             ▼
        MODERACIÓN  ◄──── ve los dos hilos
             ▲
             │  (hilo privado)
        Reclamado
```

Las partes **nunca se ven entre sí**, y no saben qué dice la otra ni siquiera si hay otra. No
es una restricción técnica: poner a denunciante y denunciado a discutir crearía el conflicto
que la moderación existe para evitar. El moderador media; no organiza un careo.

### Dónde descansa la privacidad

**En cómo está preguntada la consulta**, no en un filtro. La parte no pide «este hilo» sino
«el mío», y cuál es el suyo lo deduce el servidor de quién es: por eso la ruta del usuario no
lleva identificador de hilo. Con uno, leer el de la otra parte sería cambiar una palabra en la
dirección.

El hilo tampoco se filtra después de traerlo: va dentro de la consulta. Traer los dos y
quedarse con uno dejaría el mensaje ajeno viajando por dentro del servidor.

Para quien no es parte, la reclamación **no existe** (`404`). Un permiso denegado le
confirmaría que hay un expediente abierto, que ya es información sobre otras personas.

### Reglas aplicadas

- **Solo el moderador abre conversación.** Si una parte pudiera, la cola se llenaría de
  alegatos no solicitados y el expediente dejaría de ser un procedimiento para convertirse en
  una bandeja de entrada.
- Los mensajes son **inmutables**: no hay operación que los edite ni los borre. Si alguien
  pudiera reescribir lo que dijo, el expediente dejaría de ser prueba de nada.
- La conversación **no revela la identidad del moderador**: firma como «Moderación». Le protege
  de represalias y permite que la decisión se discuta por su contenido.
- El hilo se **cierra al resolverse** la reclamación. Se puede leer —es la única constancia que
  le queda a la parte— pero no continuar: seguir escribiendo sería alegar ante quien ya decidió.
- Una reclamación que **no señala a nadie** no tiene segundo hilo, y se dice por su nombre.

### Efectos

Cada mensaje del moderador queda en el **registro de auditoría con el hilo y sin el cuerpo**.
Lo que hay que poder revisar después es que habló con una parte; copiar el mensaje reproduciría
el expediente en un segundo sitio, con su propio control de acceso que mantener.

### Qué no está todavía

El **aviso** al destinatario de cada mensaje, que es de `Notification`. Sin él, la parte tiene
que entrar a mirar para enterarse de que moderación le ha escrito, que es lo que un expediente
no debería exigir.

---

## El backoffice de usuarios

**`operationId`:** `searchUsers`, `getUserSheet`, `orderCreditAdjustment`, `submitClaimOnBehalf` ·
**Funcionalidad:** [`FEAT-MOD-005`](../../features/moderation/FEAT-MOD-005-user-management.md)

### El backoffice mira, no posee

`Moderation` **no es dueño de los usuarios**: lo es `User`. Lo que estas operaciones ofrecen es
una **vista compuesta** sobre varios contextos y la capacidad de **ordenar** acciones que
ejecutan ellos.

La distinción no es teórica. Una sanción la registra `Moderation` y la aplica `User`; un ajuste
de créditos lo ordena `Moderation` y lo aplica `Credits`. Si el backoffice marcase las cuentas
directamente, habría dos dueños del estado de un usuario, y el segundo siempre acaba
desincronizado.

### Todo queda auditado, **también mirar**

`RN-1` no distingue entre consultar y cambiar, y eso sorprende hasta que se piensa: saber quién
miró la ficha de un usuario importa tanto como saber quién la cambió. Un backoffice donde
consultar es invisible es un backoffice donde se puede curiosear.

La búsqueda registra **el término buscado** y no la lista de resultados: lo que hay que poder
revisar después es qué se fue a buscar.

### El correo, y por qué se abre esa puerta

`GET /admin/users` busca por **correo**, nombre de usuario o nombre, y devuelve cuentas en
cualquier estado, incluidas las eliminadas. Las dos cosas son lo contrario de lo que hace el
buscador de la aplicación.

Es la puerta más ancha que `User` abre —ninguna otra respuesta de la API reparte direcciones—, y
se abre porque el caso es el inverso del que las demás protegen: **quien pregunta ya conoce la
dirección**, se la ha escrito la persona a la que va a atender. Lo que la contrapesa es la
auditoría.

### El saldo de créditos no está aquí

La ficha de usuario lista relatos, correcciones, reclamaciones y sanciones. **No el saldo**, y
su ausencia es una decisión: `Credits` no publica contratos
([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md)), así que nadie
puede preguntárselo.

Lo sirve él en su propia superficie de administración y el cliente hace dos llamadas. Abrir esa
puerta para ahorrarse una habría costado el aislamiento entero del contexto, que es lo que
permite que el modelo de créditos evolucione sin tocar nada más.

### Ordenar un ajuste no es ajustar

`POST /admin/users/{userId}/credit-adjustment` es **solo de `Admin`** (`RN-6`): mover créditos es
la única acción del backoffice que crea o destruye valor de la nada.

Responde **`202` y no `200`**. Cuando contesta, el ajuste está ordenado y no aplicado: lo aplica
`Credits` al recibir `CreditAdjustmentOrdered`. Decir `200` sería mentir sobre algo que quien lo
ordena va a comprobar mirando el saldo.

Lo que `Credits` decide, y no quien ordena:

- que sea **un movimiento nuevo**, nunca una edición (`RN-3`). Los movimientos son inmutables, y
  el saldo tiene que poder recalcularse desde cero y coincidir con la historia;
- que se contabilice como **grifo** y no como transferencia (`RN-4`). Es la que más fácil se pasa
  por alto: un ajuste crea o destruye créditos de la nada, y contarlo mal haría fallar la
  invariante contable de [`FEAT-CRD-012`](../../features/credits/FEAT-CRD-012-economy-health.md)
  sin que nadie supiera por qué.

El motivo es obligatorio (`RN-2`), cero no es un ajuste, y hay un tope de 1.000 créditos por
operación: un cero de más tecleado a las tres de la mañana no debería poder desequilibrar la
economía.

### Reclamar en nombre de otro

Cuando alguien tiene el botón bloqueado escribe por correo, y un moderador registra la
reclamación por él. Pasa por el mismo caso de uso que una ordinaria, así que hereda sus
comprobaciones, con tres diferencias:

- **el bloqueo acumulativo no cuenta y el cupo mensual sí** (`RN-15`). Si el bloqueo se aplicara
  aquí, quien lo tiene no podría reclamar por ningún camino y esta puerta no abriría nada. El
  cupo se mantiene porque la vía es **más lenta, no más barata**;
- **queda marcada** con quién la registró (`RN-12`). Si fueran indistinguibles de las ordinarias,
  nadie podría medir después cuánta moderación entra por la puerta de atrás;
- **quien la registra no puede resolverla** (`RN-14`), y se refunde con la regla que ya impedía
  a una parte revisar su propio asunto: la respuesta es la misma, `403 CLAIM_MODERATOR_IS_PARTY`.
  Registrarla no es decidir, pero quien la ha redactado a partir de un correo ya se ha formado
  una opinión.

### Efectos

Las dos lecturas escriben en el registro de auditoría y nada más. El ajuste publica
`CreditAdjustmentOrdered`. La reclamación en nombre de otro crea la reclamación y **no produce
ningún efecto** sobre lo reclamado, igual que una ordinaria.

---

## `GET /api/v1/admin/claims`

**`operationId`:** `listClaims` · **Funcionalidades:** [`FEAT-MOD-002`](../../features/moderation/FEAT-MOD-002-review-claim.md), [`FEAT-MOD-008`](../../features/moderation/FEAT-MOD-008-claim-queue.md)

### Propósito

La pantalla desde la que se trabaja la moderación.

### Autorización

`ROLE_MODERATOR`, resuelto contra la base de datos en cada petición
([`decision:0007`](../../decisions/0007-jwt-sessions.md)).

### Semántica

**La prioridad es la antigüedad y no es configurable.** No hay parámetro de ordenación, y la
ausencia es la regla: en una cola cuyo orden elige quien la trabaja, los casos incómodos se
hunden —los largos, los ambiguos, los de alguien conocido—, y una reclamación sin resolver es
alguien esperando.

Lo que sí se puede es **acotar**, por `reason` y por `targetType`, y no es lo mismo: elegir a
qué dedicarse esta tarde no es elegir qué atender antes. Dentro de lo acotado sigue mandando la
más antigua.

La respuesta trae **`total`**, con los mismos filtros que la lista. Es lo que convierte una
página en una cola: sin la cifra, quien modera ve veinte expedientes y no sabe si detrás hay
cero o mil, que es justo la información con la que se decide si hoy hay que pedir ayuda.

**Acotar no alcanza lo que la cola esconde.** La exclusión de las reclamaciones en las que
quien mira es parte (`FEAT-MOD-002` `RN-1`) se aplica antes que cualquier filtro, y tampoco
entran en el total: filtrar por el motivo exacto de una reclamación que te señala no la hace
aparecer.

Un filtro que no está en el catálogo se rechaza con `422` en lugar de ignorarse.

La cola **no lleva identidades**: el moderador decide sobre lo que se escribió, y saber de
quién es antes de mirarlo solo puede inclinar la decisión.
