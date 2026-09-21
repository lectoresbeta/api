# Glosario y lenguaje ubicuo

Traducción oficial entre el vocabulario de producto (español) y los identificadores del
código (inglés). **Es normativo**: ningún documento ni clase debe usar un nombre distinto
para estos conceptos.

Si falta un término, se añade aquí antes de usarlo en una ficha o en el código.

---

## Conceptos de negocio

| Español (producto) | Inglés (código) | Definición |
|---|---|---|
| Obra | `Work` | Unidad literaria publicada por un autor. Puede ser un relato breve o una novela. Contiene uno o varios fragmentos. |
| Fragmento | `Chapter` | Parte de una obra. Una obra corta tiene un único fragmento; una novela, muchos. Es la unidad sobre la que se lee y se comenta. |
| Autor / Escritor | `Writer` | Usuario en su rol de creador de obras. |
| Lector | `Reader` | Usuario en su rol de consumidor de obras del catálogo. |
| Lector beta (LB) | `BetaReader` | Lector con acceso concedido a una obra para darle feedback estructurado. Es el rol central de la plataforma. |
| Acceso de lector beta | `BetaReaderAccess` | Vínculo entre un usuario y una obra que le autoriza a leerla y comentarla. |
| Solicitud de LB | `AccessRequest` | Petición de un usuario para convertirse en lector beta de una obra bajo solicitud. |
| Invitación de LB | `AccessInvitation` | Propuesta del autor a un usuario concreto para que sea lector beta de su obra. |
| Grupo de lectores beta | `BetaReaderGroup` | Conjunto de lectores beta gestionado por el autor para asignarlos a obras en bloque. |
| Compañero de escritura | `WritingBuddy` | Vínculo recíproco entre dos usuarios que intercambian feedback de forma continuada. |
| Comentario / Crítica | `Feedback` | Aportación crítica de un lector beta sobre un fragmento u obra. Genera créditos al autor del comentario y los consume al autor de la obra. |
| Respuesta a comentario | `FeedbackReply` | Contestación del autor de la obra a un comentario recibido. |
| Valoración del comentario | `FeedbackRating` | Marca del autor indicando que un comentario le ha resultado útil. Otorga créditos adicionales a quien comentó. |
| Valoración de obra | `WorkRating` | Puntuación que un lector beta otorga a una obra. Alimenta los rankings. |
| Cuestionario | `Questionnaire` | Conjunto de preguntas que el autor adjunta a una obra para dirigir el feedback. Tres preguntas sin coste; cada pregunta adicional encarece el comentario recibido. |
| Pregunta del cuestionario | `QuestionnaireQuestion` | Pregunta individual del cuestionario. |
| Respuesta del cuestionario | `QuestionnaireAnswer` | Respuesta de un lector beta a una pregunta del cuestionario. |
| Registro de autoría | `AuthorshipRecord` | Prueba de autoría: huella criptográfica del contenido más marca temporal, generada en momentos definidos del ciclo de vida de la obra. |
| Enlace público | `PublicLink` | URL que permite leer una obra y dejar un comentario sin iniciar sesión. |
| Muro principal | `Feed` | Espacio común donde los usuarios publican y descubren contenido de la comunidad. |
| Publicación | `Post` | Mensaje publicado en el muro principal. Tiene un tipo según su intención. |
| Comentario de publicación | `PostComment` | Comentario sobre una publicación del muro. No confundir con `Feedback`. |
| Reacción | `Reaction` | Respuesta emocional con emoji a una publicación. |
| Apoyo / Me gusta | `Like` | Apoyo simple a una publicación. Se distingue de `Reaction`. |
| Suscripción a autor | `AuthorSubscription` | Seguimiento de un autor para recibir avisos de sus publicaciones y obras nuevas. |
| Mensaje directo (MD) | `DirectMessage` | Mensaje privado entre dos usuarios. Requiere que el destinatario los tenga habilitados. |
| Ranking | `Ranking` | Clasificación de escritores, obras o lectores, filtrable por temática y periodo. |
| Crédito | `Credit` | Unidad de la economía interna que equilibra dar y recibir feedback. |
| Saldo de créditos | `CreditBalance` | Créditos disponibles de un usuario. |
| Movimiento de créditos | `CreditTransaction` | Registro inmutable de una variación del saldo, con su motivo y su origen. |
| Regla de créditos | `CreditRule` | Norma que traduce un hecho de negocio en una variación de créditos. |
| Clasificación del texto | `TextTier` | Nivel de extensión de un texto (micro cuento, relato corto, novela media…) que determina su coste y recompensa en créditos. |
| Invitación a la plataforma | `PlatformInvitation` | Invitación por email para que alguien se registre. Otorga créditos al invitador si el invitado participa. |
| Página de autor | `AuthorPage` | Perfil público personalizable de un escritor. |
| Preferencias literarias | `LiteraryPreferences` | Géneros y temáticas de interés declarados por el usuario. |
| Temática / Género | `Genre` | Clasificación temática de obras y de intereses de usuario. |
| Notificación | `Notification` | Aviso dirigido a un usuario, entregable in-app o por email. |

---

## Modalidades y enumerados

| Español | Inglés | Valores |
|---|---|---|
| Modalidad de acceso LB | `BetaReaderAccessMode` | `PUBLIC` (cualquiera se convierte en LB automáticamente), `ON_REQUEST` (requiere aprobación del autor), `PRIVATE` (solo por invitación del autor) |
| Visibilidad | `Visibility` | `VISIBLE`, `HIDDEN` — aplicable a obra y a fragmento |
| Tipo de publicación | `PostType` | `GENERAL`, `LOOKING_FOR_BETA_READERS`, `LOOKING_FOR_WRITING_BUDDY`, `OFFERING_AS_BETA_READER` |
| Estado de solicitud | `RequestStatus` | `PENDING`, `ACCEPTED`, `REJECTED`, `CANCELLED`, `EXPIRED` |
| Nivel de texto | `TextTier` | `MICRO_STORY`, `SHORT_STORY`, `BRIEF_TALE`, `MEDIUM_TALE`, `LONG_TALE`, `MICRO_NOVEL`, `SHORT_NOVEL`, `MEDIUM_NOVEL` |
| Motivo de movimiento de créditos | `CreditTransactionReason` | `ACCOUNT_CREATED`, `FEEDBACK_GIVEN`, `FEEDBACK_RATED_POSITIVELY`, `INVITED_USER_PARTICIPATED`, `FEEDBACK_RECEIVED`, `MANUAL_ADJUSTMENT` |

> Los valores concretos de `TextTier` se fijan en
> [`features/credits/`](features/credits/) y en la ficha del contexto
> [`bounded-contexts/credits.md`](bounded-contexts/credits.md). La correspondencia entre
> nombre en español del documento original y constante en inglés está en esa ficha.

---

## Términos de arquitectura

| Término | Significado en este proyecto |
|---|---|
| Bounded context | Frontera de negocio autónoma. Primer nivel bajo `src/`. Posee su modelo, su persistencia y sus casos de uso. |
| Concepto | Segundo nivel bajo `src/<Context>/`. Agrupa el modelo y los casos de uso de una capacidad concreta antes de dividir en capas. |
| Evento de dominio | Hecho relevante dentro de un contexto. No sale del contexto ni conoce el transporte. |
| Evento de integración | Contrato público asíncrono entre contextos. Viaja por Symfony Messenger sobre RabbitMQ. |
| Read model | Proyección de solo lectura optimizada para una consulta, posiblemente alimentada por eventos. |
| Anti-corruption layer | Capa de traducción que impide que el modelo de otro contexto o de un tercero contamine el propio. |

---

## Términos que **no** se deben usar

| No usar | Usar en su lugar | Motivo |
|---|---|---|
| `Book`, `Novel`, `Story` | `Work` | Una obra puede ser cualquiera de las tres cosas. |
| `Comment` a secas para la crítica de una obra | `Feedback` | `PostComment` y `Feedback` son conceptos distintos en contextos distintos. |
| `Review` | `Feedback` | Evita confusión con las revisiones de código y con reseñas públicas. |
| `Page`, `Section`, `Part` | `Chapter` | Un único nombre para la subdivisión de la obra. |
| `Points`, `Tokens`, `Coins` | `Credit` | El documento de producto habla de créditos. |
| `Helper`, `Manager`, `Utils` | Un nombre que describa la responsabilidad | Regla explícita de `AGENTS.md`. |
