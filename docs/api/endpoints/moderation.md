# Endpoints — `Moderation`

La moderación de la plataforma. Ficha del contexto:
[`moderation.md`](../../bounded-contexts/moderation.md).

**Este documento cubre por ahora las sanciones.** El resto de operaciones del contexto
—reclamaciones, su resolución, el rol de moderador y el registro de auditoría— tienen su
contrato en [`openapi/paths/moderation.yaml`](../../../openapi/paths/moderation.yaml) y se
irán trayendo aquí.

## Operaciones

| Método y ruta | `operationId` | Propósito | Ficha | Estado |
|---|---|---|---|---|
| `POST /api/v1/admin/sanctions` | `imposeSanction` | Sancionar a alguien | FEAT-MOD-006 | **Implementado** |
| `POST /api/v1/admin/sanctions/{sanctionId}/lift` | `liftSanction` | Levantar la sanción | FEAT-MOD-006 | **Implementado** |

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
