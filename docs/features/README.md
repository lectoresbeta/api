# Registro maestro de funcionalidades

Índice único de todo lo que la plataforma debe hacer, con su estado. **Es la fuente de
verdad sobre el alcance.**

- Los valores de estado están definidos en [`../conventions.md`](../conventions.md#4-estados).
- `Spec` = madurez de la especificación. `Impl` = estado del código. Son independientes.
- Cuando una funcionalidad se detalla, se crea su ficha y se enlaza en la columna `Ficha`.
  Mientras tanto la fila es toda la información que existe.
- Ninguna funcionalidad se implementa sin ficha en `APPROVED`.

**Última actualización: 2026-09-22**

---

## Resumen

| Contexto | Funcionalidades | Con ficha | `APPROVED` | `DONE` |
|---|---|---|---|---|
| `User` (USR) | 42 | 25 | 0 | 0 |
| `Work` (WRK) | 16 | 5 | 0 | 0 |
| `Reading` (RDG) | 10 | 0 | 0 | 0 |
| `Feedback` (FBK) | 12 | 3 | 0 | 0 |
| `Community` (COM) | 36 | 9 | 0 | 0 |
| `Credits` (CRD) | 16 | 5 | 0 | 0 |
| `Notification` (NOT) | 9 | 1 | 0 | 0 |
| **Total** | **138** | **48** | **0** | **0** |

Estado global: **especificación inicial**. No hay código en `src/`.

Áreas cubiertas con diseño:

- **flujo de creación de cuenta y onboarding** — [pantallas](../ui/account-creation.md);
- **layout general y navegación** — [pantallas](../ui/app-layout-and-navigation.md);
- **Home**, con tour, estado vacío y modal de créditos — [pantallas](../ui/home.md);
- **Mi perfil**, estados vacíos — [pantallas](../ui/my-profile.md);
- **Gestión de la foto de perfil** — [pantallas](../ui/profile-photo.md);
- **Crear una publicación** — [pantallas](../ui/create-post.md);
- **Interacciones con una publicación** — [pantallas](../ui/post-interactions.md);
- **Mis relatos** y **Más info** — [pantallas](../ui/my-works.md) y [pantallas](../ui/profile-more-info.md);
- **Perfil de otro usuario** — [pantallas](../ui/user-profile.md);
- **Sección «Leer»**, catálogo con filtros — [pantallas](../ui/read-section.md);
- **Lectura de un capítulo y formulario de corrección** — [pantallas](../ui/read-chapter.md);
- **Configuración del usuario**, cinco pestañas — [pantallas](../ui/settings.md).

---

## `User` — cuenta, identidad y perfil

Ficha del contexto: [`../bounded-contexts/user.md`](../bounded-contexts/user.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-USR-001 | Registro con email y contraseña | Guest | DRAFT | TODO | P0 | [ficha](user/FEAT-USR-001-register-with-email.md) |
| FEAT-USR-002 | Registro con cuenta de Google | Guest | DRAFT | TODO | P0 | [ficha](user/FEAT-USR-002-register-with-google.md) |
| FEAT-USR-003 | Registro con cuenta de Facebook | Guest | PENDING | DEFERRED | P3 | — |
| FEAT-USR-004 | Login con email y contraseña | Guest | PENDING | TODO | P0 | — |
| FEAT-USR-005 | Login con cuenta de Google | Guest | PENDING | TODO | P0 | — |
| FEAT-USR-006 | Login con cuenta de Facebook | Guest | PENDING | DEFERRED | P3 | — |
| FEAT-USR-007 | Recuperar contraseña | Guest | PENDING | TODO | P0 | — |
| FEAT-USR-008 | Editar el perfil — nombre, biografía y foto | User | DRAFT | TODO | P1 | [ficha](user/FEAT-USR-008-edit-profile.md) |
| FEAT-USR-009 | Editar preferencias literarias | User | PENDING | TODO | P2 | — |
| FEAT-USR-010 | Configurar recepción de mensajes directos | User | PENDING | TODO | P2 | — |
| FEAT-USR-011 | Configurar recepción de propuestas de LB y writing buddy | User | PENDING | TODO | P2 | — |
| FEAT-USR-012 | ~~Configurar notificaciones por email~~ → `FEAT-USR-039` | User | PENDING | DEPRECATED | P3 | — |
| FEAT-USR-013 | Eliminar la cuenta | User | DRAFT | BLOCKED | P2 | [ficha](user/FEAT-USR-013-delete-account.md) |
| FEAT-USR-014 | Ver perfil público de un usuario | User, Guest | DRAFT | TODO | P1 | [ficha](user/FEAT-USR-014-view-public-profile.md) |
| FEAT-USR-015 | Configurar información de la página de autor (bio, foto, referencias) | Writer | PENDING | TODO | P2 | — |
| FEAT-USR-016 | Personalizar página de autor (fuentes, colores, fondos) | Writer | PENDING | TODO | P3 | — |
| FEAT-USR-017 | Buscar autores por nombre o temática | User | PENDING | TODO | P1 | — |
| FEAT-USR-018 | Invitar a personas a la plataforma por email | User | PENDING | TODO | P2 | — |
| FEAT-USR-019 | Registro y login con LinkedIn | Guest | DRAFT | DEFERRED | P3 | [ficha](user/FEAT-USR-019-linkedin-oauth.md) |
| FEAT-USR-020 | Activar la cuenta desde el enlace enviado por email | Guest, User | DRAFT | TODO | P0 | [ficha](user/FEAT-USR-020-activate-account.md) |
| FEAT-USR-021 | Reenviar el email de activación | Guest, User | DRAFT | TODO | P1 | [ficha](user/FEAT-USR-021-resend-activation-email.md) |
| FEAT-USR-022 | Onboarding paso 1 — nombre y fecha de nacimiento | User | DRAFT | TODO | P0 | [ficha](user/FEAT-USR-022-onboarding-profile-data.md) |
| FEAT-USR-023 | Onboarding paso 2 — elegir al menos tres géneros | User | DRAFT | TODO | P0 | [ficha](user/FEAT-USR-023-onboarding-select-genres.md) |
| FEAT-USR-024 | Aceptar condiciones de uso y política de privacidad | Guest | DRAFT | TODO | P0 | [ficha](user/FEAT-USR-024-accept-terms-and-privacy.md) |
| FEAT-USR-025 | Bloquear las operaciones de escritura hasta activar la cuenta | User | DRAFT | TODO | P0 | [ficha](user/FEAT-USR-025-block-writes-until-activation.md) |
| FEAT-USR-026 | Tour de bienvenida de la Home | User | DRAFT | TODO | P2 | [ficha](user/FEAT-USR-026-welcome-tour.md) |
| FEAT-USR-027 | Contexto de sesión para el layout | User | DRAFT | TODO | P1 | [ficha](user/FEAT-USR-027-session-context.md) |
| FEAT-USR-028 | Mi perfil — cabecera, datos y contadores | User | DRAFT | TODO | P1 | [ficha](user/FEAT-USR-028-own-profile-header.md) |
| FEAT-USR-029 | Obras publicadas del autor (bibliografía externa) | Writer | DRAFT | TODO | P2 | [ficha](user/FEAT-USR-029-published-books.md) |
| FEAT-USR-030 | Premios y reconocimientos del autor | Writer | PENDING | TODO | P3 | — *(sin diseño)* |
| FEAT-USR-031 | ~~Nivel del usuario~~ | — | PENDING | DEPRECATED | P3 | — |
| FEAT-USR-032 | Compartir el perfil | User | PENDING | TODO | P3 | — |
| FEAT-USR-033 | Nombre de usuario — formato, asignación automática y unicidad | Guest, User | DRAFT | TODO | P0 | [ficha](user/FEAT-USR-033-username-assignment.md) |
| FEAT-USR-034 | Cambiar el nombre de usuario y alias temporal | User | DRAFT | TODO | P1 | [ficha](user/FEAT-USR-034-change-username.md) |
| FEAT-USR-035 | Resolver un perfil por nombre de usuario o alias | Guest, User | DRAFT | TODO | P1 | [ficha](user/FEAT-USR-035-resolve-profile-by-username.md) |
| FEAT-USR-036 | Purga programada de alias caducados | — (sistema) | DRAFT | TODO | P2 | [ficha](user/FEAT-USR-036-purge-expired-aliases.md) |
| FEAT-USR-037 | Gestionar la foto de perfil — subir, editar y eliminar | User | DRAFT | TODO | P2 | [ficha](user/FEAT-USR-037-upload-profile-photo.md) |
| FEAT-USR-038 | Ajustes de privacidad del usuario | User | DRAFT | TODO | P1 | [ficha](user/FEAT-USR-038-privacy-settings.md) |
| FEAT-USR-039 | Preferencias de notificación por canal | User | DRAFT | TODO | P2 | [ficha](user/FEAT-USR-039-notification-preferences.md) |
| FEAT-USR-040 | Cambiar el correo de la cuenta | User | DRAFT | TODO | P1 | [ficha](user/FEAT-USR-040-change-email.md) |
| FEAT-USR-041 | Cambiar o establecer la contraseña | User | DRAFT | TODO | P1 | [ficha](user/FEAT-USR-041-change-password.md) |
| FEAT-USR-042 | Preferencias de apariencia (tema) | User | PENDING | TODO | P3 | — *(sin captura)* |

> `FEAT-USR-013` sigue `BLOCKED`. La pantalla de Configuración aporta el flujo pero **agrava
> el problema**: promete al usuario que se borrarán «todos los datos asociados», y eso no
> puede cumplirse —el nombre de usuario queda bloqueado 30 días, las correcciones que otros
> autores **pagaron** no son suyas para borrarlas, y los movimientos de créditos son
> inmutables—. La salida conocida es **anonimizar en vez de borrar** (`S-32`).
>
> `FEAT-USR-038` a `FEAT-USR-042` salen de la pantalla de **Configuración**. `FEAT-USR-039`
> absorbe a `FEAT-USR-012`, que solo contemplaba el correo y queda `DEPRECATED`.
>
> **`FEAT-USR-034` se ha quedado sin interfaz** (`S-3`): la pestaña «Perfil» permite cambiar
> el nombre, pero **no el nombre de usuario**, que es justamente para lo que existe toda la
> maquinaria de alias de `decision:0005`.
>
> `FEAT-USR-028` a `FEAT-USR-036` salen del diseño de «Mi perfil».
>
> **`P-1` está resuelta: el nombre de usuario existe.**
> ([`decision:0005`](../decisions/0005-username-with-temporary-aliases.md)) Se asigna solo a
> partir del email, es único y editable una vez al mes, y al cambiarlo deja un **alias de 30
> días** que mantiene vivos los enlaces de perfil e impide que otro ocupe el nombre. Un
> comando diario purga los alias caducados (`FEAT-USR-036`).
>
> El usuario puede **recuperar su nombre anterior** mientras el alias siga vigente, sin
> esperar los 30 días; la recuperación renueva el plazo, lo que cubre el arrepentimiento sin
> permitir alternar entre dos nombres. **Eliminar la cuenta tampoco libera el nombre**: queda
> bloqueado 30 días como alias que no resuelve, para que nadie herede los enlaces de quien se
> ha marchado.
>
> `FEAT-USR-031` queda `DEPRECATED`: la insignia «0 Level» era **un error del diseño**, no un
> sistema de niveles.
>
> `FEAT-USR-019` a `FEAT-USR-025` salen del diseño del flujo de creación de cuenta.
>
> **Solo Google en esta fase.** Facebook (`FEAT-USR-003`, `FEAT-USR-006`) y LinkedIn
> (`FEAT-USR-019`) quedan `DEFERRED`; el diseño muestra los tres botones, pero solo se
> implementa Google.
>
> **No existe el nombre de usuario.** La plataforma no lo pide ni lo almacena; el saludo del
> onboarding usa un alias derivado del email.
>
> **El nombre público es el «Nombre» del paso 1 del onboarding** (`FEAT-USR-022`). Es el
> referente para identificar a un usuario en toda la plataforma. El aviso de privacidad del
> diseño corresponde **solo** a la fecha de nacimiento.

---

## `Work` — obras, fragmentos y contenido

Ficha del contexto: [`../bounded-contexts/work.md`](../bounded-contexts/work.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-WRK-001 | Crear obra con el editor WYSIWYG | Writer | DRAFT | TODO | P0 | [ficha](work/FEAT-WRK-001-create-work-with-editor.md) |
| FEAT-WRK-002 | Crear obra subiendo un fichero (.doc, .pdf, .txt) | Writer | PENDING | TODO | P1 | — |
| FEAT-WRK-003 | Estructurar la obra en fragmentos | Writer | PENDING | TODO | P0 | — |
| FEAT-WRK-004 | Ver el contenido de una obra | Writer, BetaReader | PENDING | TODO | P0 | — |
| FEAT-WRK-005 | Editar una obra o un fragmento | Writer | PENDING | TODO | P0 | — |
| FEAT-WRK-006 | Eliminar una obra con confirmación | Writer | PENDING | TODO | P1 | — |
| FEAT-WRK-007 | Configurar la modalidad de acceso de lectores beta | Writer | PENDING | TODO | P0 | — |
| FEAT-WRK-008 | Configurar la visibilidad de obra y fragmentos | Writer | PENDING | TODO | P1 | — |
| FEAT-WRK-009 | Generar el registro de autoría | Writer | PENDING | BLOCKED | P1 | — |
| FEAT-WRK-010 | Crear enlace público para leer y comentar sin sesión | Writer | PENDING | TODO | P2 | — |
| FEAT-WRK-011 | Generar enlace para compartir en redes sociales y captar LB | Writer | PENDING | TODO | P2 | — |
| FEAT-WRK-012 | Sección «Leer» — catálogo con filtros y ordenación | User | DRAFT | TODO | P1 | [ficha](work/FEAT-WRK-012-browse-catalogue.md) |
| FEAT-WRK-013 | Calcular el número de palabras y el nivel de extensión | — (sistema) | PENDING | TODO | P0 | — |
| FEAT-WRK-014 | Definir el cuestionario que acompaña a la obra | Writer | DRAFT | TODO | P0 | [ficha](work/FEAT-WRK-014-configure-questionnaire.md) |
| FEAT-WRK-015 | Mis relatos — listado con filtros y ordenación | Writer | DRAFT | TODO | P1 | [ficha](work/FEAT-WRK-015-my-works-list.md) |
| FEAT-WRK-016 | Estado de una obra — borrador, visible y en corrección | Writer | DRAFT | TODO | P0 | [ficha](work/FEAT-WRK-016-work-status.md) |

> `FEAT-WRK-009` está `BLOCKED`: el documento de origen deja explícitamente abierto en qué
> momentos se genera el registro (`W-1`).
>
> **`FEAT-WRK-016` resuelve qué es «una obra en corrección»**: un estado del ciclo de vida.
> Una obra está en `DRAFT`, `VISIBLE` o `IN_CORRECTION`, y solo en `IN_CORRECTION` admite
> feedback. Eso sustituye a `Visibility` (`W-9`) y **reabre `R-1`**: si entrar en corrección
> es una acción explícita del autor, es el sitio natural para reservar los créditos, y la
> compensación entre contextos de `decision:0004` dejaría de hacer falta.
>
> **`FEAT-WRK-014` sube a `P0`.** El cuestionario parecía un accesorio de la obra y resulta
> ser el instrumento con el que el autor **fija el precio de su propia corrección**: su
> configuración determina a la vez lo que paga el autor y lo que gana el lector
> (`FEAT-CRD-016`). Ninguna corrección puede existir sin él.
>
> `FEAT-WRK-012` se reformula: no es «buscar obras por tipo, temática y valoración» sino la
> **sección «Leer»** completa, con filtro por estado, recuento total y paginación numerada.

---

## `Reading` — acceso de lectores beta

Ficha del contexto: [`../bounded-contexts/reading.md`](../bounded-contexts/reading.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-RDG-001 | Convertirse en LB automáticamente (obra `PUBLIC`) | Reader | PENDING | TODO | P0 | — |
| FEAT-RDG-002 | Solicitar ser LB de una obra (obra `ON_REQUEST`) | Reader | PENDING | TODO | P0 | — |
| FEAT-RDG-003 | Aceptar o rechazar una solicitud de LB | Writer | PENDING | TODO | P0 | — |
| FEAT-RDG-004 | Invitar a un usuario a ser LB de una obra | Writer | PENDING | TODO | P1 | — |
| FEAT-RDG-005 | Aceptar o rechazar una invitación de LB | Reader | PENDING | TODO | P1 | — |
| FEAT-RDG-006 | Buscar lectores beta | Writer | PENDING | TODO | P2 | — |
| FEAT-RDG-007 | Gestionar grupos de lectores beta (CRUD y miembros) | Writer | PENDING | TODO | P2 | — |
| FEAT-RDG-008 | Proponer a un usuario ser writing buddy | Writer | PENDING | TODO | P2 | — |
| FEAT-RDG-009 | Aceptar o rechazar una propuesta de writing buddy | User | PENDING | TODO | P2 | — |
| FEAT-RDG-010 | Revocar el acceso de un lector beta | Writer | PENDING | DEFERRED | P3 | — |

> `FEAT-RDG-010` no aparece en el material de partida. Se registra porque su ausencia es
> probablemente un olvido, no una decisión (`A-4`, `R-1`).

---

## `Feedback` — comentarios y valoraciones

Ficha del contexto: [`../bounded-contexts/feedback.md`](../bounded-contexts/feedback.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-FBK-001 | Dejar feedback sobre una obra o fragmento | BetaReader | PENDING | TODO | P0 | — |
| FEAT-FBK-002 | Valorar una obra | BetaReader | PENDING | TODO | P1 | — |
| FEAT-FBK-003 | Responder y enviar el cuestionario de corrección | BetaReader | DRAFT | TODO | P0 | [ficha](feedback/FEAT-FBK-003-answer-correction-questionnaire.md) |
| FEAT-FBK-004 | Ver los comentarios recibidos | Writer | PENDING | TODO | P0 | — |
| FEAT-FBK-005 | Contestar a un comentario recibido | Writer | PENDING | TODO | P1 | — |
| FEAT-FBK-006 | Valorar positivamente un comentario recibido | Writer | PENDING | TODO | P1 | — |
| FEAT-FBK-007 | Ocultar un comentario recibido | Writer | PENDING | TODO | P2 | — |
| FEAT-FBK-008 | Comentar mediante enlace público sin iniciar sesión | Guest | PENDING | BLOCKED | P2 | — |
| FEAT-FBK-009 | Denunciar un comentario abusivo | User | PENDING | DEFERRED | P3 | — |
| FEAT-FBK-010 | Mis correcciones — listado del feedback que he dado | User | PENDING | TODO | P2 | — |
| FEAT-FBK-011 | Guardar un borrador de corrección | BetaReader | DRAFT | TODO | P1 | [ficha](feedback/FEAT-FBK-011-save-correction-draft.md) |
| FEAT-FBK-012 | Control antifraude de las correcciones | — (sistema) | PENDING | BLOCKED | P0 | [ficha](feedback/FEAT-FBK-012-correction-fraud-control.md) |

> **Una corrección no es un comentario.** La pantalla de lectura tiene las dos cosas a la
> vez: comentarios libres bajo el texto, que no mueven créditos y pertenecen a `Community`
> (`FEAT-COM-036`), y el **cuestionario del autor**, que sí los mueve y pertenece a
> `Feedback` (`FEAT-FBK-003`). Hasta ahora la documentación las confundía. Esto aclara `D-1`
> y `F-1`: **lo que el sistema paga es el cuestionario respondido.**
>
> **La corrección es por capítulo** (`R-2`, decidida). Un lector puede corregir cada capítulo
> por separado, y cada corrección es una operación de créditos independiente. El índice único
> que impide repetir es `(chapterId, readerId)`.
>
> `FEAT-FBK-011` sale del botón «Guardar» del panel de corrección: un borrador **no publica
> ningún evento ni mueve créditos**. Es el mismo agregado que la corrección, en estado
> `DRAFT`.
>
> `FEAT-FBK-012` recoge el **control antifraude** que producto confirma que existirá. Nace en
> `P0` porque el formulario de corrección es el único punto de la plataforma donde escribir
> texto produce saldo, y porque la longitud mínima —única defensa actual— no distingue una
> respuesta larga de una respuesta con contenido.
>
> `FEAT-FBK-008` está `BLOCKED` por `A-3`, `C-5` y `F-6`: sin resolver si el comentarista
> anónimo se identifica y si la operación mueve créditos, no se puede especificar.
> `FEAT-FBK-009` no está en el material de partida; depende de que exista moderación (`V-1`).

---

## `Community` — muro, social y rankings

Ficha del contexto: [`../bounded-contexts/community.md`](../bounded-contexts/community.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-COM-001 | Ver las publicaciones del muro principal | User | PENDING | TODO | P1 | — |
| FEAT-COM-002 | Crear una publicación (texto, imagen, vídeo, enlace o relato) | User | DRAFT | TODO | P1 | [ficha](community/FEAT-COM-002-create-post.md) |
| FEAT-COM-003 | Publicar buscando lectores beta para una obra | Writer | PENDING | TODO | P1 | — |
| FEAT-COM-004 | Publicar buscando writing buddy | Writer | PENDING | TODO | P2 | — |
| FEAT-COM-005 | Publicar ofreciéndose como lector beta | Reader | PENDING | TODO | P2 | — |
| FEAT-COM-006 | Comentar una publicación | User | DRAFT | TODO | P2 | [ficha](community/FEAT-COM-006-comment-on-post.md) |
| FEAT-COM-007 | Reaccionar con emoji a una publicación | User | PENDING | TODO | P2 | — |
| FEAT-COM-008 | Apoyar una publicación con un "me gusta" | User | PENDING | TODO | P2 | — |
| FEAT-COM-009 | Filtrar y buscar publicaciones (tipo, texto, usuario, fecha) | User | PENDING | TODO | P2 | — |
| FEAT-COM-010 | Suscribirse a un autor | User | PENDING | TODO | P2 | — |
| FEAT-COM-011 | Enviar un mensaje directo | User | PENDING | TODO | P2 | — |
| FEAT-COM-012 | Ver y gestionar conversaciones de mensajes directos | User | PENDING | TODO | P2 | — |
| FEAT-COM-013 | Ver y filtrar el ranking de escritores | User | PENDING | BLOCKED | P2 | — |
| FEAT-COM-014 | Ver y filtrar el ranking de obras | User | PENDING | BLOCKED | P2 | — |
| FEAT-COM-015 | Ver y filtrar el ranking de lectores | User | PENDING | BLOCKED | P2 | — |
| FEAT-COM-016 | Onboarding paso 3 — sugerencias de autores a seguir | User | DRAFT | TODO | P1 | [ficha](community/FEAT-COM-016-onboarding-author-suggestions.md) |
| FEAT-COM-017 | Home — carrusel de obras recomendadas | User | DRAFT | TODO | P1 | [ficha](community/FEAT-COM-017-home-work-recommendations.md) |
| FEAT-COM-018 | Home — sugerencias de autores en el muro | User | DRAFT | TODO | P1 | [ficha](community/FEAT-COM-018-home-author-suggestions.md) |
| FEAT-COM-019 | Repostear una publicación | User | DRAFT | TODO | P2 | [ficha](community/FEAT-COM-019-repost.md) |
| FEAT-COM-020 | Compartir una publicación fuera de la plataforma | User | PENDING | TODO | P2 | — |
| FEAT-COM-021 | Guardar una publicación | User | PENDING | TODO | P3 | — |
| FEAT-COM-022 | Ocultar una publicación del muro | User | PENDING | TODO | P3 | — |
| FEAT-COM-023 | Denunciar una publicación | User | PENDING | DEFERRED | P3 | — |
| FEAT-COM-024 | Ordenar el muro por relevancia o por fecha | User | PENDING | BLOCKED | P2 | — |
| FEAT-COM-025 | Búsqueda global desde la cabecera | User | PENDING | DEFERRED | P3 | — |
| FEAT-COM-026 | Mi muro — publicaciones propias | User | PENDING | TODO | P2 | — |
| FEAT-COM-027 | Mis Amigos — seguidos y seguidores | User | PENDING | TODO | P2 | — |
| FEAT-COM-028 | Incluir un relato de la plataforma en una publicación | User | PENDING | TODO | P2 | — |
| FEAT-COM-029 | Audiencia de una publicación | User | PENDING | BLOCKED | P1 | — |
| FEAT-COM-030 | Me gusta en un comentario o respuesta | User | PENDING | TODO | P2 | — |
| FEAT-COM-031 | Responder a un comentario | User | DRAFT | TODO | P2 | [ficha](community/FEAT-COM-031-reply-to-comment.md) |
| FEAT-COM-032 | Menciones a usuarios | User | DRAFT | TODO | P2 | [ficha](community/FEAT-COM-032-mentions.md) |
| FEAT-COM-033 | Silenciar a un usuario | User | PENDING | TODO | P3 | — |
| FEAT-COM-034 | Bloquear a un usuario | User | DRAFT | TODO | P2 | [ficha](community/FEAT-COM-034-block-user.md) |
| FEAT-COM-035 | Denunciar a un usuario | User | PENDING | DEFERRED | P3 | — |
| FEAT-COM-036 | Interacciones sociales sobre un capítulo (like, comentario, compartir) | User | PENDING | TODO | P1 | — |

> Los tres rankings están `BLOCKED` por `CM-4`: el material de partida dice "mejor valorados"
> pero no define la fórmula de puntuación. Sin ella no hay especificación posible.
> `FEAT-COM-024` está `BLOCKED` por lo mismo: «más relevante» necesita esa fórmula.
>
> `FEAT-COM-029` está `BLOCKED`: el modal de publicación muestra un selector de audiencia
> («Publicar para cualquiera») y **no se conocen las demás opciones**. Sin ellas no hay
> modelo ni filtrado del muro. El mismo modal revela que se puede **adjuntar vídeo**, que no
> estaba contemplado y arrastra transcodificación y coste de almacenamiento (`C-2`).
>
> `FEAT-COM-033` a `FEAT-COM-035` salen del menú «···» del perfil ajeno. **Silenciar y
> bloquear no son lo mismo**: silenciar es una preferencia de visualización y bloquear es una
> regla de acceso que atraviesa varios contextos. `FEAT-COM-035` queda `DEFERRED` como las
> otras dos denuncias: no hay moderación que las atienda (`V-1`).
>
> `FEAT-COM-030` a `FEAT-COM-032` salen de la secuencia de interacciones: los comentarios se
> pueden valorar, admiten respuestas anidadas y llevan **menciones**. Una mención se guarda
> como referencia al usuario y nunca como texto: los nombres de usuario se reciclan pasados
> 30 días (`decision:0005`), así que una mención guardada como cadena podría acabar señalando
> a otra persona.
>
> `FEAT-COM-017` a `FEAT-COM-025` salen del diseño de la Home. **Repostear, compartir,
> guardar y denunciar son interacciones nuevas** que el documento de casos de uso no
> contemplaba. La búsqueda global está marcada como backlog en el propio diseño.

---

## `Credits` — economía de la plataforma

Ficha del contexto: [`../bounded-contexts/credits.md`](../bounded-contexts/credits.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-CRD-001 | Consultar saldo de créditos | User | PENDING | TODO | P0 | — |
| FEAT-CRD-002 | Abonar créditos de bienvenida al **activar** la cuenta (+20) | — (sistema) | PENDING | TODO | P0 | — |
| FEAT-CRD-003 | Abonar créditos por dar feedback, según nivel del texto | — (sistema) | PENDING | TODO | P0 | — |
| FEAT-CRD-004 | Abonar +5 créditos por feedback valorado positivamente | — (sistema) | PENDING | TODO | P1 | — |
| FEAT-CRD-005 | Abonar +5 créditos por invitación que participa | — (sistema) | PENDING | TODO | P2 | — |
| FEAT-CRD-006 | Confirmar el cargo al autor cuando recibe un comentario | — (sistema) | DRAFT | TODO | P0 | [ficha](credits/FEAT-CRD-006-charge-author-for-received-feedback.md) |
| FEAT-CRD-007 | Aplicar el coste adicional por preguntas extra del cuestionario | — (sistema) | PENDING | TODO | P1 | — |
| FEAT-CRD-008 | Consultar el historial de movimientos de créditos | User | PENDING | TODO | P1 | — |
| FEAT-CRD-009 | Reservar créditos al conceder acceso a un lector beta | — (sistema) | DRAFT | TODO | P0 | [ficha](credits/FEAT-CRD-009-reserve-credits-on-access-grant.md) |
| FEAT-CRD-010 | Calcular el coste con fórmula continua en vez de tramos | — (sistema) | PENDING | DEFERRED | P3 | — |
| FEAT-CRD-011 | Deduplicar eventos para garantizar idempotencia | — (sistema) | PENDING | TODO | P0 | — |
| FEAT-CRD-012 | Monitorizar la salud de la economía de créditos | Admin | PENDING | TODO | P1 | — |
| FEAT-CRD-013 | Créditos asociados a una obra (insignia de la tarjeta) | User | DRAFT | DEFERRED | P3 | [ficha](credits/FEAT-CRD-013-work-credit-badge.md) |
| FEAT-CRD-014 | Modal informativo del sistema de créditos | User | DRAFT | TODO | P2 | [ficha](credits/FEAT-CRD-014-credits-info-modal.md) |
| FEAT-CRD-015 | Pantalla con la tabla de puntuación de créditos | User | PENDING | TODO | P2 | — |
| FEAT-CRD-016 | Coste y recompensa determinados por el cuestionario | — (sistema) | DRAFT | BLOCKED | P0 | [ficha](credits/FEAT-CRD-016-questionnaire-based-pricing.md) |

> **`FEAT-CRD-012` pasa de `DEFERRED` a `TODO`.** Con precios de ajuste dinámico (`P-3`),
> medir la salud de la economía deja de ser una herramienta de administración y pasa a ser
> el **instrumento del que depende la política de precios**.
>
> **`C-1` está resuelta.** Se adopta la **reserva previa**
> ([`decision:0004`](../decisions/0004-credit-reservation-on-access-grant.md)): al conceder
> acceso a un lector beta se retienen los créditos del autor (`FEAT-CRD-009`) y al recibir el
> comentario se confirman (`FEAT-CRD-006`). Sin saldo disponible no se concede el acceso, así
> que el saldo nunca queda negativo. Ambas fichas pasan de `BLOCKED`/`DEFERRED` a `TODO` y
> son el camino crítico del contexto.
>
> `FEAT-CRD-013` queda `DEFERRED`: las cifras concretas de la insignia se definirán al
> documentar el sistema de créditos en detalle.
> `FEAT-CRD-009` depende de que se elija la opción de reserva (`C-2`).
> `FEAT-CRD-010` es la alternativa que el propio documento de origen plantea.

---

## `Notification` — avisos

Ficha del contexto: [`../bounded-contexts/notification.md`](../bounded-contexts/notification.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-NOT-001 | Entregar notificaciones in-app | — (sistema) | PENDING | TODO | P1 | — |
| FEAT-NOT-002 | Entregar notificaciones por email | — (sistema) | PENDING | TODO | P1 | — |
| FEAT-NOT-003 | Aplicar las preferencias de notificación del usuario | — (sistema) | PENDING | TODO | P1 | — |
| FEAT-NOT-004 | Avisar de nueva obra o publicación de un autor suscrito | — (sistema) | PENDING | TODO | P2 | — |
| FEAT-NOT-005 | Avisar de solicitudes, invitaciones y propuestas | — (sistema) | PENDING | TODO | P1 | — |
| FEAT-NOT-006 | Avisar de feedback recibido, contestado o valorado | — (sistema) | PENDING | TODO | P1 | — |
| FEAT-NOT-007 | Enviar el email de invitación a la plataforma | — (sistema) | PENDING | TODO | P2 | — |
| FEAT-NOT-008 | Enviar el email de activación de cuenta | — (sistema) | DRAFT | TODO | P0 | [ficha](notification/FEAT-NOT-008-account-activation-email.md) |
| FEAT-NOT-009 | Centro de notificaciones in-app y contador de no leídas | User | PENDING | TODO | P1 | — |

---

## Funcionalidades bloqueadas

Resumen de lo que no se puede especificar hasta tomar una decisión de producto:

| Funcionalidad | Bloqueada por | Decisión necesaria |
|---|---|---|
| FEAT-COM-013/014/015 | `CM-4` | Fórmula de puntuación de cada ranking |
| FEAT-WRK-009 | `W-1` | Cuándo se genera el registro de autoría |
| FEAT-FBK-008 | `A-3`, `C-5` | Identificación y créditos del comentarista anónimo |
| FEAT-USR-013 | `V-4`, `U-3` | Qué se conserva al eliminar la cuenta. El nombre de usuario **sí** está decidido: queda bloqueado 30 días |
| FEAT-COM-024 | `CM-4` | Fórmula de relevancia para ordenar el muro |
| FEAT-COM-029, FEAT-COM-002 | `C-1` | Qué opciones tiene el selector de audiencia de una publicación |
| FEAT-COM-002 | `C-2` | Si se admite vídeo, con qué límites y si se transcodifica |
| FEAT-COM-019 | `C-3` | Si un repost es un puntero o una publicación con entidad propia |
| FEAT-COM-006, FEAT-COM-024, rankings | `CM-4` | Una única fórmula de relevancia para el muro, los comentarios y los rankings |
| FEAT-CRD-009, FEAT-WRK-016 | **`R-1`** | Si la reserva es por lector o por obra. **El estado «En corrección» apunta a por obra** y eliminaría la compensación. Cerrar antes de implementar |
| FEAT-WRK-016 | `W-9` | Si `WorkStatus` sustituye a `Visibility` |
| FEAT-COM-034 | `B-2`, `B-3` | Si bloquear revoca el acceso de lector beta y libera su retención, y qué pasa con el feedback que el autor **ya pagó** |
| FEAT-USR-014 | `U-17` | Si el contador de correcciones es público y la lista no, de forma deliberada |
| **FEAT-CRD-016** | **`P-1`** | **La fórmula de precios. Aplazada por decisión de producto: los factores están decididos, la traducción a cifras no** |
| **FEAT-CRD-009, FEAT-FBK-003** | **`R-1`** | Si la retención es por obra o **por capítulo**. La corrección por capítulo ha separado las dos granularidades |
| **FEAT-FBK-012** | `AF-1`, `AF-2` | Qué mecanismo antifraude, y si actúa antes o después del abono |
| FEAT-CRD-016 | `P-5`, `P-6` | Cada cuánto se ajusta el precio y dónde se acumula el margen entre lo que paga el autor y lo que cobra el lector |
| FEAT-WRK-014 | `W-17` | Si las preguntas de obra entera se repiten en cada capítulo |
| FEAT-WRK-012 | `L-9` | Cómo llega la insignia de créditos al catálogo sin acoplar `Work` con `Credits` |
| **FEAT-USR-038, FEAT-WRK-016** | **`S-14`** | Si el ajuste global «quién puede comentar mis textos» manda sobre la modalidad de cada obra, y qué pasa con las retenciones vigentes |
| FEAT-USR-013 | `U-3`, `V-4`, `S-32` | Si la cuenta se borra o se **anonimiza**. La pantalla promete un borrado total que el sistema no puede hacer |
| FEAT-USR-039 | `S-11`, `S-12` | Qué tipos de aviso existen, y si corrección y comentario se separan |
| FEAT-USR-040, FEAT-USR-041 | `S-8` | Qué ve una cuenta de Google donde se pide «contraseña actual» |

**Decisiones tomadas el 2026-09-22** que cierran buena parte de lo anterior:

| # | Decisión |
|---|---|
| `P-2` | En el precio intervienen **la longitud del texto y la confección del cuestionario**. El `TextTier` sigue vigente |
| `P-3` | Coste y recompensa **no tienen por qué coincidir**: cabe un margen, incluso con ajuste dinámico |
| `P-1` | La fórmula exacta se define **más adelante** |
| `R-2` | La corrección es **por capítulo** |
| `R-5` | Las longitudes de respuesta se miden **en palabras** |
| `Q-3`/`Q-4` | Habrá **control antifraude**, con IA u otros mecanismos. Mecanismo por definir (`FEAT-FBK-012`) |
| `L-5` | **Hay footer** en todo el layout, aunque las maquetas no lo dibujen |

Dos consecuencias que no existían antes de tomarlas:

- **`R-1` pasa a ser lo más urgente.** El acceso de lector beta se concede **por obra** y el
  coste ahora se devenga **por capítulo**. La reserva previa de `decision:0004` ya no cubre
  automáticamente lo que se va a gastar, y la decisión afecta a `Reading`, `Feedback` y
  `Credits` a la vez.
- **`FEAT-CRD-012` deja de ser opcional.** Un ajuste dinámico de precios necesita medir la
  masa de créditos; sin monitorización sería un ajuste a ciegas. Pasa de `DEFERRED`/`P3` a
  `TODO`/`P1`.

`P-1` sigue bloqueando `FEAT-CRD-016`, pero es un bloqueo distinto: no falta información,
falta una decisión que producto toma después. Mientras tanto se puede construir todo el
andamiaje —registro auditable de movimientos, cuenta de sistema, fijación del importe en la
retención y la abstracción tras la que vivirá la regla—, de modo que lo único pendiente sea
**una implementación de esa abstracción**.

### Decisiones que frenan el paso a `APPROVED`

Estas funcionalidades tienen ficha y están en `DRAFT`, pero no pueden aprobarse —ni por tanto
implementarse— sin una decisión de producto:

| Funcionalidad | Pendiente de | Decisión necesaria |
|---|---|---|
| FEAT-USR-029 | `P-2` | Confirmar que una «obra publicada» es un concepto aparte de `Work` |
| FEAT-USR-035 | `N-11` | Forma de la URL de perfil: `/profile/{username}` o `/@{username}` |
| FEAT-USR-002, FEAT-USR-020 | `OB-11` | Si el alta con Google crea la cuenta ya activada, dado que Google ya verifica el correo |
| FEAT-USR-002, FEAT-USR-024 | `T-5` | Cómo se recoge la aceptación legal en el alta con Google: casilla previa o pantalla intermedia |
| FEAT-USR-022 | `OB-7` | Si hay edad mínima de registro. Tiene implicaciones legales |
| FEAT-USR-022 | `N-1`, `N-2` | Reglas de validación del nombre y si debe ser único |
| Todo `User` | `S-1` | Mecanismo de sesión de la API |

Resueltas hasta ahora: `OB-1` (alias derivado del email), `OB-2` (el nombre es público),
`OB-3` (ver [`decision:0003`](../decisions/0003-write-operations-require-activated-account.md)),
`OB-4`, `OB-5`, `OB-6`, `OB-8`, `OB-13` y `T-4` (sin aceptación legal no hay cuenta, tampoco
con Google).

Las referencias `OB-n` están en [`../ui/account-creation.md`](../ui/account-creation.md).

---

## Alcance no cubierto por el material de partida

Áreas que la plataforma necesitará y que ni los PDFs ni esta documentación detallan todavía:

- administración y moderación de la plataforma (`V-1`);
- verificación de email y políticas antifraude (`S-3`);
- límites de uso y protección frente al scraping del catálogo (`S-4`, `S-5`);
- exportación o descarga de obras y de feedback;
- términos legales, privacidad y consentimientos;
- analítica de producto.

Se registrarán como funcionalidades en cuanto entren en alcance.
