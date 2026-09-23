# Bounded context: `Feedback`

> Estado: `DRAFT` — Prefijo: `FBK`

## Responsabilidad

La crítica sobre las obras: quién la deja, sobre qué, con qué contenido, cómo responde el
autor y cómo la valora. Es el productor de los hechos que mueven la economía de créditos.

> **La interfaz llama «corrección» a lo que aquí es `Feedback`**: «Mis correcciones»,
> «corregir una obra», «poner tu obra en corrección». Es el mismo concepto visto desde quien
> lo aporta. El identificador en código sigue siendo `Feedback`.

> **Precisión importante (2026-09-22).** Lo que este contexto posee es la **corrección**: el
> cuestionario del autor respondido y enviado. **Los comentarios libres bajo un capítulo no
> son suyos**, son interacción social de `Community` (`FEAT-COM-036`) y no mueven créditos.
> Ambos conviven en la misma pantalla, lo que los hacía fáciles de confundir. Ver
> [`../ui/read-chapter.md`](../ui/read-chapter.md).

## Qué posee

- Correcciones: el cuestionario de la obra respondido por un lector beta.
- Borradores de corrección, privados de quien los escribe (`FEAT-FBK-011`).
- La evaluación antifraude de cada corrección (`FEAT-FBK-012`), como **señal**: quien mueve
  los créditos sigue siendo `Credits`.
- Respuestas al cuestionario de la obra.
- Respuestas del autor a los comentarios.
- Valoración del comentario por parte del autor (útil / no útil).
- Ocultación de comentarios.
- Valoración de la obra por parte del lector beta.
- El listado de correcciones que ha hecho cada usuario (`FEAT-FBK-010`) y su contador.

## Qué NO posee

| No es suyo | Es de |
|---|---|
| El contenido de la obra | `Work` |
| Las preguntas del cuestionario (posee las **respuestas**) | `Work` |
| El derecho a comentar (lo **consume** como hecho) | `Reading` |
| El importe en créditos de un comentario | `Credits` |
| Los comentarios de las publicaciones del muro | `Community` |
| **Los comentarios libres bajo un capítulo** | `Community` (`FEAT-COM-036`) |

## Conceptos

| Concepto | Responsabilidad |
|---|---|
| `Correction` | La corrección: borrador, envío e inmutabilidad posterior |
| `Feedback` | El comentario crítico y su ciclo de vida |
| `QuestionnaireAnswer` | Respuestas a las preguntas del autor |
| `Reply` | Respuesta del autor al comentario |
| `Rating` | Valoración del comentario por el autor y valoración de la obra por el lector |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `Feedback` | `FeedbackId` | Lo deja quien tiene acceso vigente, o quien usa un enlace público. Contiene sus respuestas al cuestionario, la respuesta del autor y su valoración. |
| `WorkRating` | `WorkRatingId` | Una valoración por lector beta y obra. |
| `Correction` | `CorrectionId` | **Una por lector y capítulo** (índice único `(chapterId, readerId)`). Responde a una versión concreta del cuestionario. En `DRAFT` es privada; en `SUBMITTED` es inmutable, porque el autor ya ha pagado por ella. |

### Value objects y enums

| Nombre | Reglas |
|---|---|
| `FeedbackContent` | Texto; longitud mínima por definir (`F-2`) |
| `FeedbackVisibility` | `VISIBLE`, `HIDDEN_BY_AUTHOR` |
| `FeedbackOrigin` | `BETA_READER`, `PUBLIC_LINK` |
| `CorrectionStatus` | `DRAFT`, `SUBMITTED` |
| `AnswerText` | Longitud mínima y máxima **en palabras**, fijadas por la pregunta |
| `RatingValue` | Escala por definir (`F-3`) |

## Eventos publicados

| Evento | Cuándo | Consumidores |
|---|---|---|
| `CorrectionStarted` | Un lector pulsa «Empezar corrección» | **`Credits`** (retiene el precio del capítulo) |
| `FeedbackSubmitted` | Se **entrega** una corrección | **`Credits`**, `Notification`, `Community` (rankings) |
| `CorrectionDraftDiscarded` | El lector descarta su borrador | **`Credits`** (libera la retención) |
| `CorrectionTipped` | El autor propina una corrección | **`Credits`**, `Community` (reputación) |
| `PublicCorrectionSubmitted` | Corrección por enlace público | `Notification`. **`Credits` no lo consume**: está fuera de la economía |
| `FeedbackRatedPositively` | El autor valora la corrección como útil | `Notification`, `Community`. **Ya no mueve créditos**: la bonificación automática se sustituyó por la propina |
| `FeedbackReplied` | El autor contesta | `Notification` |
| `FeedbackHidden` | El autor oculta un comentario | `Community` (rankings), posiblemente `Credits` (`C-9`) |
| `WorkRated` | Un LB valora la obra | `Community` (rankings de obras y escritores) |

`FeedbackSubmitted` es el evento más importante del sistema: dispara a la vez el abono al
comentarista y la **confirmación de la retención** del autor.

Lleva `betaReaderAccessId` porque `Credits` necesita localizar la retención que se creó al
conceder ese acceso ([`decision:0004`](../decisions/0004-credit-reservation-on-access-grant.md)).

Payload propuesto (pendiente de cerrar con `C-10`):

```json
{
  "feedbackId": "...",
  "workId": "...",
  "chapterId": "...",
  "authorId": "...",
  "reviewerId": "...",
  "textTier": "SHORT_STORY",
  "questionCount": 5,
  "origin": "BETA_READER"
}
```

**El contenido del comentario nunca viaja en el evento.**

## Eventos consumidos

| Evento | Origen | Efecto |
|---|---|---|
| `CreditsHeld` | **`Credits`** | Se abre el panel: hay respaldo para pagar esta corrección |
| `CreditHoldRejected` | **`Credits`** | **No se abre el panel.** Se explica por qué |
| `CreditHoldReleased` | `Credits` | La retención caducó; para entregar hay que volver a retener |
| `OverdraftCorrectionGranted` | `Credits` | La corrección se marca como **bloqueada** para el autor |
| `CorrectionUnlocked` | `Credits` | El autor repuso saldo: se desbloquea el contenido |
| `WorkClosedForCorrection` | `Work` | Los borradores en curso dejan de poder enviarse (`Q-5`) |

`CreditHoldRejected` es el único evento que **impide** a este contexto dejar trabajar a un
usuario, y por eso hay que esperarlo antes de abrir el panel de corrección. Es el punto donde
la asincronía tiene coste visible (`C-18`).

**Quién decide qué se ve es `Feedback`, no `Credits`.** En el descubierto, `Credits` publica
el hecho económico; ocultar o enseñar el texto de una corrección es decisión de quien la
posee.

## Reglas de negocio

- `RN-1` Solo puede comentar quien tiene acceso vigente o un enlace público válido.
- `RN-2` El autor de la obra no puede dejarse feedback a sí mismo.
- `RN-3` Un comentario, una vez enviado, no se elimina: se oculta.
- `RN-4` Solo el autor de la obra valora, contesta y oculta comentarios.
- `RN-5` La valoración positiva de un comentario se aplica **una sola vez**; retirarla no
  revierte los créditos (salvo decisión contraria en `C-9`).
- `RN-6` Ocultar un comentario no lo oculta para quien lo escribió.
- `RN-7` `Feedback` **no comprueba el saldo del autor**. Que exista un acceso vigente ya
  implica que sus créditos están retenidos (`decision:0004`). Consultar el saldo aquí sería
  la validación síncrona que esa decisión descartó.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| F-1 | ¿El feedback se ancla al fragmento, a la obra, o a una posición del texto? (`D-1`) | Define la raíz del agregado y el cálculo de créditos |
| F-2 | ¿Hay longitud mínima para que un comentario genere créditos? | Protección frente a comentarios vacíos que farmean créditos |
| F-3 | ¿Qué escala usa la valoración de obra: 1–5, 1–10, positiva/negativa? | Rankings |
| F-4 | ¿Un LB puede dejar varios comentarios en la misma obra? (`D-2`, `C-4`) | **Gana importancia con la reserva previa**: una retención cubre un comentario, así que el segundo se quedaría sin respaldo |
| F-5 | ¿La valoración del comentario es binaria (útil / no útil) o graduada? | El documento habla de "valoración positiva" |
| F-6 | ¿El comentario desde enlace público genera créditos si no hay cuenta? (`A-3`, `C-5`) | Bloquea `FEAT-FBK-008` |
| F-7 | ¿Se puede editar un comentario ya enviado? | Afecta a la irreversibilidad de los créditos |
| F-8 | ¿Hay mecanismo de denuncia de comentarios abusivos? (`J-9`) | Requiere moderación |
| F-9 | ¿Qué muestra «Mis correcciones»: el texto completo, o un resumen con enlace a la obra? | El contenido del feedback es privado entre autor y corrector |
| F-10 | ¿El contador «Correcciones» del perfil es público? | Es un indicador de reputación; probablemente sí |
