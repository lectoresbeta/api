---
id: FEAT-COM-034
title: Bloquear a un usuario
context: Community
concept: Relationship
actors: [User]
spec_status: APPROVED
impl_status: PARTIAL
priority: P2
sources:
  - conversation:2026-09-22 (menú «···» del perfil ajeno)
  - docs/ui/user-profile.md
endpoints:
  - PUT /users/{userId}/block
  - DELETE /users/{userId}/block
  - GET /me/blocked-users
events: [UserBlocked, UserUnblocked]
depends_on: [FEAT-COM-010]
updated: 2026-09-24
---

# FEAT-COM-034 — Bloquear a un usuario

## Resumen

Cortar el contacto con otra persona. A diferencia de silenciar (`FEAT-COM-033`), que es una
preferencia de visualización, **bloquear es una regla de acceso** y sus efectos atraviesan
varios bounded contexts.

## Qué hace un bloqueo

| Efecto | Detalle |
|---|---|
| Seguimiento | Se deshace **en ambos sentidos** |
| Muro | Ninguno ve el contenido del otro |
| Publicaciones y comentarios | El bloqueado no puede comentar ni reaccionar al contenido del que bloquea |
| Mensajes directos | Se cortan. No se pueden iniciar ni continuar |
| Perfil | **Por decidir** si se oculta o se muestra vacío (`B-1`) |
| Menciones | El bloqueado no puede mencionar al que bloquea |
| Reciprocidad | El bloqueo es **unilateral en la intención y bidireccional en el efecto** |

## Lo que hay que decidir: el contenido ya existente

Bloquear es fácil de especificar hacia el futuro y difícil hacia el pasado. Lectores Beta
tiene además un caso que otras plataformas no: **el acceso de lector beta y sus créditos**.

| Situación | Pregunta | Referencia |
|---|---|---|
| El bloqueado es **lector beta** de una obra del que bloquea | ¿Se le revoca el acceso? ¿Puede terminar una corrección en curso? | `B-2` |
| El bloqueado ya dejó **feedback** en una obra del que bloquea | ¿Se oculta? ¿Se conserva? El autor ya lo pagó | `B-3` |
| Hay una **corrección en curso** de esa persona | ¿La entrega y cobra, o se pierde su trabajo? | `B-2` |
| Hay comentarios y respuestas cruzados en el muro | ¿Desaparecen o se conservan sin enlace? | `B-4` |

`B-3` es el más delicado: el autor **ya pagó créditos** por ese feedback. Borrarlo le haría
perder lo que compró; conservarlo visible contradice el bloqueo. Una salida razonable es
conservarlo pero anonimizar al autor de cara al bloqueador, aunque eso también tiene aristas.

**No se resuelve aquí.** Son decisiones de producto con consecuencias económicas.

## Qué ocurre al bloquear

**Decidido** (`B-2`, `B-3`): el bloqueo corta el acceso del bloqueado al contenido del
bloqueador, **incluido el trabajo que tuviera en curso**.

| Efecto | |
|---|---|
| El bloqueado **deja de ver el contenido** del bloqueador | Perfil, obras, publicaciones, comentarios |
| Pierde el **acceso a las obras** de quien le bloqueó | Desde el momento del bloqueo |
| **No puede terminar una corrección empezada** | Su borrador deja de poder entregarse |
| Las correcciones **ya entregadas y pagadas** | **Se conservan.** Ya eran trabajo hecho y cobrado |

### La regla incómoda, dicha de frente

Un lector que llevaba dos horas escribiendo una corrección **pierde ese trabajo** si el autor
le bloquea, y **no cobra**, porque nunca llegó a entregarla.

Es la única situación del sistema en la que alguien pierde trabajo real por una decisión
ajena, y conviene no disimularla:

- **Debe avisársele**, con un mensaje que deje claro que no ha hecho nada mal. Descubrir que
  un texto ha desaparecido sin explicación es mucho peor que que te lo digan.
- **No genera ningún cargo al autor.** Nada se entregó, así que nada se paga.
- **Su borrador se conserva** aunque no pueda entregarse, porque es texto suyo.

Que el bloqueo pueda usarse así —dejar que alguien corrija y bloquearle antes de que entregue—
es un abuso posible, y **se asume a conciencia**: el derecho a bloquear pesa más que el caso
raro de quien lo use de mala fe. No se registra ni se penaliza.

- `RN-B1` El bloqueado pierde el acceso a las obras del bloqueador desde ese instante.
- `RN-B2` Una corrección en curso del bloqueado **no se puede entregar**, y se le avisa.
- `RN-B3` Esa corrección **no genera cargo** al autor ni abono al lector.
- `RN-B4` Las correcciones ya entregadas **no se revierten ni se ocultan**: el autor pagó por
  ellas y el lector las ganó.
- `RN-B5` El bloqueo **no borra** comentarios ni publicaciones anteriores: los oculta al
  bloqueado.

`RN-B4` es la contrapartida de `RN-B2`: el bloqueo corta el futuro, no reescribe el pasado.

## Reglas de negocio

- `RN-1` El bloqueo lo aplica un usuario sobre otro. No requiere consentimiento ni aviso.
- `RN-2` **No se notifica al bloqueado.** Descubrirlo por el comportamiento es lo habitual;
  avisarle convierte el bloqueo en una confrontación.
- `RN-3` Al bloquear se deshacen **las dos** relaciones de seguimiento, si existen.
- `RN-4` Mientras dure el bloqueo, ninguno puede volver a seguir al otro.
- `RN-5` Los mensajes directos entre ambos quedan cortados.
- `RN-6` El bloqueado no puede comentar, responder, reaccionar, repostear ni mencionar
  contenido del que bloquea.
- `RN-7` Desbloquear **no restaura** las relaciones de seguimiento deshechas: hay que volver
  a seguir.
- `RN-8` Un usuario no se bloquea a sí mismo.
- `RN-9` Bloquear exige la cuenta activada (`FEAT-USR-025`).
- `RN-10` El bloqueo se publica como evento para que los demás contextos apliquen sus
  consecuencias. `Community` **no** revoca accesos ni toca créditos por su cuenta.

`RN-10` es lo que mantiene la arquitectura en pie: `Community` conoce relaciones sociales, no
accesos a obras ni créditos. Publica el hecho y cada contexto decide.

## Cómo se aplica sin acoplar contextos

```text
Community: UserBlocked
     │
     ├──▶ Reading      ¿revoca el acceso de lector beta?        (B-2)
     ├──▶ Feedback     ¿oculta el feedback cruzado?             (B-3)
     ├──▶ Credits      ¿paga una corrección ya entregada?       (B-2)
     └──▶ Notification deja de enviar avisos entre ambos
```

Cada contexto decide su reacción. Ninguno pregunta a `Community` si dos personas se han
bloqueado: **mantiene su propia proyección** a partir del evento.

Esa proyección es necesaria porque el bloqueo hay que comprobarlo en operaciones que ocurren
lejos del muro: al conceder un acceso, al enviar feedback, al mostrar un comentario.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Bloquear | `PUT /users/{userId}/block` | `blockUser` |
| Desbloquear | `DELETE /users/{userId}/block` | `unblockUser` |
| Listar bloqueados | `GET /me/blocked-users` | `listBlockedUsers` |

El listado hace falta: sin él, un bloqueo es irreversible en la práctica porque el usuario no
puede encontrar a quién bloqueó.

## Eventos

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `UserBlocked` | Se bloquea | `Reading`, `Feedback`, `Credits`, `Notification` | `blockerId`, `blockedId` |
| `UserUnblocked` | Se desbloquea | Los mismos | `blockerId`, `blockedId` |

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `user_block` | `blocker_id`, `blocked_id`, `created_at`, con clave única sobre el par |

Índices en ambas direcciones: hay que poder responder «¿a quién he bloqueado?» y «¿quién me
ha bloqueado?», y la segunda es la que más se consulta.

## Criterios de aceptación

- [x] Bloquear deshace las dos relaciones de seguimiento.
- [x] El bloqueado no recibe ningún aviso **del bloqueo**. *`Notification` no consume `UserBlocked`. Sí recibe el aviso de haber perdido el acceso, que es otra cosa y no dice por qué: ver abajo.*
- [x] Ninguno puede seguir al otro mientras dure el bloqueo.
- [ ] Los mensajes directos entre ambos se cortan. *La mensajería (`FEAT-COM-011`) no existe.*
- [ ] El bloqueado no puede comentar ni mencionar al que bloquea. *En el muro no: las publicaciones y sus comentarios (`FEAT-COM-002`, `FEAT-COM-006`) no existen. **En las obras sí**, que es donde hoy se comenta: ver abajo.*
- [x] Desbloquear no restaura los seguimientos.
- [x] No se puede uno bloquear a sí mismo.
- [x] El usuario puede consultar a quién tiene bloqueado.
- [x] `Community` no revoca accesos de lector beta ni toca créditos: publica el evento.
- [x] Con la cuenta sin activar devuelve `403`.

### Y los de «qué ocurre al bloquear»

- [x] `RN-B1`: el bloqueado pierde el acceso a las obras del bloqueador desde ese instante.
- [x] `RN-B2`: una corrección en curso del bloqueado no se puede entregar.
- [x] `RN-B3`: esa corrección no genera cargo ni abono. *Se cumple por construcción: no hay entrega, y el cargo lo dispara la entrega.*
- [x] `RN-B4`: las correcciones ya entregadas no se revierten ni se ocultan.
- [ ] `RN-B5`: el bloqueo no borra comentarios ni publicaciones anteriores, los oculta al bloqueado. *No hay muro; y lo entregado en una obra se conserva, que es la mitad que sí existe.*
- [x] Se le avisa a quien pierde una corrección en curso. *Lo cumple [`FEAT-NOT-001`](../notification/FEAT-NOT-001-in-app-notifications.md): el bloqueo publica `BetaReaderAccessRevoked` por el mismo camino que cualquier otra revocación, y `Notification` lo convierte en un aviso `BETA_READER_ACCESS_REVOKED`.*

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~B-2~~ | ¿Bloquear revoca el acceso de lector beta, y puede terminar quien ya estaba corrigiendo? | Cortarlo destruiría trabajo real de un tercero |
| ~~B-3~~ | ¿Qué pasa con el feedback que el bloqueado ya dejó, y que el autor **ya pagó**? | Borrarlo le hace perder lo comprado; conservarlo contradice el bloqueo |
| B-1 | ¿El perfil del que bloquea sigue siendo visible para el bloqueado? | Ocultarlo delata el bloqueo; mostrarlo lo hace parcial. **Hoy se ve**: nada lo esconde |
| B-4 | ¿Qué ocurre con los comentarios cruzados ya publicados? | Hilos con huecos |
| B-5 | ¿Hay límite de bloqueos? | Poco probable que haga falta |
| B-6 | ¿El bloqueo impide que el bloqueado solicite acceso a obras del bloqueador? | Se deduce de `RN-6`, pero conviene decirlo. **Hoy no lo impide**: la solicitud (`FEAT-RDG-002`) no consulta el bloqueo, aunque el acceso que se le conceda quedaría sin efecto para comentar |

## Estado

**Especificación:** `APPROVED` (2026-09-24). `B-2` y `B-3` resueltas: el bloqueo corta el acceso y las
correcciones en curso, y conserva las ya entregadas.

**Implementación:** `PARTIAL` (2026-09-24). `PUT` y `DELETE
/api/v1/users/{userId}/block`, y `GET /api/v1/me/blocked-users`. Una tabla nueva y su
migración, pero en `User`: la proyección de bloqueos.

### Lo que hace `Community`, y lo que deliberadamente no hace

Guarda el bloqueo, deshace los dos seguimientos —que son suyos— y publica el hecho. **Nada
más.** No revoca accesos de lector beta y no toca créditos, porque no sabe qué son: este
contexto conoce relaciones sociales. `RN-10` no es una preferencia de estilo, es lo que
permite que bloquear tenga consecuencias en tres contextos sin que ninguno dependa de los
otros.

Al deshacer los seguimientos publica un `AuthorUnsubscribed` por cada uno. Quien proyecta el
grafo se entera de lo que le importa —esa relación ya no está— **sin aprender que detrás había
un bloqueo**, que es información de otro orden.

### Dos caminos para «el bloqueado no puede comentar», y hacen falta los dos

| Dónde | Quién lo aplica | Cómo |
|---|---|---|
| Obras con acceso restringido | `Reading` | Revoca el acceso de lector beta del bloqueado a las obras del bloqueador (`RN-B1`) |
| Obras públicas | `User` | El techo de audiencia responde que no: **un bloqueo vence a cualquier ajuste de privacidad** |

Con solo el primero, bloquear no serviría de nada en las obras abiertas, que son la mayoría.
Con solo el segundo, el bloqueado seguiría **leyendo** obra inédita a la que tenía acceso, que
es lo que de verdad hay que cortar.

`RN-B2` —que una corrección en curso no se pueda entregar— no necesitó ninguna regla nueva:
sale de los dos anteriores. Sin acceso o sin audiencia, no se entrega.

### La proyección de bloqueos en `User`

La misma historia que la de seguidores, y por la misma regla: `CheckAuthorAudience` es un
contrato publicado, y un contrato no llama al de otro contexto mientras responde
([`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md)).

Guarda **el par ordenado y no la dirección**. Lo único que se pregunta aquí es si dos personas
se hablan, porque el efecto sobre los comentarios corta en los dos sentidos; guardar quién
bloqueó a quién invitaría a usarlo, y quien lo usara respondería distinto a cada lado de un
bloqueo que no tiene lados. Eso tiene un coste conocido y asumido: si dos personas se
bloquearon mutuamente, levantar uno de los dos deja la copia sin bloqueo aunque el otro siga
vivo. El caso es raro y dura hasta el siguiente hecho.

### La lista de bloqueados no se filtra por privacidad

Y es la única lista del sistema que no lo hace. Si se filtrara, bloquear a alguien que después
cierra su perfil lo haría desaparecer de ahí, y **el bloqueo sería irreversible en la
práctica**: no se puede deshacer lo que no se puede encontrar.

Por eso hay un contrato aparte —`ProfileCards`, sin filtro— en vez de relajar `VisibleProfiles`:
el peligroso tiene nombre propio y un solo uso legítimo, en lugar de un parámetro que alguien
acabaría pasando en una pantalla donde se descubre gente.

### La regla incómoda sigue siendo incómoda, pero ya no en silencio

Quien estuviera corrigiendo pierde ese trabajo y no cobra. Está probado, no disimulado, y se
asume a conciencia. Lo que la ficha pedía junto a eso —avisarle— lo cumple
[`FEAT-NOT-001`](../notification/FEAT-NOT-001-in-app-notifications.md) desde 2026-09-24.

**Y lo hace sin contar que ha habido un bloqueo**, que era la tensión entre esta ficha y
aquella. El aviso sale de `BetaReaderAccessRevoked`, el mismo hecho que publican los otros dos
caminos de revocación, y dice lo único que esa persona necesita saber para no seguir
escribiendo en balde: que ya no puede leer esa obra. `RN-2` —el bloqueo no se anuncia— sigue
intacto, porque el hecho no distingue el camino y el aviso tampoco.

### Qué queda fuera

Los mensajes directos (`FEAT-COM-011`) y el muro con sus comentarios y menciones
(`FEAT-COM-002`, `FEAT-COM-006`, `FEAT-COM-032`) no existen como código. Cuando existan, cada
uno tendrá que consultar el bloqueo — y la forma ya está: consumir el hecho y mantener su
propia copia, como han hecho `User` y `Reading`.

`B-1` sigue abierta: si el perfil del bloqueador se le oculta al bloqueado. Hoy se ve, porque
nada lo esconde.
