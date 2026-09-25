---
id: FEAT-WRK-006
title: Eliminar una obra con confirmación
context: Work
concept: Manuscript
actors: [Writer]
spec_status: APPROVED
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-25 (bloque de ciclo de vida de la obra)
endpoints:
  - DELETE /works/{workId}
  - POST /works/{workId}/restore
events: [WorkArchived, WorkRestored, WorkDeleted]
depends_on: [FEAT-WRK-016, FEAT-MOD-003]
updated: 2026-09-25
---

# FEAT-WRK-006 — Eliminar una obra con confirmación

## Resumen

El autor retira una obra de la plataforma. **Se archiva, no se borra**: desaparece del
catálogo, del perfil y de las búsquedas, deja de poder corregirse, y su autor la sigue viendo
y puede recuperarla.

## Por qué archivar y no borrar

Una obra de esta plataforma casi nunca es solo del autor:

| Lo que cuelga de ella | Qué pasaría si se borrase |
|---|---|
| Correcciones entregadas y **pagadas** | Alguien trabajó, cobró, y su trabajo desaparece sin rastro |
| Reclamaciones abiertas o resueltas | Se destruye el contenido reclamado, que es justo lo que hace falta conservar si alguien discute la decisión ([`FEAT-MOD-003`](../moderation/FEAT-MOD-003-block-work.md) `RN-6`) |
| Accesos de lector beta vigentes | Se revocan sin aviso y sin explicación |
| Movimientos de créditos que la citan | El historial apunta a un objeto que ya no existe |

**Borrar no es una operación local.** Archivar deja fuera a todos menos al autor, que es lo
que el autor realmente pide cuando pulsa «Eliminar», y conserva lo que no es suyo.

## Lo que el autor tiene que entender

El botón dice «Eliminar» y lo que ocurre es un archivado. Eso solo es honesto si el mensaje de
confirmación lo dice con estas palabras:

- **deja de verla todo el mundo menos tú**;
- **las correcciones que recibiste siguen ahí**, y quien las escribió las sigue viendo;
- **los créditos que costaron no se devuelven**;
- **puedes recuperarla**.

Un diálogo que diga «esta acción no se puede deshacer» sobre algo que sí se deshace enseña a
los usuarios a no leer los diálogos.

## Reglas de negocio

- `RN-1` Solo el autor archiva su obra.
- `RN-2` Archivar **requiere confirmación explícita**. La API la exige también: no basta con
  un `DELETE` suelto (`RN-8`).
- `RN-3` Una obra archivada **desaparece del catálogo, del perfil público, de las búsquedas y
  de las recomendaciones**, y sus capítulos dejan de servirse a cualquiera que no sea su autor.
- `RN-4` **No se puede corregir** una obra archivada. Los borradores en curso quedan
  inservibles y se avisa a quien los estuviera escribiendo, igual que al bloquear
  ([`FEAT-MOD-003`](../moderation/FEAT-MOD-003-block-work.md) `RN-4`).
- `RN-5` Las correcciones entregadas **no se revierten ni se borran**. Su autor las sigue
  leyendo y quien las escribió las sigue viendo en «Mis correcciones».
- `RN-6` Los **accesos de lector beta se revocan**. No hay nada que leer.
- `RN-7` El autor puede **restaurarla**, y vuelve al estado `DRAFT`. Nunca vuelve publicada ni
  en corrección: reabrir la puerta es una decisión aparte.
- `RN-8` Una obra **bloqueada por moderación no se archiva**. Sería la vía para hacer
  desaparecer contenido reclamado.
- `RN-9` El borrado **definitivo** existe solo por dos vías, y ninguna es este botón: la
  supresión de la cuenta ([`FEAT-USR-013`](../user/FEAT-USR-013-delete-account.md)) y una
  acción administrativa motivada.
- `RN-10` Una obra archivada **no cuenta** en el contador de relatos del perfil.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| No es el autor | Se rechaza sin revelar nada | `404` |
| Falta la confirmación | Se rechaza | `422` `CONFIRMATION_REQUIRED` |
| La obra está bloqueada por moderación | Se rechaza | `409` `WORK_BLOCKED` |
| Ya estaba archivada | Se acepta y no ocurre nada | `204` |
| Restaurar una obra que no está archivada | Se rechaza | `409` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Archivar | `DELETE /works/{workId}` | `archiveWork` |
| Restaurar | `POST /works/{workId}/restore` | `restoreWork` |

`DELETE` porque es lo que el autor cree que hace y lo que la interfaz llama «Eliminar». La
confirmación viaja en el cuerpo (`confirm: true`): un `DELETE` que se dispara por un enlace
mal pulsado no debería vaciar un perfil.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `WorkArchived` | Se archiva | `workId`, `authorId`, `archivedAt` |
| `WorkRestored` | Se restaura | `workId`, `authorId`, `restoredAt` |

Consumidores: `Reading` revoca accesos, `Feedback` invalida borradores, `Credits` deja de
ofrecer sus capítulos, `Community` la retira de muros y recomendaciones, `Notification` avisa
a quien estuviera corrigiendo.

`WorkDeleted` —el borrado definitivo— **sigue existiendo en el catálogo de eventos** y esta
funcionalidad no lo publica. Lo publicará `FEAT-USR-013`.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `work_ctx.work` | `archived_at TIMESTAMPTZ NULL` |

Las consultas del catálogo, del perfil y de la búsqueda añaden `archived_at IS NULL`, igual
que ya añaden `blocked_at IS NULL`.

## Criterios de aceptación

- [ ] El autor archiva su obra y deja de verla cualquier otra persona.
- [ ] La obra desaparece del catálogo, del perfil y de las búsquedas.
- [ ] El autor la sigue viendo, marcada como archivada.
- [ ] Sin confirmación explícita no se archiva.
- [ ] Los accesos de lector beta quedan revocados.
- [ ] Quien tenía un borrador de corrección recibe aviso y no puede entregarlo.
- [ ] Las correcciones entregadas se siguen leyendo por las dos partes.
- [ ] Los créditos no se devuelven.
- [ ] El autor la restaura y vuelve a `DRAFT`, nunca publicada.
- [ ] Una obra bloqueada por moderación no se puede archivar.
- [ ] El contador de relatos del perfil no la cuenta.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| W-31 | ¿Caduca el archivado y pasa a borrado definitivo a los N meses? | Sin caducidad, el archivo crece para siempre |
| W-32 | ¿Puede el autor pedir el borrado definitivo de una obra concreta? | Hoy solo borrando la cuenta entera, que es desproporcionado |
| W-33 | ¿Se avisa a los lectores beta de que la obra que leían se ha archivado? | Hoy simplemente desaparece de su lista |

## Estado

**Especificación:** `APPROVED` (2026-09-25). La decisión de archivar en
lugar de borrar se tomó el 2026-09-25.

**Implementación:** `TODO`.
