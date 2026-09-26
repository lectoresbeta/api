---
id: FEAT-COM-019
title: Repostear una publicación
context: Community
concept: Interaction
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - conversation:2026-09-22 (muro de «Mi perfil» con reposts)
  - docs/ui/create-post.md
endpoints: [POST /posts/{postId}/repost, DELETE /posts/{postId}/repost]
events: [PostReposted]
depends_on: [FEAT-COM-002]
updated: 2026-09-25
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

## Un repost es una referencia, no una publicación aparte

**Decidido** (`C-3`). Es una fila que apunta al original y que puede llevar texto propio.

| Forma | Qué es |
|---|---|
| **Repost simple** | Una referencia al original, sin texto |
| **Repost citado** | La misma referencia, con texto propio |

Tres consecuencias prácticas:

1. **Los contadores y la conversación son los del original**, que es lo que enseña el diseño.
   No hay dos conversaciones que mezclar.
2. **No hay cadenas de reposts anidados** que mostrar ni que moderar: se repostea siempre el
   original.
3. **Si el original desaparece, el repost desaparece con él** (`RN-6`). Es lo que significa ser
   una referencia, y evita tener que diseñar cómo se pinta una rota.

## Reglas de negocio

- `RN-1` Un repost **referencia** la publicación original; no copia su contenido. Si el
  original se edita, el repost refleja el cambio.
- `RN-2` Un usuario no repostea dos veces la misma publicación. **Repetir la acción la
  deshace** (`C-12`, resuelta): es lo que hace la gente cuando se arrepiente, y un segundo
  envío que respondiera «ya estaba» dejaría a la interfaz sin forma de decirlo.
- `RN-3` **Sí se repostea una publicación propia** (`C-13`, resuelta): sirve para volver a
  sacar algo antiguo, que es para lo que se usa.
- `RN-4` **No se repostea lo que no se puede ver.** Si la audiencia del original lo excluye,
  la acción se rechaza.
- `RN-5` Repostear **no amplía la audiencia del original**: quien no podía verlo sigue sin
  poder. Es la regla que impide usar el repost para saltarse la privacidad.
- `RN-6` Si el original se elimina, el repost deja de mostrarse.
- `RN-7` Repostear exige la cuenta activada (`FEAT-USR-025`).
- `RN-8` Repostear no mueve créditos.
- `RN-9` Se puede deshacer. **Deshacer no comprueba la audiencia del original**, a diferencia
  de repostear: si no, un original restringido después dejaría un repost que su dueño no puede
  quitar.
- `RN-10` Una publicación aparece **una vez por página**, aunque llegue por dos caminos. Pasa
  en cuanto alguien repostea algo público que ya veías; ver la misma tarjeta dos veces seguidas
  parece un error de la plataforma, así que se queda la entrada más reciente.

`RN-5` es la más importante y la más fácil de romper: basta con servir el muro del que
repostea sin volver a comprobar la audiencia del original para publicar contenido restringido.

## Flujo principal

1. El usuario pulsa el icono de repost en una publicación.
2. El sistema comprueba que puede verla (`RN-4`).
3. Crea el repost.
4. La publicación aparece en su muro con el encabezado correspondiente.
5. Se publica `PostReposted`.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Ya la había reposteado | **Lo deshace** | `200` con `reposted: false` |
| La publicación no existe o no es visible para él | Se rechaza sin distinguir ambos casos | `404` |
| Publicación propia | Se repostea | `200` |
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

- [x] Repostear muestra la publicación en el muro con el encabezado de repost.
- [x] Se muestran el autor original, su antigüedad y su contenido.
- [x] Repostear dos veces no crea dos entradas: la segunda lo deshace.
- [x] **No se puede repostear una publicación cuya audiencia excluye al usuario.**
- [x] **Un repost no hace visible el original a quien no podía verlo.**
- [x] Editar el original cambia lo que muestra el repost.
- [x] Eliminar el original hace desaparecer el repost.
- [x] Deshacer el repost lo retira del muro.
- [x] Deshacerlo funciona aunque el original haya dejado de ser visible.
- [x] Un repost puede llevar texto propio.
- [x] El repost entra en el muro por **su** fecha, no por la del original.
- [x] Repostear no mueve créditos.
- [x] Con la cuenta sin activar devuelve `403`.
- [x] El muro sirve el original embebido, sin una petición adicional por entrada.

Los dos criterios en negrita son los que hay que probar de verdad. El resto son mecánica.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~C-3~~ | ¿Puntero o entidad propia? | Resuelta: referencia con texto propio |
| ~~C-4~~ | ¿Se puede repostear con comentario propio? | Resuelta: sí |
| ~~C-12~~ | ¿Volver a pulsar repostea de nuevo o lo deshace? | Resuelta: lo deshace |
| ~~C-13~~ | ¿Se puede repostear una publicación propia? | Resuelta: sí |
| ~~C-14~~ | ¿Se puede repostear un repost? | Resuelta: se repostea siempre el original |
| ~~C-15~~ | ¿Qué audiencia tiene lo reposteado? | Resuelta: la del original, que no se amplía |

## Estado

**Especificación:** `APPROVED` (2026-09-25). `C-3` resuelta: el repost es una **referencia** al
original que admite texto añadido, no una publicación aparte.

La ficha llegó a la implementación diciendo las dos cosas a la vez —«entidad propia» arriba y
«propuesta: puntero» más abajo— con dos reglas que se contradecían (`RN-R2` decía que el
repost sobrevive al borrado del original y `RN-6` que desaparece). Se ha resuelto por la
referencia, que es lo que enseña el diseño —contadores en la tarjeta del original— y lo que
evita cadenas anidadas; las secciones contradictorias se han retirado.

**Implementación:** `DONE` (2026-09-25).

Una regla que la ficha no preveía y que la prueba encontró: **una publicación aparece una vez
por página aunque llegue por dos caminos** (`RN-10`). Pasa en cuanto alguien repostea algo
público que ya estaba en tu muro por sí mismo, y ver la misma tarjeta dos veces seguidas parece
un error de la plataforma.

El muro mezcla las dos consultas en memoria, no en SQL: cada una llega ordenada y con una fila
de más, así que los primeros `n` de la mezcla son los primeros `n` de verdad. Una unión en la
base de datos sería más rápida y bastante menos legible, por una diferencia que este muro no va
a notar.
