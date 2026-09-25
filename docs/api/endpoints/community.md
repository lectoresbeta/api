# Endpoints — `Community`

La vida social de la plataforma. Ficha del contexto:
[`community.md`](../../bounded-contexts/community.md).

Hasta `FEAT-COM-010`, `Community` era un modelo sin comportamiento —entidades, tablas y ni un
endpoint—. Con `FEAT-COM-001` y `FEAT-COM-002` tiene por fin **muro**: sitio donde escribir y
sitio donde leer lo escrito.

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
| `GET /api/v1/posts` | `listPosts` | El muro principal | FEAT-COM-001 | **Implementado** |
| `POST /api/v1/posts` | `createPost` | Publicar | FEAT-COM-002 | **Implementado** |
| `PATCH /api/v1/posts/{postId}` | `editPost` | Cambiar el texto de lo propio | FEAT-COM-002 | **Implementado** |
| `DELETE /api/v1/posts/{postId}` | `deletePost` | Retirar lo propio | FEAT-COM-002 | **Implementado** |
| `GET /api/v1/posts/{postId}/image` | `getPostImage` | La imagen de una publicación | FEAT-COM-002 | **Implementado** |
| `GET /api/v1/posts/{postId}/comments` | `listPostComments` | Los comentarios | FEAT-COM-006 | **Implementado** |
| `POST /api/v1/posts/{postId}/comments` | `createPostComment` | Comentar | FEAT-COM-006 | **Implementado** |
| `PATCH /api/v1/comments/{commentId}` | `editPostComment` | Editar el propio | FEAT-COM-006 | **Implementado** |
| `DELETE /api/v1/comments/{commentId}` | `deletePostComment` | Retirar el propio | FEAT-COM-006 | **Implementado** |
| `GET /api/v1/comments/{commentId}/replies` | `listCommentReplies` | El hilo de un comentario | FEAT-COM-031 | **Implementado** |
| `POST /api/v1/comments/{commentId}/replies` | `replyToComment` | Responder | FEAT-COM-031 | **Implementado** |
| `POST /api/v1/posts/{postId}/repost` | `repostPost` | Repostear, o deshacerlo | FEAT-COM-019 | **Implementado** |
| `DELETE /api/v1/posts/{postId}/repost` | `undoRepost` | Retirar el repost propio | FEAT-COM-019 | **Implementado** |
| `GET /api/v1/onboarding/author-suggestions` | `listAuthorSuggestions` | A quién proponer seguir | FEAT-COM-016 | **Implementado** |
| `GET /api/v1/home/author-suggestions` | `listHomeAuthorSuggestions` | El mismo bloque, en el muro | FEAT-COM-018 | **Implementado** |

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


---

## `GET` y `POST /api/v1/posts`

**`operationId`:** `listPosts`, `createPost` · **Funcionalidades:**
[`FEAT-COM-001`](../../features/community/FEAT-COM-001-main-wall.md),
[`FEAT-COM-002`](../../features/community/FEAT-COM-002-create-post.md)

### Propósito

El muro y lo que se escribe en él. Van juntos porque son las dos mitades de lo mismo: sin el
listado, publicar es escribir en un sitio que nadie mira.

### Autorización

Publicar, editar y eliminar exigen sesión **y cuenta activada**: son escrituras. Leer el muro
exige solo sesión, porque leer no es escribir y quien acaba de registrarse necesita ver dónde
ha entrado.

Sin sesión no hay muro, y no es una limitación técnica: sin saber quién mira no se puede
resolver qué publicaciones `FOLLOWERS` le alcanzan.

### Reglas aplicadas

- **Lo que compone el muro es la audiencia, no el seguimiento.** Se ve todo lo `EVERYONE`, lo
  `FOLLOWERS` de quienes sigue quien mira, y lo suyo. En un muro restringido a quienes sigues
  las dos audiencias serían indistinguibles y el selector del modal sería un adorno.
- **El filtrado va dentro de la consulta.** Traer lo que no se puede ver para descartarlo
  después rompería la paginación —una página de veinte devolvería doce— y dejaría el texto de
  alguien viajando por dentro del servidor.
- El **perfil ajeno es un techo**: quien no es visible para quien mira no aparece, y su
  publicación tampoco. Es la misma regla que aplican las listas de seguidores.
- Un **bloqueo** esconde las publicaciones en las dos direcciones.
- **Un adjunto como máximo** y el **formato se deriva** de él. Dos adjuntos se rechazan en vez
  de quedarse con el primero.
- El **autor es quien tiene la sesión**, aunque el cuerpo diga otra cosa.
- El texto se guarda **plano**: el marcado se retira en vez de rechazarse, y los emojis se
  conservan.
- Una **imagen se reescribe al guardarla** y pierde sus metadatos EXIF, que es donde va
  escrito dónde se tomó una foto.
- Un **enlace** se guarda como dirección y nada más, y solo `http` o `https`.
- Un **relato** se cita por `workId` y tiene que ser visible para quien publica.
- **Editar cambia solo el texto** y lo marca como editado. **Eliminar es definitivo para
  todos**, incluido su autor.

### La imagen no está en la carpeta pública

`GET /api/v1/posts/{postId}/image` comprueba **lo mismo que el muro**. Si la foto viviera bajo
`/api/v1/media/`, la de una publicación para seguidores quedaría protegida solo por lo difícil
que es adivinar una clave, y una clave se filtra el día que aparece en un registro, en un
`Referer` o en una captura de pantalla.

El coste, dicho: esa respuesta depende de quién pregunta, así que se sirve con
`Cache-Control: private` y no puede ir detrás de una caché compartida.

### Qué no está todavía

Filtrar por tipo de publicación y ordenar por relevancia son funcionalidades propias
(`FEAT-COM-009`, `FEAT-COM-024`), y la segunda espera a que alguien defina qué es «relevante».

El **vídeo** queda fuera: adjuntarlo arrastra transcodificación y almacenamiento con un coste
que no se parece al de una imagen (`FEAT-COM-037`).

La **previsualización de un enlace** tampoco existe. Generarla obligaría al servidor a visitar
la dirección que escribe cualquiera, y eso se decide aparte.

### Efectos

Publica `PostPublished` con la audiencia incluida, que es lo que impide que `Notification`
avise a quien no puede abrir lo que se le anuncia. Todavía no lo escucha nadie.


---

## `GET` y `POST /api/v1/posts/{postId}/comments`

**`operationId`:** `listPostComments`, `createPostComment`, `editPostComment`,
`deletePostComment` · **Funcionalidad:**
[`FEAT-COM-006`](../../features/community/FEAT-COM-006-comment-on-post.md)

### Propósito

La conversación bajo una publicación.

**No confundir con `Feedback`.** Un comentario es social; un feedback es la crítica de un
lector beta sobre una obra, mueve créditos y tiene reglas de acceso propias. Son conceptos de
contextos distintos que la interfaz llama parecido.

### Autorización

**Solo comenta quien puede ver la publicación**, y solo lee sus comentarios quien puede verla.
Es la misma regla preguntada por los dos lados, resuelta en un único sitio: si la publicación
no existe para quien pregunta, sus comentarios tampoco, y la respuesta es `404`.

Escribir exige además cuenta activada.

### Reglas aplicadas

- El texto se guarda **plano** y conserva los emojis. **No puede estar vacío**: un comentario
  no lleva adjunto, así que sin texto no hay comentario.
- El **contador de la publicación cuenta toda la conversación**, respuestas incluidas, que es
  lo que espera quien lee «3 comentarios».
- El autor **edita y retira lo suyo**, con el mismo criterio que una publicación. Lo ajeno
  responde `404`, no `403`.
- Retirar un comentario raíz **se lleva sus respuestas**, y el contador baja por todo lo que
  se va.
- Comentar **no mueve créditos**.

### Qué no está todavía

**«Más relevantes»**, que es el orden que el desplegable enseña por defecto. Su fórmula está
decidida —`apoyos + 2 × respuestas`— y los apoyos son `FEAT-COM-030`, que no existe. Hoy
responde `422 UNSUPPORTED_SORT` con `supportedSorts`, en vez de ordenar por media fórmula y
dejar que alguien se fíe.

Hay además una razón que conviene anotar para cuando llegue: un orden que cambia mientras
alguien lo lee —porque otro responde— no tiene una posición estable que codificar en un
cursor. Paginarlo bien exige meter el contador en el cursor, no solo sumar el término que
falta.

Que el autor de la publicación pueda retirar comentarios ajenos de lo suyo es moderación
propia y no tiene diseño (`I-13`).

### Efectos

Publica `PostCommented` con **a quién hay que avisar** —el autor de la publicación, el del
comentario, y el del comentario padre si es una respuesta— y sin el texto: un aviso no
necesita el cuerpo de lo escrito, y copiarlo lo pondría en una cola que persiste y reintenta.
Todavía no lo escucha nadie.


---

## Menciones

**Funcionalidad:** [`FEAT-COM-032`](../../features/community/FEAT-COM-032-mentions.md)

No tienen endpoint propio: viajan dentro de una publicación o de un comentario, al escribirlos
y al leerlos.

### Cómo se envían

Cada mención es **un `userId` y una posición**, nunca un nombre. Si el servidor la resolviera
leyendo el texto, cualquiera podría fabricar una que pareciera apuntar a otra persona
escribiendo su nombre a mano.

Un identificador que no existe **rechaza la publicación entera** (`MENTIONED_USER_NOT_FOUND`):
quien escribe cree que ha nombrado a alguien, y guardar el texto sin la mención le enseñaría el
resultado cuando ya no puede corregirlo.

El tope son **diez** (`TOO_MANY_MENTIONS`). No sale de ninguna pantalla: sale de lo que pasa
sin él, que es un envío masivo de avisos que cualquiera puede disparar.

### Cómo se sirven

Como **lista aparte del texto**, con la posición donde empieza cada una. El cliente no
interpreta la cadena buscando arrobas: que cliente y servidor tengan que coincidir en cómo se
parsea un texto es una fuente clásica de discrepancias, y aquí la discrepancia sería un enlace
apuntando a quien no es.

`name` es **el de ahora**. Como se guarda el identificador, cambiar de nombre actualiza todas
las menciones pasadas a la vez, y cambiar de `@usuario` no mueve ninguna de sitio — un nombre
de usuario se recicla a los 30 días, y una mención guardada como texto acabaría atribuyendo
palabras a quien no las dijo.

Una mención a una cuenta que ya no está llega con `userId` y `name` a `null`: se pinta de
forma neutra, sin enlace.

### La regla que cierra la fuga

Mencionar **no concede acceso a nada**, y **no se avisa a quien no puede ver dónde se le
menciona**. Las dos juntas son lo que impide usar una mención para filtrar lo escrito para
otros: sin la segunda, nombrar a alguien en una publicación restringida le enviaría un aviso
sobre contenido que no debería conocer.

La comprobación se hace en `Community`, que es quien conoce la audiencia, y no se delega en
quien envía el aviso: el hecho que no debe existir no llega a la cola.


---

## `GET` y `POST /api/v1/comments/{commentId}/replies`

**`operationId`:** `listCommentReplies`, `replyToComment` · **Funcionalidad:**
[`FEAT-COM-031`](../../features/community/FEAT-COM-031-reply-to-comment.md)

### Propósito

El hilo que cuelga de un comentario.

### La invariante

**Los hilos son planos.** Una respuesta cuelga siempre del comentario raíz, nunca de otra
respuesta, y si `commentId` es una respuesta el servidor resuelve al raíz por su cuenta. El
cliente responde a lo que tiene delante.

No es un detalle de modelo: un hilo plano se pagina y uno arbitrariamente profundo no, y cada
nivel de más convierte leer una conversación en una consulta recursiva. Lo que sustituye a la
profundidad es la **mención** a quien se responde, que dice lo mismo sin anidar.

Por eso pedir «las respuestas de esta respuesta» devuelve las del hilo entero: es la misma
conversación.

### Autorización

Las mismas reglas que comentar. Un comentario **no tiene audiencia propia**: hereda entera la
de su publicación, así que responder no es una puerta trasera a una conversación ajena.

### Reglas aplicadas

- El hilo se lee **de la más antigua a la más reciente**, sin desplegable que lo cambie: del
  revés obligaría a leer hacia arriba para entender a qué contesta cada cosa.
- La **mención precargada** a quien se responde la manda el cliente como cualquier otra, y se
  puede borrar antes de enviar: es una comodidad del compositor, no una obligación.
- Retirar el comentario raíz **se lleva sus respuestas**, y el contador de la publicación baja
  por todas.

### Qué no está todavía

El **«me gusta» de una respuesta** (`FEAT-COM-030`), que es la misma ausencia que impide
ordenar los comentarios por relevancia.


---

## `POST` y `DELETE /api/v1/posts/{postId}/repost`

**`operationId`:** `repostPost`, `undoRepost` · **Funcionalidad:**
[`FEAT-COM-019`](../../features/community/FEAT-COM-019-repost.md)

### Propósito

Volver a sacar la publicación de otra persona en el propio muro.

### Qué es un repost

**Una referencia, no una publicación aparte.** Los contadores y la conversación son los del
original —que es lo que enseña el diseño— y así no hay cadenas de reposts anidados que mostrar
ni que moderar. Puede llevar texto propio, que es lo que separa reenviar de citar.

Si el original se edita, el repost muestra la versión de ahora; si se elimina, el repost
desaparece con él. Las dos cosas son lo que significa ser una referencia.

### La regla que hay que vigilar

**Un repost no amplía la audiencia del original.** Quien no podía verlo sigue sin poder, y el
muro lo garantiza comprobando siempre el original y no la referencia.

Es la regla más fácil de romper de esta funcionalidad: basta con servir los reposts sin volver
a mirar el original para publicar contenido restringido.

Tampoco se repostea lo que no se puede ver, y un original invisible responde igual que uno
inexistente: distinguirlos revelaría que existe una publicación que esa persona no debería
conocer.

### El botón alterna

`POST` repostea, y **volver a llamarlo lo deshace**. Responde `200` con `reposted`, no `201`,
porque la misma llamada puede dejar las dos cosas.

`DELETE` existe además porque dicen cosas distintas: aquél es «cambia lo que haya», este es
«quítalo». Y **no comprueba la audiencia del original**: deshacer algo propio tiene que
funcionar aunque el autor lo haya restringido después, o quedaría un repost que su dueño no
puede quitar.

Se puede repostear lo propio: sirve para volver a sacar algo antiguo.

### Una vez por página

Una publicación aparece **una sola vez** aunque llegue por dos caminos —como ella misma y como
repost de alguien—. Se queda la entrada más reciente. Ver la misma tarjeta dos veces seguidas
parece un error de la plataforma.

Es por página y no global: recordar entre páginas lo ya enseñado exigiría un cursor que llevara
esa lista dentro.

### Efectos

Publica `PostReposted`, que avisará al autor original. Todavía no lo escucha nadie.


---

## `GET /api/v1/onboarding/author-suggestions`

**`operationId`:** `listAuthorSuggestions` · **Funcionalidad:**
[`FEAT-COM-016`](../../features/community/FEAT-COM-016-onboarding-author-suggestions.md)

### Propósito

El paso 3 del onboarding: a quién proponer seguir, a partir de los géneros elegidos en el paso
anterior.

### El caso normal no es la lista llena

En una plataforma recién lanzada no hay autores que sugerir, y **ese es justamente el momento
en que todo el mundo pasa por aquí**. De ahí que la respuesta sea siempre `200`, incluso con la
lista vacía: la ausencia de autores es un estado normal, no un fallo.

Cuando no hay bastantes por género, la lista se completa con los más seguidos de la plataforma,
marcados con `matchedGenres` vacío: es preferible proponer autores populares aunque no encajen
que enseñar una pantalla casi vacía, y decirlo permite a la interfaz no prometer una afinidad
que no hay.

Si aun así no llegan al mínimo, el paso se omite. **Quien lo decide es el servidor**
(`shouldDisplay`): si lo decidiera el cliente habría dos sitios donde cambiar el umbral y uno
se quedaría atrás.

`reason` distingue dos silencios que no significan lo mismo: `NOT_ENOUGH_AUTHORS` —no hay
gente— y `ALREADY_FOLLOWING_ALL` —ya la sigues toda—.

### De dónde salen los datos

De **proyecciones que `Community` mantiene con hechos**, no de contar al leer: en qué géneros
escribe cada autor, cuántos seguidores tiene y cuántas obras lleva. Un `COUNT` por tarjeta en
cada carga del onboarding se degrada justo cuando la plataforma empieza a funcionar.

Ninguna consulta cruza a `User` ni a `Work`. El precio de esa frontera son estas tablas, y el
registro de hechos ya aplicados que las protege de una reentrega: a diferencia del grafo de
seguidores, que afirma un estado, estos contadores **suman**.

`publicationCount` son **obras publicadas**, no mensajes del muro: quien elige a quién seguir
por los textos que escribe no está midiendo cuánto habla.

### Seguir desde aquí

Con la operación de siempre, `PUT /api/v1/users/{userId}/subscription`. **No hay una
suscripción «de onboarding» distinta**, así que tampoco una ruta distinta.

### Qué no está todavía

El filtro de **cuentas activadas**. La proyección de autores no distingue todavía una cuenta
activada de una que no lo está; mientras tanto solo se sugiere a quien ha publicado una obra,
lo que ya exige tenerla activada.

### El mismo motor en la Home

`GET /api/v1/home/author-suggestions` es el bloque «todavía no sigues a ningún autor» y **usa
el mismo caso de uso**. Lo propio suyo son tres cosas: la ruta, cuántas tarjetas caben —cuatro,
no diez— y una condición, que solo se pinta a quien no sigue a nadie y desaparece en cuanto
sigue a alguien (`ALREADY_FOLLOWING_SOMEBODY`).

Dos rutas y un motor a propósito: el contexto de uso es distinto y es previsible que el tamaño
diverja, pero duplicar el criterio sería tener dos sitios donde cambiarlo.
