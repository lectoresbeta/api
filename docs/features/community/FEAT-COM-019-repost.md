---
id: FEAT-COM-019
title: Repostear una publicación
context: Community
concept: Interaction
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P2
sources:
  - conversation:2026-09-22 (muro de «Mi perfil» con reposts)
  - docs/ui/create-post.md
endpoints: [POST /posts/{postId}/repost, DELETE /posts/{postId}/repost]
events: [PostReposted]
depends_on: [FEAT-COM-002]
updated: 2026-09-22
---

# FEAT-COM-019 — Repostear una publicación

## Resumen

Republicar la publicación de otra persona en el propio muro. Aparece con un encabezado
discreto —«BEATRIZ ALONSO REPOSTEÓ»— seguido de la publicación original completa: su autor,
su antigüedad, su contenido y sus contadores.

No es una cita: **quien repostea no añade texto propio**.

## Cómo se ve

```text
⟳ BEATRIZ ALONSO REPOSTEÓ
┌──────────────────────────────────────┐
│ (avatar) Pedro Martínez · 3 días  ···│
│ texto original                       │
│ [imagen, tarjeta de obra o enlace]   │
│ ♡ 999  💬 999  ⟳ 999  ↗ 999          │
└──────────────────────────────────────┘
```

## Reglas de negocio

- `RN-1` Un repost **referencia** la publicación original; no copia su contenido. Si el
  original se edita, el repost refleja el cambio.
- `RN-2` Un usuario no repostea dos veces la misma publicación. Repetir la acción es
  idempotente, o la deshace; ver `C-12`.
- `RN-3` No se repostea una publicación propia. *(Por confirmar, `C-13`.)*
- `RN-4` **No se repostea lo que no se puede ver.** Si la audiencia del original lo excluye,
  la acción se rechaza.
- `RN-5` Repostear **no amplía la audiencia del original**: quien no podía verlo sigue sin
  poder. Es la regla que impide usar el repost para saltarse la privacidad.
- `RN-6` Si el original se elimina, el repost deja de mostrarse.
- `RN-7` Repostear exige la cuenta activada (`FEAT-USR-025`).
- `RN-8` Repostear no mueve créditos.
- `RN-9` Se puede deshacer.

`RN-5` es la más importante y la más fácil de romper: basta con servir el muro del que
repostea sin volver a comprobar la audiencia del original para publicar contenido restringido.

## El modelo: puntero o entidad

Es la decisión de fondo, y el diseño no la resuelve.

| | A. El repost es un puntero | B. El repost es una publicación propia |
|---|---|---|
| Qué se guarda | `(userId, postId)` | Una publicación que referencia a otra |
| Contadores | Son los del original | El repost tiene los suyos |
| ¿Se puede comentar el repost? | No: se comenta el original | Sí, por separado |
| ¿Se puede repostear un repost? | Se resuelve al original | Podría anidarse |
| Complejidad | Baja | Alta |

El diseño muestra **los contadores en la tarjeta del original**, lo que encaja con la opción
A: un puntero. Es también la más simple y la que evita cadenas de reposts anidados.

**Propuesta: opción A.** Pendiente de confirmar (`C-3`).

## Flujo principal

1. El usuario pulsa el icono de repost en una publicación.
2. El sistema comprueba que puede verla (`RN-4`).
3. Crea el repost.
4. La publicación aparece en su muro con el encabezado correspondiente.
5. Se publica `PostReposted`.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Ya la había reposteado | Idempotente, o lo deshace (`C-12`) | `200` |
| La publicación no existe o no es visible para él | Se rechaza sin distinguir ambos casos | `404` |
| Publicación propia | Sin confirmar (`C-13`) | Pendiente |
| Cuenta sin activar | Se rechaza | `403` con `code: ACCOUNT_NOT_ACTIVATED` |

Que un original invisible y uno inexistente devuelvan lo mismo es deliberado: distinguirlos
revelaría que existe una publicación que ese usuario no debería conocer.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Repostear | `POST /posts/{postId}/repost` | `repostPost` |
| Deshacer | `DELETE /posts/{postId}/repost` | `undoRepost` |

En el muro, cada entrada indica si es un repost, quién lo hizo y la publicación original
completa. El cliente no debe tener que pedir el original por separado.

## Eventos

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|
| `PostReposted` | Se repostea | `Notification` (avisa al autor original) | `postId`, `originalAuthorId`, `repostedBy` |

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `post_repost` | `post_id`, `user_id`, `created_at`, con clave única sobre el par |

Índice sobre `post_repost(user_id, created_at)` para componer el muro propio.

## Criterios de aceptación

- [ ] Repostear muestra la publicación en el muro con el encabezado de repost.
- [ ] Se muestran el autor original, su antigüedad y su contenido.
- [ ] Repostear dos veces no crea dos entradas.
- [ ] **No se puede repostear una publicación cuya audiencia excluye al usuario.**
- [ ] **Un repost no hace visible el original a quien no podía verlo.**
- [ ] Editar el original cambia lo que muestra el repost.
- [ ] Eliminar el original hace desaparecer el repost.
- [ ] Deshacer el repost lo retira del muro.
- [ ] Repostear no mueve créditos.
- [ ] Con la cuenta sin activar devuelve `403`.
- [ ] El muro sirve el original embebido, sin una petición adicional por entrada.

Los dos criterios en negrita son los que hay que probar de verdad. El resto son mecánica.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **C-3** | ¿El repost es un puntero o una publicación con entidad propia? | Define contadores, comentarios y anidamiento |
| C-4 | ¿Se puede repostear añadiendo comentario propio? | El diseño no lo muestra; cambiaría el modelo |
| C-12 | ¿Volver a pulsar repostea de nuevo o lo deshace? | El icono no muestra estado activo en el diseño |
| C-13 | ¿Se puede repostear una publicación propia? | Habitual en otras plataformas |
| C-14 | ¿Se puede repostear un repost? | Con la opción A se resolvería al original |
| C-15 | ¿Qué audiencia tiene lo reposteado en el muro de quien repostea? | Relacionado con `RN-5` y con `C-1` |

## Estado

**Especificación:** `DRAFT`. El comportamiento visible está claro; falta `C-3`, que decide el
modelo.

**Implementación:** `TODO`.
