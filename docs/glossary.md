# Glosario y lenguaje ubicuo

Traducción oficial entre el vocabulario de producto (español) y los identificadores del
código (inglés). **Es normativo**: ningún documento ni clase debe usar un nombre distinto
para estos conceptos.

Si falta un término, se añade aquí antes de usarlo en una ficha o en el código.

---

## Conceptos de negocio

| Español (producto) | Inglés (código) | Definición |
|---|---|---|
| Obra / **Relato** | `Work` | Unidad literaria inédita publicada por un autor en la plataforma. Puede ser un relato breve o una novela. Contiene uno o varios fragmentos. **La interfaz la llama «relato»** («Mis relatos», «Explorar más relatos»). |
| Fragmento / **Parte** / Capítulo | `Chapter` | Parte de una obra. Una obra corta tiene un único fragmento; una novela, muchos. Es la unidad sobre la que se lee y se comenta. **La interfaz lo llama «Partes»** («1/3 Partes» en la tarjeta de obra) y «capítulo» en la pantalla de lectura («Siguiente capítulo»). El identificador sigue siendo `Chapter`. |
| Autor / Escritor | `Writer` | Usuario en su rol de creador de obras. |
| Lector | `Reader` | Usuario en su rol de consumidor de obras del catálogo. |
| Lector beta (LB) | `BetaReader` | Lector con acceso concedido a una obra para darle feedback estructurado. Es el rol central de la plataforma. |
| Acceso de lector beta | `BetaReaderAccess` | Vínculo entre un usuario y una obra que le autoriza a leerla y comentarla. |
| Solicitud de LB | `AccessRequest` | Petición de un usuario para convertirse en lector beta de una obra bajo solicitud. |
| Invitación de LB | `AccessInvitation` | Propuesta del autor a un usuario concreto para que sea lector beta de su obra. |
| Grupo de lectores beta | `BetaReaderGroup` | Conjunto de lectores beta gestionado por el autor para asignarlos a obras en bloque. |
| Compañero de escritura | `WritingBuddy` | Vínculo recíproco entre dos usuarios que intercambian feedback de forma continuada. |
| **Corrección** / Crítica | `Correction` (agregado de `Feedback`) | **El cuestionario del autor respondido y enviado por un lector beta, sobre un capítulo concreto.** Es lo que mueve los créditos: los consume al autor y los abona al lector. Una por lector y capítulo. La interfaz la llama «corrección» («Mis correcciones», «Empezar corrección»). |
| Comentario de un capítulo | `ChapterComment` | Reacción social libre bajo el texto de un capítulo, con respuestas y menciones. **No es una corrección**: no cuesta ni recompensa créditos y pertenece a `Community`, no a `Feedback`. |
| Borrador de corrección | `Correction` con `status: DRAFT` | Corrección empezada y no enviada. Privada del lector; el autor no sabe que existe. No mueve créditos. |
| Precio de una corrección | `CorrectionPrice` | `techo(palabras del capítulo / 1.000) + techo(palabras exigidas / 100)`, entre 2 y 20. Es a la vez lo que paga el autor y lo que cobra el lector. |
| Palabras exigidas | `requiredWords` | Suma de los mínimos de palabras que el autor fija en las preguntas de su cuestionario. Un solo número que captura toda su exigencia, y el segundo término del precio. |
| Propina | `CorrectionTip` | Créditos extra que el autor da **de su propio saldo** a una corrección que le ha servido. Es una transferencia, así que no altera la masa y es inmune a la colusión. |
| Saldo negativo / descubierto | `negativeBalance` | El corrector cobra siempre; si el autor no llega, queda en negativo. Con saldo negativo no se abren correcciones nuevas, pero **sí se pueden dar**: es como se sale. |
| Corrección en descubierto | `OverdraftCorrection` | Corrección concedida a un autor sin saldo como gancho de reactivación, dentro de un **cupo periódico**. Se comporta como cualquier corrección bloqueada. |
| Cupo de descubierto | `OverdraftQuota` | Número de correcciones en descubierto que la plataforma concede por periodo. Es el presupuesto de emisión del gancho de reactivación. |
| Reclamación / Denuncia | `Claim` | Señalamiento de contenido ajeno para que un moderador lo revise: una obra inapropiada, una corrección que no aporta valor, un usuario abusivo. **No produce ningún efecto hasta que se resuelve.** |
| Moderador | `Moderator` | Usuario con acceso al backoffice para resolver reclamaciones. Es un usuario normal en todo lo demás, de ahí la regla de conflicto de interés. |
| Administrador | `Admin` | Rol por encima del moderador: concede el rol, gestiona usuarios y ordena ajustes de créditos. |
| Sanción | `Sanction` | Medida impuesta a un usuario tras una reclamación estimada, con tipo, alcance, motivo y vigencia. |
| Obra o capítulo bloqueado | `BLOCKED` | Deshabilitado por una reclamación estimada. Sigue siendo visible **solo para su autor**, marcado como tal. Indefinido: solo lo revoca un moderador. **3 capítulos bloqueados bloquean la obra entera.** |
| Clasificación de contenido | `ContentRating` | Etiquetas con las que el autor declara qué material sensible contiene su obra: `SEXUAL_CONTENT`, `GRAPHIC_VIOLENCE`, `SELF_HARM`, `SUBSTANCE_USE`, `STRONG_LANGUAGE`, más el indicador `ADULTS_ONLY`. Etiquetar bien **protege** frente a reclamaciones; etiquetar mal es reclamable. |
| Contenido de una obra | `content_html` + `content_text` | HTML saneado con lista blanca corta —`p`, `strong`, `em`, `blockquote`, `h2`, `h3`, `hr`, `br`— **sin enlaces ni imágenes**, más el texto plano derivado, que es la fuente de verdad para el recuento de palabras y, por tanto, para el precio. |
| Relevancia del catálogo | — | `capacidad × desatención × frescura` ([`decision:0008`](decisions/0008-catalogue-ordering.md)). **No es popularidad**: reparte trabajo entre autores en lugar de concentrarlo. |
| Audiencia de una publicación | `PostAudience` | `EVERYONE` o `FOLLOWERS`. Se fija al publicar y **no cambia después**. |
| Repost | `Post` con `repostOf` | Publicación **con entidad propia** que referencia al original, con o sin texto añadido. Nunca copia el contenido y **no puede ampliar la audiencia** del original. |
| Visibilidad de una obra | `Visibility` | `VISIBLE` u `HIDDEN`. **Eje distinto de `WorkStatus`**: el estado dice en qué punto de su vida está la obra; la visibilidad, si se muestra ahora. |
| Alcance de una pregunta | `QuestionScope` | `EVERY_CHAPTER` o `LAST_CHAPTER`. Evita que el autor pague por preguntas sin respuesta posible en un capítulo intermedio. |
| Sesión | `AccessToken` + `RefreshToken` | JWT de 15 minutos más un token de refresco revocable ([`decision:0007`](decisions/0007-jwt-sessions.md)). **La revocación tarda hasta 15 minutos**, salvo en operaciones de escritura, que comprueban el estado de la cuenta. |
| Revisión automática | `ContentReviewer` | Servicio que aprueba o marca un texto antes de publicarse. Hoy aprueba todo; es un puerto preparado para una implementación con IA. |
| Hilo de reclamación | `ClaimThread` | Conversación privada entre el moderador y **una** de las partes. Las partes no se ven entre sí. |
| Registro de auditoría | `AuditLog` | Registro inmutable de toda acción administrativa, **incluidas las consultas**. |
| Enlace público de corrección | `PublicCorrectionLink` | URL que el autor reparte fuera de la plataforma para que alguien corrija sin registrarse. **No cuesta créditos ni los da.** |
| Biografía / Descripción | `bio` | Texto de presentación del usuario, **300 caracteres**. Es el mismo campo que se ve bajo la foto en «Mi perfil» y que se edita en Configuración. |
| Cuenta anonimizada | `UserStatus: DELETED` | Cuenta eliminada **por su titular**: se suprimió todo dato personal y se conserva, sin autor identificable, lo que pertenece a terceros. Irreversible. |
| Cuenta bloqueada | `UserStatus: BLOCKED` | Cuenta **expulsada** por un moderador. **No se anonimiza**: sus datos se conservan para impedir volver a registrarse con el mismo correo. Revocable. |
| Suspensión parcial | `SanctionType: PARTIAL_SUSPENSION` | El usuario entra y lee, pero no publica, comenta ni corrige. De 3 días, 1 semana o 1 mes. |
| Mensaje operativo | `OperationalMessage` | Correo que responde a algo que el usuario acaba de pedir o que afecta a la seguridad de su cuenta: activación, restablecimiento de contraseña, cambio de correo. **No es una notificación** y no se puede desactivar. |
| Techo de privacidad | — | El ajuste global «quién puede comentar mis textos» fija el **máximo**; cada obra puede ser más restrictiva, nunca más permisiva. |
| Respuesta a comentario | `FeedbackReply` | Contestación del autor de la obra a un comentario recibido. |
| Valoración del comentario | `FeedbackRating` | Marca del autor indicando que un comentario le ha resultado útil. Otorga créditos adicionales a quien comentó. |
| Valoración de obra | `WorkRating` | Puntuación que un lector beta otorga a una obra. Alimenta los rankings. |
| Cuestionario | `Questionnaire` | Conjunto de preguntas que el autor adjunta a una obra para dirigir el feedback. Puede ir desde un único campo de texto libre hasta varias preguntas concretas. **Su configuración y la longitud del capítulo determinan lo que paga el autor y lo que gana el lector** (`FEAT-CRD-016`); la fórmula se define más adelante. Se versiona: una corrección responde siempre a la versión con la que empezó. Las longitudes de respuesta se miden **en palabras**. |
| Pregunta del cuestionario | `QuestionnaireQuestion` | Pregunta individual del cuestionario. |
| Respuesta del cuestionario | `QuestionnaireAnswer` | Respuesta de un lector beta a una pregunta del cuestionario. |
| Registro de autoría | `AuthorshipRecord` | Prueba de autoría: huella criptográfica del contenido más marca temporal, generada en momentos definidos del ciclo de vida de la obra. |
| Enlace público | `PublicLink` | URL que permite leer una obra y dejar un comentario sin iniciar sesión. |
| Muro principal | `Feed` | Espacio común donde los usuarios publican y descubren contenido de la comunidad. |
| Publicación | `Post` | Mensaje publicado en el muro principal. Tiene un tipo según su intención. |
| Comentario de publicación | `PostComment` | Comentario sobre una publicación del muro. Puede ser de primer nivel o una **respuesta** a otro. No confundir con `Feedback`. |
| Respuesta a comentario | `PostComment` con `parentCommentId` | Comentario anidado bajo otro. Un solo nivel de profundidad. |
| Mención | `Mention` | Referencia a un usuario dentro de un comentario. Se guarda como `UserId`, **nunca como texto**: los nombres de usuario se reciclan y una mención en texto acabaría señalando a otra persona. |
| Reacción | `Reaction` | Respuesta emocional con emoji a una publicación. |
| Apoyo / Me gusta | `Like` | Apoyo simple a una publicación, **a un comentario o a una respuesta**. Se distingue de `Reaction` y, sobre todo, de `FeedbackRating`, que sí mueve créditos. |
| Suscripción a autor | `AuthorSubscription` | Seguimiento de un autor para recibir avisos de sus publicaciones y obras nuevas. |
| Silenciar | `Mute` | Ocultar el contenido de alguien **sin que lo sepa** y sin romper el seguimiento. Preferencia de visualización. |
| Bloquear | `Block` | Cortar el contacto con alguien: deshace los seguimientos, corta los mensajes y le impide interactuar. **Regla de acceso**, no preferencia. |
| Mensaje directo (MD) | `DirectMessage` | Mensaje privado entre dos usuarios. Requiere que el destinatario los tenga habilitados. |
| Ranking | `Ranking` | Clasificación de escritores, obras o lectores, filtrable por temática y periodo. |
| Repost | `Repost` | Republicación de una publicación ajena en el propio muro, sin texto propio. Referencia al original; no lo copia ni amplía su audiencia. Distinto de compartir fuera de la plataforma. |
| Compartir | `Share` | Difusión de una publicación **fuera** de Lectores Beta. |
| Publicación guardada | `SavedPost` | Publicación que el usuario archiva para leer después. Privada. |
| Obra en corrección | `WorkStatus: IN_CORRECTION` | **Estado** del ciclo de vida de una obra: publicada y **abierta a recibir feedback**. Distinto de `PUBLISHED`, que se puede leer pero no comentar. |
| Obra publicada | `WorkStatus: PUBLISHED` | **Estado**: la obra ha salido del cajón y se puede leer. **No** admite correcciones nuevas; abrirlas es una segunda decisión del autor, porque recibirlas cuesta créditos. Se llama `PUBLISHED` y no `VISIBLE` para no chocar con el eje de visibilidad (`FEAT-WRK-016`, `W-9`). |
| Tour | `Tour` | Secuencia de globos que presenta la aplicación la primera vez. Su estado se guarda por usuario y por tour. |
| Crédito | `Credit` | Unidad de la economía interna que equilibra dar y recibir feedback. |
| Saldo de créditos | `CreditBalance` | Créditos disponibles de un usuario. |
| Movimiento de créditos | `CreditTransaction` | Registro inmutable de una variación del saldo, con su motivo y su origen. |
| Créditos de bienvenida | `WELCOME_GRANT` | Los **10 créditos** que se abonan una sola vez al activar la cuenta. Es uno de los dos únicos **grifos** del sistema, y por tanto el saldo medio de la plataforma. |
| Grifo | `CreditTransactionReason::isTap()` | Motivo que **crea** créditos en vez de moverlos entre dos cuentas. Solo la bienvenida, la bonificación por invitación y el ajuste manual lo son. |
| Evento procesado | `ProcessedEvent` | Registro de que un evento de integración ya se aplicó en `Credits`, con el que se garantiza que un crédito no se abona dos veces. La entrega duplicada hay que darla por supuesta. |
| Precio anotado | `CorrectionPrice` | Importe que se fija al empezar una corrección y que se cargará y abonará al entregarla. **No bloquea créditos**: el saldo del autor sigue íntegro. |
| Corrección bloqueada | `Correction` con `locked` | Corrección entregada que dejó el saldo del autor en negativo. El autor ve sus metadatos, no su contenido, hasta que repone saldo. |
| Regla de créditos | `CreditRule` | Norma que traduce un hecho de negocio en una variación de créditos. |
| ~~Clasificación del texto~~ | ~~`TextTier`~~ | **Derogado** ([`decision:0006`](decisions/0006-credit-system.md)). El precio ya no usa tramos sino una fórmula continua sobre el número de palabras. El recuento de palabras sigue existiendo ([`FEAT-WRK-013`](features/work/FEAT-WRK-013-word-count-and-reading-time.md)); la clasificación en niveles, no. |
| Tiempo de lectura | `readingMinutes` | Cuánto se tarda en leer un texto, **derivado** del recuento a 200 palabras por minuto y nunca almacenado ([`FEAT-WRK-013`](features/work/FEAT-WRK-013-word-count-and-reading-time.md)). |
| Invitación a la plataforma | `PlatformInvitation` | Invitación por email para que alguien se registre. Otorga créditos al invitador si el invitado participa. |
| Página de autor / Mi perfil | `AuthorPage` | Perfil público de un escritor: portada, avatar, descripción, obras publicadas y premios. Si son dos pantallas distintas está sin decidir (`P-5`). |
| Obra publicada | `PublishedBook` | Libro ya editado **fuera de Lectores Beta**, con editorial, año y enlace de compra, que el autor añade a su perfil como mérito. **No es una `Work`**: no tiene contenido en la plataforma, no se lee, no se comenta y no mueve créditos. |
| Premio o reconocimiento | `AuthorAward` | Mérito que el autor declara en su perfil. |
| ~~Nivel~~ | — | La insignia «0 Level» del perfil era **un error del diseño**. No existe sistema de niveles. |
| Preferencias literarias | `LiteraryPreferences` | Géneros y temáticas de interés declarados por el usuario. |
| Temática / Género | `Genre` | Clasificación temática de obras y de intereses de usuario. |
| Notificación | `Notification` | Aviso dirigido a un usuario, entregable in-app o por email. |
| Activación de cuenta | `AccountActivation` | Confirmación del email mediante un enlace con token, que lleva la cuenta de `PENDING_ACTIVATION` a `ACTIVE`. Desbloquea los créditos de bienvenida y todas las operaciones de escritura. |
| ~~Alias de presentación~~ | — | Concepto retirado. El saludo del onboarding muestra el **nombre de usuario** (`Username`), que sí se almacena y sí es único. «Alias» designa ahora únicamente el nombre de usuario anterior conservado tras un cambio. |
| Nombre | `Name` | Nombre **público** de un usuario, recogido en el paso 1 del onboarding. Es el referente con el que se le identifica en perfiles, catálogo, muro, comentarios, rankings y sugerencias. No es el identificador técnico: ese es `UserId`. |
| Nombre de usuario | `Username` | Identificador público, único y corto que se muestra como `@bealonso` y forma la URL del perfil. Se asigna solo a partir del email y es editable una vez cada 30 días. **No es la identidad**: esa sigue siendo `UserId`. |
| Alias de nombre de usuario | `UsernameAlias` | Nombre de usuario reservado durante 30 días, tras un cambio de nombre o tras el borrado de la cuenta. Evita que otra persona lo ocupe y herede los enlaces. El de un cambio **resuelve** al perfil; el de una cuenta eliminada solo bloquea. Caduca solo. |
| Motivo del alias | `UsernameAliasReason` | `USERNAME_CHANGED`, `ACCOUNT_DELETED` |
| Onboarding | `Onboarding` | Proceso de tres pasos posterior al registro: datos personales, géneros de interés y autores a seguir. |
| Aceptación legal | `LegalAcceptance` | Registro inmutable de qué versión de las condiciones de uso y la política de privacidad aceptó un usuario, y cuándo. |
| Catálogo de géneros | `Genre` | Lista administrable de géneros literarios, compartida por los intereses del lector y la temática de las obras. |

---

## Modalidades y enumerados

| Español | Inglés | Valores |
|---|---|---|
| Modalidad de acceso LB | `BetaReaderAccessMode` | `PUBLIC` (cualquiera se convierte en LB automáticamente), `ON_REQUEST` (requiere aprobación del autor), `PRIVATE` (solo por invitación del autor) |
| Estado de la obra | `WorkStatus` | `DRAFT` (solo el autor), `VISIBLE` (legible, sin feedback), `IN_CORRECTION` (legible y abierta a feedback). **Sustituye a `Visibility` en la obra** (`W-9`) |
| Visibilidad | `Visibility` | `VISIBLE`, `HIDDEN` — **solo aplicable al fragmento**; en la obra queda sustituida por `WorkStatus` |
| Tipo de publicación | `PostType` | **Intención**: `GENERAL`, `LOOKING_FOR_BETA_READERS`, `LOOKING_FOR_WRITING_BUDDY`, `OFFERING_AS_BETA_READER` |
| Formato de publicación | `PostFormat` | **Forma del contenido**: `TEXT`, `IMAGE`, `VIDEO`, `LINK`, `WORK`. Dimensión independiente de `PostType` |
| Audiencia de publicación | `PostAudience` | **Quién puede verla.** Solo se conoce el valor por defecto, «cualquiera» (`C-1`) |
| Estado de solicitud | `RequestStatus` | `PENDING`, `ACCEPTED`, `REJECTED`, `CANCELLED`, `EXPIRED` |
| ~~Nivel de texto~~ | ~~`TextTier`~~ | **Derogado.** Ver arriba |
| Motivo de movimiento de créditos | `CreditTransactionReason` | `ACCOUNT_ACTIVATED`, `FEEDBACK_GIVEN`, `FEEDBACK_RATED_POSITIVELY`, `INVITED_USER_PARTICIPATED`, `FEEDBACK_RECEIVED`, `MANUAL_ADJUSTMENT` |
| Estado de la cuenta | `AccountStatus` | `PENDING_ACTIVATION` (solo lectura y onboarding), `ACTIVE`, `DELETED` |
| Estado del onboarding | `OnboardingStatus` | `PROFILE_PENDING`, `GENRES_PENDING`, `SUGGESTIONS_PENDING`, `COMPLETED` |
| Estado de la retención | `CreditReservationStatus` | `HELD`, `CONFIRMED`, `RELEASED` |
| Tipo de documento legal | `LegalDocumentType` | `TERMS_OF_USE`, `PRIVACY_POLICY` |
| Proveedor de autenticación | `AuthProvider` | `LOCAL`, `GOOGLE`. `FACEBOOK` y `LINKEDIN` están previstos pero **diferidos** |

### Catálogo de géneros

Identificadores propuestos a partir del diseño del onboarding (`FEAT-USR-023`). **El catálogo
no está cerrado**: el diseño incluye chips de relleno y la lista tiene scroll.

| Español | `Genre` | | Español | `Genre` |
|---|---|---|---|---|
| Aventura | `ADVENTURE` | | Misterio | `MYSTERY` |
| Ciencia Ficción | `SCIENCE_FICTION` | | Poesía | `POETRY` |
| Comedia | `COMEDY` | | Policíaco | `CRIME` |
| Drama | `DRAMA` | | Romance | `ROMANCE` |
| Fantasía | `FANTASY` | | Terror | `HORROR` |
| Histórico | `HISTORICAL` | | Thriller | `THRILLER` |
| Infantil | `CHILDREN` | | | |

El diseño escribe «Poeta»; se corrige a «Poesía» (`POETRY`), que es el género.

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
| `Comment` a secas para la crítica de una obra | `Correction` | Tres conceptos distintos conviven: `PostComment` (muro), `ChapterComment` (bajo un capítulo) y `Correction` (el cuestionario respondido). Solo el tercero mueve créditos. |
| `Review` para la crítica de una obra | `Correction` | `Review` se reserva para la **decisión de un moderador** sobre una reclamación. |
| `Page`, `Section`, `Part` | `Chapter` | Un único nombre para la subdivisión de la obra. |
| `Points`, `Tokens`, `Coins` | `Credit` | El documento de producto habla de créditos. |
| `Nickname`, `Handle` | `Username` | Un único nombre para el concepto. |
| `Book`, `PublishedWork` para el manuscrito | `Work` | `PublishedBook` es otra cosa: el libro editado fuera de la plataforma. |
| `Helper`, `Manager`, `Utils` | Un nombre que describa la responsabilidad | Regla explícita de `AGENTS.md`. |
