---
id: FEAT-COM-034
title: Bloquear a un usuario
context: Community
concept: Relationship
actors: [User]
spec_status: APPROVED
impl_status: TODO
priority: P2
sources:
  - conversation:2026-09-22 (menú «···» del perfil ajeno)
  - docs/ui/user-profile.md
endpoints: [PUT /users/{userId}/block, DELETE /users/{userId}/block]
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

- [ ] Bloquear deshace las dos relaciones de seguimiento.
- [ ] El bloqueado no recibe ningún aviso.
- [ ] Ninguno puede seguir al otro mientras dure el bloqueo.
- [ ] Los mensajes directos entre ambos se cortan.
- [ ] El bloqueado no puede comentar ni mencionar al que bloquea.
- [ ] Desbloquear no restaura los seguimientos.
- [ ] No se puede uno bloquear a sí mismo.
- [ ] El usuario puede consultar a quién tiene bloqueado.
- [ ] `Community` no revoca accesos de lector beta ni toca créditos: publica el evento.
- [ ] Con la cuenta sin activar devuelve `403`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~B-2~~ | ¿Bloquear revoca el acceso de lector beta, y puede terminar quien ya estaba corrigiendo? | Cortarlo destruiría trabajo real de un tercero |
| ~~B-3~~ | ¿Qué pasa con el feedback que el bloqueado ya dejó, y que el autor **ya pagó**? | Borrarlo le hace perder lo comprado; conservarlo contradice el bloqueo |
| B-1 | ¿El perfil del que bloquea sigue siendo visible para el bloqueado? | Ocultarlo delata el bloqueo; mostrarlo lo hace parcial |
| B-4 | ¿Qué ocurre con los comentarios cruzados ya publicados? | Hilos con huecos |
| B-5 | ¿Hay límite de bloqueos? | Poco probable que haga falta |
| B-6 | ¿El bloqueo impide que el bloqueado solicite acceso a obras del bloqueador? | Se deduce de `RN-6`, pero conviene decirlo |

## Estado

**Especificación:** `APPROVED` (2026-09-24). `B-2` y `B-3` resueltas: el bloqueo corta el acceso y las
correcciones en curso, y conserva las ya entregadas.

**Implementación:** `TODO`.
