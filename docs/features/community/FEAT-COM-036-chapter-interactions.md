---
id: FEAT-COM-036
title: Interacciones sociales sobre un capítulo
context: Community
concept: Interaction
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - docs/ui/read-chapter.md
  - docs/features/README.md
  - conversation:2026-09-25
endpoints:
  - likeChapter
  - unlikeChapter
  - commentOnChapter
  - listChapterComments
  - getChapterEngagement
  - listChapterCommentReplies
  - deleteChapterComment
events:
  - ChapterCommented
depends_on: [FEAT-WRK-004, FEAT-COM-006, FEAT-USR-038]
updated: 2026-09-25
---

# FEAT-COM-036 — Interacciones sociales sobre un capítulo

## Resumen

La barra que va bajo el texto de un capítulo: **apoyar** y **comentar**, con sus respuestas y
sus cifras en la cabecera.

> ### Comentar un capítulo **no es** corregirlo
>
> Es la distinción más importante de esta pantalla y la que la documentación de este producto
> confundió durante meses.
>
> | | Comentario | Corrección |
> |---|---|---|
> | Dónde | Bajo el texto, abierto | Panel «Empezar corrección» |
> | Qué es | Reacción social, libre | Respuesta al cuestionario del autor |
> | Créditos | **Ninguno** | **Los mueve: cuesta al autor y recompensa al lector** |
> | Cuántas caben | Las que sean | **Una por lector y capítulo** |
> | Contexto | `Community` | `Feedback` |
> | Quién | Cualquiera que pueda leer | Quien tenga acceso de lector beta |
>
> Conviven en la misma pantalla y son dos cosas. Que `Credits` **no** esté entre los
> consumidores de `ChapterCommented` es la forma más clara de decirlo.

## `R-9`, resuelta: no son `PostComment`

La pregunta abierta era si los comentarios de un capítulo son `PostComment` o un tipo aparte.
**Son un tipo aparte**, y la razón no es de forma sino de autorización:

- un `PostComment` cuelga de una publicación y hereda **su audiencia**;
- un `ChapterComment` cuelga de un capítulo y hereda **la regla de lectura de la obra**, que
  vive en otro contexto y tiene cinco puertas.

Compartir tabla con un `postId` anulable habría dejado dos caminos de autorización dentro de
una entidad, que es la forma más rápida de que un día se aplique el que no toca. Se parecen en
la forma; no en lo que son.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| Usuario | Apoyar, comentar y responder | Puede **leer** el capítulo y el autor admite comentarios suyos |
| Usuario | Retirar su apoyo | Puede leer el capítulo |
| Usuario | Leer comentarios y cifras | Puede leer el capítulo |
| Autor del comentario | Retirarlo | Es suyo |

## Reglas de negocio

- `RN-1` **No se apoya ni se comenta lo que no se puede leer.** Quien no puede ver el capítulo
  recibe `404`, el mismo que si no existiera: un `403` sobre una obra inédita ya confirma que
  está ahí. Aplica a un borrador ajeno, a un capítulo oculto o bloqueado, a una obra para
  adultos sin edad y a una obra que exige ser lector beta.
- `RN-2` Apoyar es **idempotente en las dos direcciones**. No es elegancia: el botón se pulsa
  dos veces sin querer y el cliente reintenta cuando la red falla.
- `RN-3` El **techo de audiencia del perfil** (`FEAT-USR-038` `RN-2`) gobierna escribir, no
  leer. Quien cierra sus comentarios cierra también los de sus capítulos, y **no esconde la
  conversación que ya existe** ni la cifra de apoyos. Retirar un apoyo propio sigue siendo
  posible: un gesto que no se puede deshacer no era un gesto.
- `RN-4` **Los hilos son planos.** Responder a una respuesta cuelga del comentario raíz, nunca
  de la respuesta. El cliente responde a lo que tiene delante y el servidor lo cuelga donde
  toca.
- `RN-5` Un comentario es **texto plano**, entre 1 y 1.000 caracteres, el mismo cuerpo que en
  el muro. **Sin menciones**, a diferencia de aquellos: nombrar a alguien bajo un capítulo lo
  arrastraría a una obra que quizá no puede leer, y la regla de que mencionar no da acceso a
  nada obligaría a filtrar cada mención contra la visibilidad de la obra. Se deja fuera hasta
  que haya una pantalla que lo pida.
- `RN-6` Retirar un comentario raíz **se lleva sus respuestas**, y el contador baja por todo
  lo que se va. Lo contrario dejaría respuestas colgando de algo que ya no está.
- `RN-7` Comentar **avisa al autor de la obra** (`CHAPTER_COMMENT`); responder avisa además a
  quien escribió el comentario (`POST_REPLY`). Nadie se avisa a sí mismo.
- `RN-8` Las cifras son **del capítulo**, no de la obra (`R-1`), y se guardan en vez de
  contarse al leer: la cabecera las enseña en cada visita.
- `RN-9` El orden de los comentarios es **por fecha**, del más reciente al más antiguo. «Más
  relevantes», que es lo que enseña la maqueta, necesita la fórmula de relevancia que `CM-4`
  deja sin definir — la misma que tiene bloqueados los tres rankings y el orden del muro. Un
  orden inventado aquí sería un cuarto sitio del que desdecirse.

## Flujo principal

1. Quien lee abre el capítulo (`FEAT-WRK-004`, servido por `Work`).
2. Pide las cifras a `GET /chapters/{id}/engagement` y los comentarios a
   `GET /chapters/{id}/comments`.
3. Apoya, comenta o responde.
4. `Community` comprueba con `Work` que puede leer el capítulo y con `User` que el autor
   admite comentarios suyos.
5. Guarda, actualiza los contadores en la misma transacción y publica `ChapterCommented`.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| No puede leer el capítulo | Igual que si no existiera | `404 CHAPTER_NOT_FOUND` |
| El autor cerró sus comentarios | Se dice, porque ya puede ver el capítulo | `403 COMMENTS_NOT_ACCEPTED` |
| Responde a un comentario de otro capítulo | No se cuelga nada | `404 COMMENT_NOT_FOUND` |
| Cuerpo vacío o de más de 1.000 | Se dice: es lo único arreglable | `422 VALIDATION_FAILED` |
| Retira un comentario ajeno | Igual que si no existiera | `404 COMMENT_NOT_FOUND` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Apoyar | `PUT /api/v1/chapters/{chapterId}/like` | `likeChapter` |
| Retirar el apoyo | `DELETE /api/v1/chapters/{chapterId}/like` | `unlikeChapter` |
| Comentar o responder | `POST /api/v1/chapters/{chapterId}/comments` | `commentOnChapter` |
| Listar comentarios | `GET /api/v1/chapters/{chapterId}/comments` | `listChapterComments` |
| Cifras de la cabecera | `GET /api/v1/chapters/{chapterId}/engagement` | `getChapterEngagement` |
| Respuestas de un hilo | `GET /api/v1/chapter-comments/{commentId}/replies` | `listChapterCommentReplies` |
| Retirar el propio | `DELETE /api/v1/chapter-comments/{commentId}` | `deleteChapterComment` |

Las cifras van en **endpoint aparte** y no como dos campos más de `GET /chapters/{id}`: el
capítulo lo sirve `Work` y esto es de `Community`. Meterlas en la misma respuesta obligaría a
uno de los dos contextos a conocer al otro. El precio es una segunda llamada del cliente; el
precio de lo otro era la frontera.

Las rutas de comentario cuelgan de `/chapter-comments/` y no de `/comments/`, que ya es de los
del muro: son entidades distintas con audiencias distintas, y compartir prefijo invitaría a
creer que son la misma.

## El contrato nuevo de `Work`

`Community` no puede decidir por su cuenta quién lee un capítulo. La regla es la más peligrosa
del backend —esta plataforma custodia obra inédita— y reconstruirla aquí sería tenerla escrita
dos veces, con una de las dos quedándose atrás tarde o temprano.

`Work` publica `ReadableChapters`, que **devuelve el veredicto ya dado**. En eso se diferencia
de `CorrectionBriefs`, que entrega hechos y deja decidir a quien pregunta: aquella regla es
corta, esta tiene cinco puertas.

Los dos booleanos que hacen falta —si tiene edad, si es lector beta— **los trae quien
pregunta**, y eso es lo que mantiene la regla 4 de
[`decision:0015`](../../decisions/0015-work-and-reading-ask-each-other.md): si el contrato
preguntara por su cuenta a `User` y a `Reading`, estaría llamando a otros contratos mientras
responde, que es justo lo que separa un ciclo de referencias de uno de llamadas.

`workOf()` existe por una razón concreta: el acceso de lector beta se concede **por obra**, así
que sin saber en qué obra está el capítulo no se le puede preguntar a `Reading`. Revela solo
que un capítulo pertenece a una obra, y evita que `Community` guarde esa relación por su
cuenta — una copia así envejece, y con ella el control de acceso.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `ChapterCommented` | Alguien comenta o responde | `chapterId`, `workId`, `commentId`, `workAuthorId`, `commentAuthorId`, `parentAuthorId?`. **Sin el texto** |

**Consume** — ninguno.

## Efectos en créditos

**Ninguno, y es la regla.** `Credits` no consume `ChapterCommented`, y esa ausencia es
deliberada: lo que el sistema paga es el cuestionario respondido.

## Modelo de datos afectado

| Tabla | Para qué |
|---|---|
| `community_ctx.chapter_comment` | Los comentarios y sus respuestas, con borrado lógico |
| `community_ctx.chapter_like` | El par `(capítulo, persona)` como clave |
| `community_ctx.chapter_engagement` | Los contadores, para no contarlos al leer |

Sin claves ajenas a `work_ctx`: los capítulos son de otro contexto, y una copia que restringe a
su origen es una copia que lo bloquea.

## Fuera de alcance, y por qué

- **«Comparte»**, el tercer botón de la barra. Compartir fuera de la plataforma es una URL y no
  deja estado; y las métricas de la maqueta son tres —lecturas, apoyos, comentarios— y los
  compartidos no están entre ellas. Es `FEAT-COM-020` cuando haya algo que contar.
- **Lecturas.** Qué cuenta como «lectura» sigue sin definirse (`H-3`), y un contador que nadie
  sabe qué mide es peor que no tenerlo.
- **Apoyos sobre un comentario de capítulo.** En el muro son `FEAT-COM-030`; aquí no los pide
  ninguna pantalla.
- **Menciones**, por `RN-5`.

## Diseño (Figma)

[`docs/ui/read-chapter.md`](../../ui/read-chapter.md), requisitos 3 y 5.

## Criterios de aceptación

- [x] Apoyar es alternable e idempotente en las dos direcciones.
- [x] Las cifras son del capítulo y `likedByViewer` es de quien mira.
- [x] Comentar no mueve ningún crédito a ninguna de las dos partes.
- [x] Responder a una respuesta cuelga del comentario raíz.
- [x] La lista de primer nivel no mezcla respuestas y cuenta cuántas cuelgan.
- [x] No se puede apoyar, comentar ni leer comentarios de un capítulo que no se puede leer.
- [x] Un capítulo oculto solo lo comenta su autor.
- [x] Cerrar los comentarios del perfil impide los nuevos y no esconde los viejos.
- [x] Retirar un comentario raíz se lleva sus respuestas y el contador baja por todas.
- [x] Solo se retira el propio.
- [x] Comentar avisa al autor; responder avisa además a quien escribió el comentario.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| `CM-4` | La fórmula de relevancia | Sin ella no hay «Más relevantes»; afecta igual a rankings y muro |
| `H-3` | Qué cuenta como «lectura» | La tercera métrica de la cabecera |

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
