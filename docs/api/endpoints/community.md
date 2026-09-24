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
| `GET /api/v1/users/{userId}/subscriptions` | `listAuthorSubscriptions` | A quién sigue | FEAT-COM-027 | **Implementado** |
| `GET /api/v1/users/{userId}/subscribers` | `listSubscribers` | Quién le sigue | FEAT-COM-027 | **Implementado** |
| `PUT /api/v1/users/{userId}/block` | `blockUser` | Bloquear | FEAT-COM-034 | **Implementado** |
| `DELETE /api/v1/users/{userId}/block` | `unblockUser` | Levantar el bloqueo | FEAT-COM-034 | **Implementado** |
| `GET /api/v1/me/blocked-users` | `listBlockedUsers` | A quién tengo bloqueado | FEAT-COM-034 | **Implementado** |

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

---

## `GET /api/v1/users/{userId}/subscriptions` y `/subscribers`

**`operationId`:** `listAuthorSubscriptions`, `listSubscribers` · **Funcionalidad:**
[`FEAT-COM-027`](../../features/community/FEAT-COM-027-following-and-followers.md)

### Propósito

La pestaña «Amigos» de un perfil: a quién sigue esa persona y quién la sigue. La misma tabla
leída por sus dos extremos.

Dos endpoints y no uno con `?direction=`: son dos preguntas distintas sobre la misma tabla, y
un parámetro que cambia el significado de la respuesta acaba mal documentado.

### Autorización

**Públicos**, como el perfil al que pertenecen: una lista que solo se viera con sesión haría
inútil compartir ese perfil. Con sesión se ve además lo que la sesión permita.

Dos reglas, y la segunda es la que importa:

- **la lista se ve tanto como el perfil que la tiene.** Si ese perfil responde `404` por estar
  restringido, sus listas responden lo mismo;
- **cada fila se ve tanto como la persona que la ocupa.** Quien tiene su perfil cerrado no
  aparece en la lista de nadie.

Sin la segunda, cerrar el perfil no serviría de nada: bastaría con abrir los seguidores de
cualquier autor conocido para encontrar a quien no quiere ser encontrado. Su titular siempre
se ve a sí mismo, por lo mismo que ve su propio perfil.

Quién es visible lo decide `User`, por contrato publicado y **para la página entera de una
vez**. `Community` no aprende qué hace visible a alguien, y una llamada por fila sería un N+1
escondido detrás de un contrato.

### Reglas aplicadas

- Paginación **por cursor**, de lo más reciente a lo más antiguo. `limit` por defecto 20,
  máximo 100.
- Cada fila es una **tarjeta de perfil** —`@usuario`, nombre y avatar—, no un identificador.
- **Sin total**: contar aquí sería contar lo filtrado, una cifra distinta para cada visitante.
  Los contadores del perfil son `FEAT-USR-028`.

### Lo que hay que saber para consumirlas

**Una página puede traer menos filas que el `limit`, o ninguna, y seguir teniendo siguiente.**
El filtro de privacidad se aplica después de paginar, así que quien no sea visible deja un
hueco. Lo que dice si hay más es `pageInfo.nextCursor`; un cliente que cuente elementos para
decidir si sigue pidiendo se parará antes de tiempo.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `PROFILE_NOT_FOUND` | 404 | Ese perfil no existe **o no es visible** para quien pregunta. Los dos casos responden igual: un `403` confirmaría que la cuenta está ahí |
| `INVALID_CURSOR` | 422 | El cursor no lo produjo esta API |

### Los contadores no están aquí

Las dos cifras de seguidos y seguidores las sirve `getMyProfile` (`FEAT-USR-028`), que las
pide a este contexto por contrato. **Cuentan a todo el mundo, sin filtrar por privacidad**,
así que pueden ser mayores que las filas de estas listas: el contador es un dato de esa
persona, y filtrarlo lo convertiría en un dato de quien mira.

---

## `PUT` y `DELETE /api/v1/users/{userId}/block`, y `GET /api/v1/me/blocked-users`

**`operationId`:** `blockUser`, `unblockUser`, `listBlockedUsers` · **Funcionalidad:**
[`FEAT-COM-034`](../../features/community/FEAT-COM-034-block-user.md)

### Propósito

Cortar el contacto con alguien. A diferencia de silenciar, que es una preferencia de
visualización, **bloquear es una regla de acceso** y sus efectos atraviesan varios contextos.

### Autorización

Solo sobre uno mismo como sujeto: se bloquea desde la propia sesión, con la cuenta activada.
La lista de bloqueados es solo la propia — quién ha bloqueado otra persona no es asunto de
nadie, y no hay endpoint que lo pregunte.

### Reglas aplicadas

- **No se avisa al bloqueado.** Descubrirlo por el comportamiento es lo habitual; avisarle
  convierte el bloqueo en una confrontación.
- Al bloquear se deshacen **las dos** relaciones de seguimiento, y mientras dure ninguno puede
  seguir al otro. Intentarlo responde como si esa cuenta no existiera: un «te ha bloqueado»
  sería el aviso que la regla anterior evita.
- **Desbloquear no restaura nada**: ni los seguimientos ni un acceso revocado. Lo que devuelve
  es la posibilidad de empezar otra vez.
- Las dos operaciones son idempotentes.

### Qué hace `Community`, y qué no

Guarda el bloqueo, deshace los seguimientos y **publica el hecho**. No revoca accesos de
lector beta y no toca créditos, porque no sabe qué son.

Cada contexto decide al recibirlo:

| Contexto | Qué hace |
|---|---|
| `Reading` | Retira el acceso de lector beta del bloqueado a las obras del bloqueador |
| `User` | Deja de aceptar comentarios entre ambos, **por encima de cualquier ajuste de privacidad** |

Hacen falta los dos: con solo el primero, bloquear no serviría de nada en las obras públicas;
con solo el segundo, el bloqueado seguiría leyendo obra inédita.

> **Lo que esto se lleva por delante.** Si el bloqueado estaba corrigiendo, **pierde ese
> trabajo y no cobra**, porque nunca llegó a entregarlo. Su borrador se conserva. Lo ya
> entregado no se toca: el autor lo pagó y el lector lo ganó. Falta avisarle, y hoy no hay con
> qué.

### La lista no se filtra por privacidad

Es la única del sistema que no lo hace, y a propósito: si se filtrara, bloquear a alguien que
después cierra su perfil lo haría desaparecer de ahí y el bloqueo sería irreversible en la
práctica. Quien pregunta ya sabe quiénes son.

### Errores específicos

| `code` | HTTP | Cuándo |
|---|---|---|
| `USER_NOT_FOUND` | 404 | No hay ninguna cuenta con ese identificador |
| `CANNOT_BLOCK_YOURSELF` | 422 | Uno no se bloquea a sí mismo |
| `ACCOUNT_NOT_ACTIVATED` | 403 | Bloquear sin haber activado la cuenta |
| `INVALID_CURSOR` | 422 | Al listar, un cursor que no produjo esta API |
