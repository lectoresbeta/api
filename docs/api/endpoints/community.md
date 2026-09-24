# Endpoints — `Community`

La vida social de la plataforma. Ficha del contexto:
[`community.md`](../../bounded-contexts/community.md).

**Este documento empieza con una sola funcionalidad**, y es la que pone el contexto en
marcha: hasta `FEAT-COM-010`, `Community` era un modelo sin comportamiento — entidades,
tablas y ni un endpoint.

## Operaciones

| Método y ruta | `operationId` | Propósito | Ficha | Estado |
|---|---|---|---|---|
| `GET /api/v1/users/{userId}/subscription` | `getAuthorSubscription` | ¿Sigo a esta persona? | FEAT-COM-010 | **Implementado** |
| `PUT /api/v1/users/{userId}/subscription` | `subscribeToAuthor` | Seguir | FEAT-COM-010 | **Implementado** |
| `DELETE /api/v1/users/{userId}/subscription` | `unsubscribeFromAuthor` | Dejar de seguir | FEAT-COM-010 | **Implementado** |

---

## `GET`, `PUT` y `DELETE /api/v1/users/{userId}/subscription`

**`operationId`:** `getAuthorSubscription`, `subscribeToAuthor`, `unsubscribeFromAuthor` ·
**Funcionalidad:** [`FEAT-COM-010`](../../features/community/FEAT-COM-010-subscribe-to-author.md)

### Propósito

Seguir a alguien y dejar de seguirle. Es la acción del botón «Seguir» del perfil ajeno, del
paso 3 del onboarding y de las sugerencias de la Home: **una sola acción con tres puertas**.

La interfaz dice «Seguir»; el glosario llama al concepto `AuthorSubscription` y es normativo,
así que la ruta y los `operationId` usan su vocabulario.

### Autorización

Seguir y dejar de seguir exigen sesión **y cuenta activada**. Consultar, solo sesión, y
responde **únicamente por quien pregunta**: a quién sigue otra persona es `FEAT-COM-027`, y si
esas listas son públicas sigue sin decidirse (`CM-14`).

### Reglas aplicadas

- **Las tres operaciones son idempotentes.** Seguir a quien ya sigues, o dejar de seguir a
  quien no sigues, dejan el mundo igual y responden lo mismo. De ahí `PUT`/`DELETE` sobre un
  recurso de estado en vez de un `POST`: con `POST` tocaría un `409` a la segunda pulsación,
  que es decirle a alguien que ha fallado cuando lo que pedía ya se cumple.
- Nadie se sigue a sí mismo.
- Solo se puede seguir a una cuenta que existe, comprobado contra el **contrato publicado** de
  `User` (`RegisteredUsers`), nunca leyendo sus tablas.
- **Seguir no concede acceso a nada**: no abre obras, no da acceso de lector beta y no inicia
  conversación. Y no anula lo que su titular cerró — con el perfil o los comentarios en
  `NOBODY`, seguir no cambia nada. Decide **quién entra en una audiencia**, no cuál eligió su
  dueño.

### Efectos

Publica `AuthorSubscribed` y `AuthorUnsubscribed`, con los dos identificadores y nada más.

`User` los consume para mantener su copia del grafo y resolver las audiencias `FOLLOWERS` de
[`FEAT-USR-038`](../../features/user/FEAT-USR-038-privacy-settings.md) — quién ve mi perfil y
quién puede comentar mis textos. Eso las hace **consistentes en diferido**: entre seguir a
alguien y poder comentar sus textos pasa lo que tarde la cola.

El rodeo es una regla, no una casualidad: `CheckAuthorAudience` es un contrato publicado, y
[`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md) prohíbe que un
contrato llame al de otro contexto mientras responde.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `USER_NOT_FOUND` | 404 | No hay ninguna cuenta con ese identificador. Solo al seguir: **consultar responde `false`**, porque la pregunta es sobre mi relación con él y un `404` contaría si esa cuenta existe |
| `CANNOT_SUBSCRIBE_TO_YOURSELF` | 422 | Uno no se sigue a sí mismo |
| `ACCOUNT_NOT_ACTIVATED` | 403 | Seguir o dejar de seguir sin haber activado la cuenta |
