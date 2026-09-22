---
id: FEAT-COM-034
title: Bloquear a un usuario
context: Community
concept: Relationship
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P2
sources:
  - conversation:2026-09-22 (menú «···» del perfil ajeno)
  - docs/ui/user-profile.md
endpoints: [PUT /users/{userId}/block, DELETE /users/{userId}/block]
events: [UserBlocked, UserUnblocked]
depends_on: [FEAT-COM-010]
updated: 2026-09-22
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
| El bloqueado es **lector beta** de una obra del que bloquea | ¿Se le revoca el acceso? ¿Se libera la retención de créditos? | `B-2` |
| El bloqueado ya dejó **feedback** en una obra del que bloquea | ¿Se oculta? ¿Se conserva? El autor ya lo pagó | `B-3` |
| Hay una **retención de créditos** viva por ese acceso | ¿Se libera, se confirma o se queda colgada? | `B-2` |
| Hay comentarios y respuestas cruzados en el muro | ¿Desaparecen o se conservan sin enlace? | `B-4` |

`B-3` es el más delicado: el autor **ya pagó créditos** por ese feedback. Borrarlo le haría
perder lo que compró; conservarlo visible contradice el bloqueo. Una salida razonable es
conservarlo pero anonimizar al autor de cara al bloqueador, aunque eso también tiene aristas.

**No se resuelve aquí.** Son decisiones de producto con consecuencias económicas.

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
accesos a obras ni retenciones. Publica el hecho y cada contexto decide.

## Cómo se aplica sin acoplar contextos

```text
Community: UserBlocked
     │
     ├──▶ Reading      ¿revoca el acceso de lector beta?        (B-2)
     ├──▶ Feedback     ¿oculta el feedback cruzado?             (B-3)
     ├──▶ Credits      ¿libera la retención asociada?           (B-2)
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
| **B-2** | ¿Bloquear revoca el acceso de lector beta y libera su retención de créditos? | **Económico.** Afecta a `Reading` y a `Credits` |
| **B-3** | ¿Qué pasa con el feedback que el bloqueado ya dejó, y que el autor **ya pagó**? | Borrarlo le hace perder lo comprado; conservarlo contradice el bloqueo |
| B-1 | ¿El perfil del que bloquea sigue siendo visible para el bloqueado? | Ocultarlo delata el bloqueo; mostrarlo lo hace parcial |
| B-4 | ¿Qué ocurre con los comentarios cruzados ya publicados? | Hilos con huecos |
| B-5 | ¿Hay límite de bloqueos? | Poco probable que haga falta |
| B-6 | ¿El bloqueo impide que el bloqueado solicite acceso a obras del bloqueador? | Se deduce de `RN-6`, pero conviene decirlo |

## Estado

**Especificación:** `DRAFT`. El comportamiento social está claro. Para llegar a `APPROVED`
hacen falta `B-2` y `B-3`, que tienen consecuencias sobre los créditos y sobre trabajo ya
pagado.

**Implementación:** `TODO`.
