# Registro maestro de funcionalidades

Índice único de todo lo que la plataforma debe hacer, con su estado. **Es la fuente de
verdad sobre el alcance.**

- Los valores de estado están definidos en [`../conventions.md`](../conventions.md#4-estados).
- `Spec` = madurez de la especificación. `Impl` = estado del código. Son independientes.
- Cuando una funcionalidad se detalla, se crea su ficha y se enlaza en la columna `Ficha`.
  Mientras tanto la fila es toda la información que existe.
- Ninguna funcionalidad se implementa sin ficha en `APPROVED`.

**Última actualización: 2026-09-25**

---

## Resumen

| Contexto | Total | Con ficha | `APPROVED` | `REVIEW` | `DONE` | `PARTIAL` |
|---|---|---|---|---|---|---|
| `User` (USR) | 44 | 30 | 30 | 0 | 12 | 10 |
| `Work` (WRK) | 17 | 12 | 12 | 0 | 3 | 9 |
| `Reading` (RDG) | 10 | 7 | 7 | 0 | 7 | 0 |
| `Feedback` (FBK) | 12 | 8 | 7 | 0 | 5 | 1 |
| `Community` (COM) | 38 | 11 | 10 | 0 | 2 | 1 |
| `Moderation` (MOD) | 12 | 11 | 11 | 0 | 2 | 5 |
| `Credits` (CRD) | 19 | 13 | 13 | 0 | 4 | 5 |
| `Notification` (NOT) | 9 | 3 | 3 | 0 | 2 | 1 |
| **Total** | **161** | **95** | **93** | **0** | **37** | **32** |

`APPROVED` y `REVIEW` son estados de la **especificación**; `DONE` y `PARTIAL`, del **código**.
Las columnas no suman entre sí a propósito: una funcionalidad aprobada y a medio implementar
cuenta en las dos mitades.

Estado global: **la especificación está cerrada en su mayor parte y hay backend funcionando**.
28 funcionalidades están implementadas por completo y otras 33 a medias, con lo que les falta
escrito en su ficha. El ciclo de la cuenta —registro, activación, sesión, contraseña, correo,
consentimiento legal— está entero; el económico —precio, cobro, abono, descubierto— también;
y la moderación resuelve reclamaciones y bloquea obras.

El bloque en curso, aprobado el 2026-09-25: el ciclo de vida de una obra (`FEAT-WRK-003`,
`005`, `006`, `008`, `015`), leer y responder lo que se recibe (`FEAT-FBK-004`, `005`, `006`,
`010`), y las deudas de transparencia (`FEAT-CRD-008`, `FEAT-MOD-007`, `FEAT-USR-043`,
`FEAT-USR-044`).

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

El **sistema de créditos** se ha rediseñado de cero y está definido en
[`decision:0006`](../decisions/0006-credit-system.md). Es la fuente de verdad del modelo
económico; las fichas `FEAT-CRD-*` lo desarrollan.

---

## `User` — cuenta, identidad y perfil

Ficha del contexto: [`../bounded-contexts/user.md`](../bounded-contexts/user.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-USR-001 | Registro con email y contraseña | Guest | APPROVED | PARTIAL | P0 | [ficha](user/FEAT-USR-001-register-with-email.md) |
| FEAT-USR-002 | Registro con cuenta de Google | Guest | APPROVED | TODO | P0 | [ficha](user/FEAT-USR-002-register-with-google.md) |
| FEAT-USR-003 | Registro con cuenta de Facebook | Guest | PENDING | DEFERRED | P3 | — |
| FEAT-USR-004 | Login con email y contraseña, y ciclo de vida de la sesión | Guest | APPROVED | DONE | P0 | [ficha](user/FEAT-USR-004-login-with-email.md) |
| FEAT-USR-005 | Login con cuenta de Google | Guest | PENDING | TODO | P0 | — |
| FEAT-USR-006 | Login con cuenta de Facebook | Guest | PENDING | DEFERRED | P3 | — |
| FEAT-USR-007 | Recuperar la contraseña | Guest | APPROVED | DONE | P0 | [ficha](user/FEAT-USR-007-recover-password.md) |
| FEAT-USR-008 | Editar el perfil — nombre, usuario, biografía, géneros y foto | User | APPROVED | PARTIAL | P1 | [ficha](user/FEAT-USR-008-edit-profile.md) |
| FEAT-USR-009 | Editar preferencias literarias | User | APPROVED | DONE | P2 | [ficha](user/FEAT-USR-009-literary-preferences.md) |
| FEAT-USR-010 | Configurar recepción de mensajes directos | User | PENDING | TODO | P2 | — |
| FEAT-USR-011 | Configurar recepción de propuestas de LB y writing buddy | User | PENDING | TODO | P2 | — |
| FEAT-USR-012 | ~~Configurar notificaciones por email~~ → `FEAT-USR-039` | User | PENDING | DEPRECATED | P3 | — |
| FEAT-USR-013 | Eliminar la cuenta — anonimización | User | APPROVED | BLOCKED | P2 | [ficha](user/FEAT-USR-013-delete-account.md) |
| FEAT-USR-014 | Ver perfil público de un usuario | User, Guest | APPROVED | PARTIAL | P1 | [ficha](user/FEAT-USR-014-view-public-profile.md) |
| FEAT-USR-015 | Configurar información de la página de autor (bio, foto, referencias) | Writer | PENDING | TODO | P2 | — |
| FEAT-USR-016 | Personalizar página de autor (fuentes, colores, fondos) | Writer | PENDING | TODO | P3 | — |
| FEAT-USR-017 | Buscar autores por nombre o temática | User | PENDING | TODO | P1 | — |
| FEAT-USR-018 | Invitar a personas a la plataforma por email | User | PENDING | TODO | P2 | — |
| FEAT-USR-019 | Registro y login con LinkedIn | Guest | APPROVED | DEFERRED | P3 | [ficha](user/FEAT-USR-019-linkedin-oauth.md) |
| FEAT-USR-020 | Activar la cuenta desde el enlace enviado por email | Guest, User | APPROVED | PARTIAL | P0 | [ficha](user/FEAT-USR-020-activate-account.md) |
| FEAT-USR-021 | Reenviar el email de activación | Guest, User | APPROVED | DONE | P1 | [ficha](user/FEAT-USR-021-resend-activation-email.md) |
| FEAT-USR-022 | Onboarding paso 1 — nombre y fecha de nacimiento | User | APPROVED | DONE | P0 | [ficha](user/FEAT-USR-022-onboarding-profile-data.md) |
| FEAT-USR-023 | Onboarding paso 2 — elegir al menos tres géneros | User | APPROVED | DONE | P0 | [ficha](user/FEAT-USR-023-onboarding-select-genres.md) |
| FEAT-USR-024 | Aceptar condiciones de uso y política de privacidad | Guest | APPROVED | DONE | P0 | [ficha](user/FEAT-USR-024-accept-terms-and-privacy.md) |
| FEAT-USR-025 | Bloquear las operaciones de escritura hasta activar la cuenta | User | APPROVED | PARTIAL | P0 | [ficha](user/FEAT-USR-025-block-writes-until-activation.md) |
| FEAT-USR-026 | Tour de bienvenida de la Home | User | APPROVED | TODO | P2 | [ficha](user/FEAT-USR-026-welcome-tour.md) |
| FEAT-USR-027 | Contexto de sesión para el layout | User | APPROVED | DONE | P1 | [ficha](user/FEAT-USR-027-session-context.md) |
| FEAT-USR-028 | Mi perfil — cabecera, datos y contadores | User | APPROVED | PARTIAL | P1 | [ficha](user/FEAT-USR-028-own-profile-header.md) |
| FEAT-USR-029 | Obras publicadas del autor (bibliografía externa) | Writer | APPROVED | TODO | P2 | [ficha](user/FEAT-USR-029-published-books.md) |
| FEAT-USR-030 | Premios y reconocimientos del autor | Writer | PENDING | TODO | P3 | — *(sin diseño)* |
| FEAT-USR-031 | ~~Nivel del usuario~~ | — | PENDING | DEPRECATED | P3 | — |
| FEAT-USR-032 | Compartir el perfil | User | PENDING | TODO | P3 | — |
| FEAT-USR-033 | Nombre de usuario — formato, asignación automática y unicidad | Guest, User | APPROVED | PARTIAL | P0 | [ficha](user/FEAT-USR-033-username-assignment.md) |
| FEAT-USR-034 | Cambiar el nombre de usuario y alias temporal | User | APPROVED | PARTIAL | P1 | [ficha](user/FEAT-USR-034-change-username.md) |
| FEAT-USR-035 | Resolver un perfil por nombre de usuario o alias | Guest, User | APPROVED | DONE | P1 | [ficha](user/FEAT-USR-035-resolve-profile-by-username.md) |
| FEAT-USR-036 | Purga programada de alias caducados | — (sistema) | APPROVED | TODO | P2 | [ficha](user/FEAT-USR-036-purge-expired-aliases.md) |
| FEAT-USR-037 | Gestionar la foto de perfil — subir, editar y eliminar | User | APPROVED | DONE | P2 | [ficha](user/FEAT-USR-037-upload-profile-photo.md) |
| FEAT-USR-038 | Ajustes de privacidad del usuario | User | APPROVED | PARTIAL | P1 | [ficha](user/FEAT-USR-038-privacy-settings.md) |
| FEAT-USR-039 | Preferencias de notificación por canal | User | APPROVED | TODO | P2 | [ficha](user/FEAT-USR-039-notification-preferences.md) |
| FEAT-USR-040 | Cambiar el correo de la cuenta | User | APPROVED | DONE | P1 | [ficha](user/FEAT-USR-040-change-email.md) |
| FEAT-USR-041 | Cambiar o establecer la contraseña | User | APPROVED | DONE | P1 | [ficha](user/FEAT-USR-041-change-password.md) |
| FEAT-USR-042 | Preferencias de apariencia (tema) | User | PENDING | TODO | P3 | — *(sin captura)* |
| FEAT-USR-043 | Preferencias de contenido sensible | User | APPROVED | PARTIAL | P1 | [ficha](user/FEAT-USR-043-content-preferences.md) |
| FEAT-USR-044 | Filtrado automático de contenido por edad | User, Guest | APPROVED | PARTIAL | P0 | [ficha](user/FEAT-USR-044-age-based-content-filtering.md) |

> **`FEAT-USR-013`: eliminar una cuenta la anonimiza** (`S-32`, decidida). Se suprime todo
> dato personal y se conserva, sin autor identificable, lo que pertenece a terceros: las
> correcciones que otros autores **pagaron**, los movimientos de créditos y los comentarios
> en conversaciones ajenas. Sigue `BLOCKED` por `U-3` —qué pasa con las obras propias— y
> `V-4` —qué pasa con los mensajes directos—. **El texto de la advertencia hay que
> reescribirlo**: promete un borrado total que no va a ocurrir.
>
> `FEAT-USR-038` a `FEAT-USR-042` salen de la pantalla de **Configuración**. `FEAT-USR-039`
> absorbe a `FEAT-USR-012`, que solo contemplaba el correo y queda `DEPRECATED`.
>
> **`FEAT-USR-034` y `FEAT-USR-009` ya tienen interfaz**: la pestaña «Perfil» permite cambiar
> también el **nombre de usuario** y las **preferencias literarias**, aunque las capturas no
> los muestren. El nombre de usuario conserva sus reglas propias —una vez cada 30 días, alias
> del anterior— pese a compartir el «Guardar» de la pestaña.
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
| FEAT-WRK-001 | Crear obra con el editor WYSIWYG | Writer | APPROVED | PARTIAL | P0 | [ficha](work/FEAT-WRK-001-create-work-with-editor.md) |
| FEAT-WRK-002 | Crear obra subiendo un fichero (.doc, .pdf, .txt) | Writer | PENDING | TODO | P1 | — |
| FEAT-WRK-003 | Estructurar la obra en capítulos | Writer | APPROVED | DONE | P0 | [ficha](work/FEAT-WRK-003-structure-work-in-chapters.md) |
| FEAT-WRK-004 | Ver una obra y el contenido de sus capítulos | Writer, BetaReader, User | APPROVED | PARTIAL | P0 | [ficha](work/FEAT-WRK-004-read-a-work.md) |
| FEAT-WRK-005 | Editar una obra o un capítulo | Writer | APPROVED | DONE | P0 | [ficha](work/FEAT-WRK-005-edit-work-and-chapter.md) |
| FEAT-WRK-006 | Eliminar una obra con confirmación | Writer | APPROVED | DONE | P1 | [ficha](work/FEAT-WRK-006-delete-work.md) |
| FEAT-WRK-007 | Configurar la modalidad de acceso de lectores beta | Writer | APPROVED | PARTIAL | P0 | [ficha](work/FEAT-WRK-007-configure-access-mode.md) |
| FEAT-WRK-008 | Configurar la visibilidad de obra y capítulos | Writer | APPROVED | PARTIAL | P1 | [ficha](work/FEAT-WRK-008-work-and-chapter-visibility.md) |
| FEAT-WRK-009 | Generar el registro de autoría | Writer | PENDING | BLOCKED | P1 | — |
| FEAT-WRK-010 | Crear un enlace público para leer y corregir sin sesión | Writer | APPROVED | DONE | P2 | [ficha](work/FEAT-WRK-010-public-correction-link.md) |
| FEAT-WRK-011 | Generar enlace para compartir en redes sociales y captar LB | Writer | PENDING | TODO | P2 | — |
| FEAT-WRK-012 | Sección «Leer» — catálogo con filtros y ordenación | User | APPROVED | PARTIAL | P1 | [ficha](work/FEAT-WRK-012-browse-catalogue.md) |
| FEAT-WRK-013 | Calcular el número de palabras y el nivel de extensión | — (sistema) | PENDING | TODO | P0 | — |
| FEAT-WRK-014 | Definir el cuestionario que acompaña a la obra | Writer | APPROVED | PARTIAL | P0 | [ficha](work/FEAT-WRK-014-configure-questionnaire.md) |
| FEAT-WRK-015 | Mis relatos — listado con filtros y ordenación | Writer | APPROVED | PARTIAL | P1 | [ficha](work/FEAT-WRK-015-my-works-list.md) |
| FEAT-WRK-016 | Estado de una obra — borrador, visible y en corrección | Writer | APPROVED | PARTIAL | P0 | [ficha](work/FEAT-WRK-016-work-status.md) |
| FEAT-WRK-017 | Clasificación de contenido sensible de una obra | Writer | APPROVED | PARTIAL | P1 | [ficha](work/FEAT-WRK-017-content-rating.md) |

> `FEAT-WRK-009` está `BLOCKED`: el documento de origen deja explícitamente abierto en qué
> momentos se genera el registro (`W-1`).
>
> **`FEAT-WRK-016` resuelve qué es «una obra en corrección»**: un estado del ciclo de vida.
> Una obra está en `DRAFT`, `PUBLISHED` o `IN_CORRECTION`, y solo en `IN_CORRECTION` admite
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
| FEAT-RDG-001 | Convertirse en LB al empezar a corregir (obra `PUBLIC`) | Reader | APPROVED | DONE | P0 | [ficha](reading/FEAT-RDG-001-become-beta-reader-by-correcting.md) |
| FEAT-RDG-002 | Solicitar ser LB de una obra (obra `ON_REQUEST`) | Reader | APPROVED | DONE | P0 | [ficha](reading/FEAT-RDG-002-request-beta-reader-access.md) |
| FEAT-RDG-003 | Aceptar o rechazar una solicitud de LB | Writer | APPROVED | DONE | P0 | [ficha](reading/FEAT-RDG-003-resolve-access-request.md) |
| FEAT-RDG-004 | Invitar a un usuario a ser LB de una obra | Writer | APPROVED | DONE | P1 | [ficha](reading/FEAT-RDG-004-invite-beta-reader.md) |
| FEAT-RDG-005 | Aceptar o rechazar una invitación de LB | Reader | APPROVED | DONE | P1 | [ficha](reading/FEAT-RDG-005-resolve-invitation.md) |
| FEAT-RDG-006 | Buscar lectores beta a quienes invitar | Writer | APPROVED | DONE | P2 | [ficha](reading/FEAT-RDG-006-find-beta-readers.md) |
| FEAT-RDG-007 | Gestionar grupos de lectores beta (CRUD y miembros) | Writer | PENDING | TODO | P2 | — |
| FEAT-RDG-008 | Proponer a un usuario ser writing buddy | Writer | PENDING | TODO | P2 | — |
| FEAT-RDG-009 | Aceptar o rechazar una propuesta de writing buddy | User | PENDING | TODO | P2 | — |
| FEAT-RDG-010 | Revocar el acceso de un lector beta | Writer | APPROVED | DONE | P3 | [ficha](reading/FEAT-RDG-010-revoke-beta-reader-access.md) |

> **`R-4` resuelta (2026-09-24):** en una obra `PUBLIC`, **empezar a corregir concede el
> acceso**. No hay solicitud previa ni espera. `ON_REQUEST` y `PRIVATE` mantienen su flujo.
> `FEAT-RDG-001` deja de ser una pantalla y pasa a ser **el efecto en `Reading`** de un hecho
> que ocurre en `Feedback`: no tiene endpoint ninguno.
>
> El motivo es de producto: el recorrido es *descubrir → leer → corregir*, y meter una espera
> en medio cae justo donde el lector estaba dispuesto a trabajar gratis.
>
> `FEAT-RDG-010` no aparecía en el material de partida y se registró porque su ausencia era
> probablemente un olvido (`A-4`, `R-1`). **Confirmado**: se entraba a una obra por tres
> caminos y no había forma de salir salvo bloquear a la persona. Ya la hay.

---

## `Feedback` — comentarios y valoraciones

Ficha del contexto: [`../bounded-contexts/feedback.md`](../bounded-contexts/feedback.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-FBK-001 | Dejar feedback sobre una obra o fragmento | BetaReader | PENDING | TODO | P0 | — |
| FEAT-FBK-002 | Valorar una obra | BetaReader | PENDING | TODO | P1 | — |
| FEAT-FBK-003 | Responder y enviar el cuestionario de corrección | BetaReader | APPROVED | PARTIAL | P0 | [ficha](feedback/FEAT-FBK-003-answer-correction-questionnaire.md) |
| FEAT-FBK-004 | Ver las correcciones recibidas | Writer | APPROVED | DONE | P0 | [ficha](feedback/FEAT-FBK-004-read-received-corrections.md) |
| FEAT-FBK-005 | Contestar a una corrección recibida | Writer | APPROVED | DONE | P1 | [ficha](feedback/FEAT-FBK-005-reply-to-correction.md) |
| FEAT-FBK-006 | Valorar una corrección recibida | Writer | APPROVED | DONE | P1 | [ficha](feedback/FEAT-FBK-006-rate-correction.md) |
| FEAT-FBK-007 | Ocultar un comentario recibido | Writer | PENDING | TODO | P2 | — |
| FEAT-FBK-008 | Corregir por enlace público sin cuenta | Guest | APPROVED | DONE | P1 | [ficha](feedback/FEAT-FBK-008-public-link-correction.md) |
| FEAT-FBK-009 | Denunciar una corrección abusiva → `FEAT-MOD-001` | User | PENDING | TODO | P2 | — |
| FEAT-FBK-010 | Mis correcciones — lo que he corregido | BetaReader | APPROVED | DONE | P2 | [ficha](feedback/FEAT-FBK-010-my-corrections.md) |
| FEAT-FBK-011 | Guardar un borrador de corrección | BetaReader | APPROVED | DONE | P1 | [ficha](feedback/FEAT-FBK-011-save-correction-draft.md) |
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
> **`FEAT-FBK-008` deja de estar `BLOCKED`.** `A-3`, `C-5` y `F-6` están resueltas: el
> corrector anónimo se identifica con **una etiqueta opcional y sin verificar**, y la
> operación **no mueve créditos en ninguna dirección**. Además de dar feedback al autor, es
> una **válvula de seguridad de la economía** —un autor a cero siempre tiene una salida— y el
> mejor canal de captación disponible, porque la persona ya ha hecho el trabajo antes de que
> se le pida registrarse.
> `FEAT-FBK-009` no está en el material de partida; depende de que exista moderación (`V-1`).

---

## `Community` — muro, social y rankings

Ficha del contexto: [`../bounded-contexts/community.md`](../bounded-contexts/community.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-COM-001 | Ver las publicaciones del muro principal | User | APPROVED | DONE | P1 | [ficha](community/FEAT-COM-001-main-wall.md) |
| FEAT-COM-002 | Crear una publicación (texto, imagen, vídeo, enlace o relato) | User | APPROVED | DONE | P1 | [ficha](community/FEAT-COM-002-create-post.md) |
| FEAT-COM-003 | Publicar buscando lectores beta para una obra | Writer | PENDING | TODO | P1 | — |
| FEAT-COM-004 | Publicar buscando writing buddy | Writer | PENDING | TODO | P2 | — |
| FEAT-COM-005 | Publicar ofreciéndose como lector beta | Reader | PENDING | TODO | P2 | — |
| FEAT-COM-006 | Comentar una publicación | User | APPROVED | PARTIAL | P2 | [ficha](community/FEAT-COM-006-comment-on-post.md) |
| FEAT-COM-007 | Reaccionar con emoji a una publicación | User | PENDING | TODO | P2 | — |
| FEAT-COM-008 | Apoyar una publicación con un "me gusta" | User | PENDING | TODO | P2 | — |
| FEAT-COM-009 | Filtrar y buscar publicaciones (tipo, texto, usuario, fecha) | User | PENDING | TODO | P2 | — |
| FEAT-COM-010 | Suscribirse a un autor | User | APPROVED | DONE | P2 | [ficha](community/FEAT-COM-010-subscribe-to-author.md) |
| FEAT-COM-011 | Enviar un mensaje directo | User | PENDING | TODO | P2 | — |
| FEAT-COM-012 | Ver y gestionar conversaciones de mensajes directos | User | PENDING | TODO | P2 | — |
| FEAT-COM-013 | Ver y filtrar el ranking de escritores | User | PENDING | BLOCKED | P2 | — |
| FEAT-COM-014 | Ver y filtrar el ranking de obras | User | PENDING | BLOCKED | P2 | — |
| FEAT-COM-015 | Ver y filtrar el ranking de lectores | User | PENDING | BLOCKED | P2 | — |
| FEAT-COM-016 | Onboarding paso 3 — sugerencias de autores a seguir | User | APPROVED | PARTIAL | P1 | [ficha](community/FEAT-COM-016-onboarding-author-suggestions.md) |
| FEAT-COM-017 | Home — carrusel de obras recomendadas | User | DRAFT | TODO | P1 | [ficha](community/FEAT-COM-017-home-work-recommendations.md) |
| FEAT-COM-018 | Home — sugerencias de autores en el muro | User | APPROVED | PARTIAL | P1 | [ficha](community/FEAT-COM-018-home-author-suggestions.md) |
| FEAT-COM-019 | Repostear una publicación | User | APPROVED | DONE | P2 | [ficha](community/FEAT-COM-019-repost.md) |
| FEAT-COM-020 | Compartir una publicación fuera de la plataforma | User | PENDING | TODO | P2 | — |
| FEAT-COM-021 | Guardar una publicación | User | PENDING | TODO | P3 | — |
| FEAT-COM-022 | Ocultar una publicación del muro | User | PENDING | TODO | P3 | — |
| FEAT-COM-023 | Denunciar una publicación | User | PENDING | DEFERRED | P3 | — |
| FEAT-COM-024 | Ordenar el muro por relevancia o por fecha | User | PENDING | BLOCKED | P2 | — |
| FEAT-COM-025 | Búsqueda global desde la cabecera | User | PENDING | DEFERRED | P3 | — |
| FEAT-COM-026 | Mi muro — publicaciones propias | User | PENDING | TODO | P2 | — |
| FEAT-COM-027 | Mis Amigos — seguidos y seguidores | User, Guest | APPROVED | DONE | P2 | [ficha](community/FEAT-COM-027-following-and-followers.md) |
| FEAT-COM-028 | Incluir un relato de la plataforma en una publicación | User | PENDING | TODO | P2 | — |
| FEAT-COM-029 | Audiencia de una publicación | User | PENDING | BLOCKED | P1 | — |
| FEAT-COM-030 | Me gusta en un comentario o respuesta | User | PENDING | TODO | P2 | — |
| FEAT-COM-031 | Responder a un comentario | User | APPROVED | PARTIAL | P2 | [ficha](community/FEAT-COM-031-reply-to-comment.md) |
| FEAT-COM-032 | Menciones a usuarios | User | APPROVED | PARTIAL | P2 | [ficha](community/FEAT-COM-032-mentions.md) |
| FEAT-COM-033 | Silenciar a un usuario | User | PENDING | TODO | P3 | — |
| FEAT-COM-034 | Bloquear a un usuario | User | APPROVED | PARTIAL | P2 | [ficha](community/FEAT-COM-034-block-user.md) |
| FEAT-COM-035 | Denunciar a un usuario → `FEAT-MOD-001` | User | PENDING | TODO | P2 | — |
| FEAT-COM-036 | Interacciones sociales sobre un capítulo (like, comentario, compartir) | User | PENDING | TODO | P1 | — |
| FEAT-COM-037 | Adjuntar vídeo a una publicación | User | PENDING | DEFERRED | P3 | — |
| FEAT-COM-038 | Publicaciones de la plataforma (cuenta institucional) | Admin | PENDING | TODO | P2 | — |

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

## `Moderation` — reclamaciones, sanciones y backoffice

Ficha del contexto: [`../bounded-contexts/moderation.md`](../bounded-contexts/moderation.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-MOD-001 | Presentar una reclamación | User, Writer | APPROVED | PARTIAL | P1 | [ficha](moderation/FEAT-MOD-001-submit-claim.md) |
| FEAT-MOD-002 | Revisar y resolver una reclamación | Moderator | APPROVED | PARTIAL | P1 | [ficha](moderation/FEAT-MOD-002-review-claim.md) |
| FEAT-MOD-003 | Bloquear una obra por reclamación estimada | — (sistema) | APPROVED | PARTIAL | P1 | [ficha](moderation/FEAT-MOD-003-block-work.md) |
| FEAT-MOD-004 | Rol de moderador y aviso de reclamaciones | Admin, Moderator | APPROVED | PARTIAL | P1 | [ficha](moderation/FEAT-MOD-004-moderator-role.md) |
| FEAT-MOD-005 | Gestión de usuarios desde el backoffice | Admin, Moderator | APPROVED | TODO | P2 | [ficha](moderation/FEAT-MOD-005-user-management.md) |
| FEAT-MOD-006 | Catálogo de sanciones | Moderator | APPROVED | PARTIAL | P2 | [ficha](moderation/FEAT-MOD-006-sanctions.md) |
| FEAT-MOD-007 | Registro de auditoría de acciones administrativas | Admin | APPROVED | DONE | P1 | [ficha](moderation/FEAT-MOD-007-audit-log.md) |
| FEAT-MOD-008 | Cola de reclamaciones con filtros y prioridad | Moderator | PENDING | TODO | P2 | — |
| FEAT-MOD-009 | Conversación entre el moderador y las partes | Moderator, User | APPROVED | PARTIAL | P1 | [ficha](moderation/FEAT-MOD-009-moderator-conversation.md) |
| FEAT-MOD-010 | Mis reclamaciones — sección del usuario | User | APPROVED | PARTIAL | P1 | [ficha](moderation/FEAT-MOD-010-my-claims.md) |
| FEAT-MOD-011 | Revisión automática de contenido | — (sistema) | APPROVED | TODO | P1 | [ficha](moderation/FEAT-MOD-011-automated-content-review.md) |
| FEAT-MOD-012 | Comando de creación del primer administrador | Admin | APPROVED | DONE | P1 | [ficha](moderation/FEAT-MOD-012-bootstrap-admin.md) |

> **`Moderation` existe desde el 2026-09-23** y con él desaparece `V-1`, que bloqueaba las
> denuncias desde el principio: no había moderación que las atendiera. Ahora la hay.
>
> **La reclamación no produce ningún efecto inmediato.** Ni oculta el contenido, ni congela
> créditos, ni avisa al reclamado. Lo contrario convertiría el botón de denunciar en un arma.
> El coste es que un contenido dañino permanece visible hasta que alguien lo revise (`MOD-3`).
>
> **El riesgo que hay que cerrar antes de implementar** es `MOD-2`: estimar una reclamación de
> corrección devuelve créditos al autor, así que reclamar es **una forma de no pagar**. Si el
> sistema no distingue una crítica dura de una corrección fraudulenta, los correctores
> aprenderán a escribir elogios. Hacen falta límite de reclamaciones, consecuencia por
> reclamar en falso y criterios escritos para el moderador.
>
> **El catálogo de sanciones** son cuatro familias (`FEAT-MOD-006`): aviso, suspensión parcial
> —**solo lectura**: entra y lee, no publica, comenta ni corrige— de 3 días, 1 semana o 1 mes;
> suspensión total **indefinida hasta que alguien la revoque**; y expulsión, que deja la cuenta
> en **`BLOCKED`, sin anonimizar**.
>
> **La expulsión no anonimiza a propósito**: no se puede a la vez borrar a alguien y recordarlo
> para impedirle volver. Si anonimizase, la sanción más grave del catálogo sería la más fácil
> de esquivar. Es la diferencia con darse de baja voluntariamente, que sí anonimiza.
>
> **La puerta de reclamar nunca se cierra del todo** (`MOD-22`): quien tiene el botón
> bloqueado ve un enlace para escribir por correo, y un moderador registra la reclamación en
> su nombre. Más lenta, pero nadie se queda sin forma de avisar de algo serio.
>
> **El bloqueo es por capítulo**, y una obra con **3 capítulos bloqueados** queda bloqueada
> entera (`FEAT-MOD-003`). El recurso del autor es **por correo**, no por la plataforma.
>
> **Hay conversación con el moderador** (`FEAT-MOD-009`), en forma de estrella: cada parte
> tiene un hilo privado con él y **no ve el de la otra**. Poner a denunciante y denunciado a
> discutir crearía el conflicto que la moderación existe para evitar. El usuario lo sigue todo
> desde su sección de reclamaciones (`FEAT-MOD-010`).
>
> **La revisión automática se construye ahora y vacía** (`FEAT-MOD-011`): un puerto con una
> implementación que aprueba todo, para poder sustituirla por IA sin abrir el flujo de
> publicación. El coste hoy es casi cero y el ahorro después, grande.
>
> **El primer `Admin` se crea por comando** (`FEAT-MOD-012`), nunca desde la API. El `Admin`
> es además el último recurso cuando una reclamación afecta a todos los moderadores.

---

## `Credits` — economía de la plataforma

Ficha del contexto: [`../bounded-contexts/credits.md`](../bounded-contexts/credits.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-CRD-001 | Consultar el saldo de créditos | User | APPROVED | DONE | P0 | [ficha](credits/FEAT-CRD-001-check-credit-balance.md) |
| FEAT-CRD-002 | Abonar los créditos de bienvenida al **activar** la cuenta (+10) | — (sistema) | APPROVED | PARTIAL | P0 | [ficha](credits/FEAT-CRD-002-welcome-credit-grant.md) |
| FEAT-CRD-003 | ~~Abonar créditos por dar feedback según nivel del texto~~ → `FEAT-CRD-016` | — | PENDING | DEPRECATED | P3 | — |
| FEAT-CRD-004 | ~~Abonar +5 por feedback valorado positivamente~~ → `FEAT-CRD-017` | — | PENDING | DEPRECATED | P3 | — |
| FEAT-CRD-005 | Abonar +5 al invitador cuando el invitado entrega su primera corrección | — (sistema) | PENDING | TODO | P2 | — |
| FEAT-CRD-006 | Cargar al autor y abonar al lector al entregarse la corrección | — (sistema) | APPROVED | PARTIAL | P0 | [ficha](credits/FEAT-CRD-006-charge-author-for-received-feedback.md) |
| FEAT-CRD-007 | ~~Coste adicional por preguntas extra del cuestionario~~ → `FEAT-CRD-016` | — | PENDING | DEPRECATED | P3 | — |
| FEAT-CRD-008 | Consultar el historial de movimientos de créditos | User | APPROVED | DONE | P1 | [ficha](credits/FEAT-CRD-008-credit-history.md) |
| FEAT-CRD-009 | Comprobar el saldo al empezar una corrección | — (sistema) | APPROVED | PARTIAL | P0 | [ficha](credits/FEAT-CRD-009-balance-check-on-correction-start.md) |
| FEAT-CRD-010 | ~~Fórmula continua en vez de tramos~~ → adoptada en `FEAT-CRD-016` | — | PENDING | DEPRECATED | P3 | — |
| FEAT-CRD-011 | Deduplicar eventos para garantizar idempotencia | — (sistema) | APPROVED | PARTIAL | P0 | [ficha](credits/FEAT-CRD-011-deduplicate-integration-events.md) |
| FEAT-CRD-012 | Salud de la economía de créditos | Admin | APPROVED | DONE | P1 | [ficha](credits/FEAT-CRD-012-economy-health.md) |
| FEAT-CRD-013 | Créditos asociados a un capítulo (insignia de la tarjeta) | User | APPROVED | DONE | P2 | [ficha](credits/FEAT-CRD-013-work-credit-badge.md) |
| FEAT-CRD-014 | Modal informativo del sistema de créditos | User | APPROVED | TODO | P2 | [ficha](credits/FEAT-CRD-014-credits-info-modal.md) |
| FEAT-CRD-015 | Pantalla explicativa de cómo se calcula el precio | User | PENDING | TODO | P2 | — |
| FEAT-CRD-016 | Precio de una corrección según el esfuerzo | — (sistema) | APPROVED | PARTIAL | P0 | [ficha](credits/FEAT-CRD-016-effort-based-pricing.md) |
| FEAT-CRD-017 | Propina del autor a una buena corrección | Writer | APPROVED | DONE | P2 | [ficha](credits/FEAT-CRD-017-author-tip.md) |
| FEAT-CRD-018 | Saldo negativo y correcciones bloqueadas | — (sistema) | APPROVED | DONE | P0 | [ficha](credits/FEAT-CRD-018-negative-balance.md) |
| FEAT-CRD-019 | Corrección en descubierto como gancho de reactivación | — (sistema) | APPROVED | TODO | P2 | [ficha](credits/FEAT-CRD-019-overdraft-correction.md) |

> **El sistema de créditos se ha rediseñado de cero**
> ([`decision:0006`](../decisions/0006-credit-system.md)). Nueve reglas:
>
> 1. **El precio mide esfuerzo**: `techo(palabras/1.000) + techo(palabras exigidas/100)`, por
>    capítulo, entre 2 y 20, con suelo de 25 palabras por pregunta sin mínimo
>    (`FEAT-CRD-016`).
> 2. **Coste = recompensa.** Una corrección mueve créditos, no los crea ni los destruye.
> 3. **No se retiene nada**: se comprueba el saldo al empezar y se cobra al entregar
>    (`FEAT-CRD-009`).
> 4. **El corrector cobra siempre**; el autor puede quedar en negativo y la corrección llega
>    **bloqueada** hasta que reponga (`FEAT-CRD-018`).
> 5. **Bienvenida: 10 créditos** al activar la cuenta.
> 6. **Invitación: +5** cuando el invitado entrega su primera corrección, tope 10.
> 7. **Propina** del autor, de su propio saldo (`FEAT-CRD-017`).
> 8. **El enlace público queda fuera de la economía** (`FEAT-FBK-008`).
> 9. **Corrección en descubierto** como gancho de reactivación, con **cupo periódico**
>    (`FEAT-CRD-019`).
>
> **El hallazgo que lo ordena todo:** con coste igual a recompensa, la masa total de créditos
> no depende del precio. El promedio por usuario es **siempre** el regalo de bienvenida, así
> que «que todos tengan demasiados créditos» es imposible por construcción. El riesgo real no
> es la inflación sino la **concentración**, y la fórmula de precios se puede ajustar sin
> poner en riesgo la economía.
>
> **Queda derogado** lo que el rediseño sustituye: los tramos por `TextTier` y el recargo por
> preguntas (`FEAT-CRD-003`, `FEAT-CRD-007`, `FEAT-CRD-010`), la bonificación automática por
> feedback valorado (`FEAT-CRD-004`, reemplazada por la propina), el margen dinámico entre
> coste y recompensa con su cuenta de sistema, y **la reserva de créditos entera**
> ([`decision:0004`](../decisions/0004-credit-reservation-on-access-grant.md) queda
> sustituida).
>
> **Lo que desaparece con la reserva:** el «saldo disponible» como concepto, los estados
> `HELD`/`CONFIRMED`/`RELEASED`, los plazos de caducidad, las liberaciones, las retenciones
> huérfanas y la compensación entre `Reading` y `Credits`. `R-1` deja de tener sentido: no hay
> nada que reservar.
>
> **Lo que se paga a cambio** es el descubierto por carrera, que **no será excepcional**:
> escribir una corrección lleva días, así que dos lectores coincidirán sobre el mismo capítulo
> con cierta frecuencia y el autor quedará en negativo, con una corrección bloqueada hasta que
> reponga. Es una consecuencia aceptada a conciencia: se prefiere a apartarle créditos al
> autor por una corrección que todavía no existe.

---

## `Notification` — avisos

Ficha del contexto: [`../bounded-contexts/notification.md`](../bounded-contexts/notification.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-NOT-001 | Entregar notificaciones in-app | — (sistema) | APPROVED | DONE | P1 | [ficha](notification/FEAT-NOT-001-in-app-notifications.md) |
| FEAT-NOT-002 | Entregar notificaciones por email | — (sistema) | PENDING | TODO | P1 | — |
| FEAT-NOT-003 | Aplicar las preferencias de notificación del usuario | — (sistema) | PENDING | TODO | P1 | — |
| FEAT-NOT-004 | Avisar de nueva obra o publicación de un autor suscrito | — (sistema) | PENDING | TODO | P2 | — |
| FEAT-NOT-005 | Avisar de solicitudes, invitaciones y propuestas | — (sistema) | PENDING | TODO | P1 | — |
| FEAT-NOT-006 | Avisar de feedback recibido, contestado o valorado | — (sistema) | PENDING | TODO | P1 | — |
| FEAT-NOT-007 | Enviar el email de invitación a la plataforma | — (sistema) | PENDING | TODO | P2 | — |
| FEAT-NOT-008 | Enviar el email de activación de cuenta | — (sistema) | APPROVED | PARTIAL | P0 | [ficha](notification/FEAT-NOT-008-account-activation-email.md) |
| FEAT-NOT-009 | Centro de notificaciones in-app y contador de no leídas | User | APPROVED | DONE | P1 | [ficha](notification/FEAT-NOT-009-notification-centre.md) |

---

## Funcionalidades bloqueadas

Resumen de lo que no se puede especificar hasta tomar una decisión de producto:

| Funcionalidad | Bloqueada por | Decisión necesaria |
|---|---|---|
| FEAT-COM-013/014/015 | `CM-4` | Fórmula de puntuación de cada ranking |
| FEAT-WRK-009 | `W-1` | Cuándo se genera el registro de autoría |
| FEAT-USR-013 | `V-4`, `U-3` | Qué se conserva al eliminar la cuenta. El nombre de usuario **sí** está decidido: queda bloqueado 30 días |
| FEAT-COM-024 | `CM-4` | Fórmula de relevancia para ordenar el muro |
| FEAT-COM-029, FEAT-COM-002 | `C-1` | Qué opciones tiene el selector de audiencia de una publicación |
| FEAT-COM-002 | `C-2` | Si se admite vídeo, con qué límites y si se transcodifica |
| FEAT-COM-019 | `C-3` | Si un repost es un puntero o una publicación con entidad propia |
| FEAT-COM-006, FEAT-COM-024, rankings | `CM-4` | Una única fórmula de relevancia para el muro, los comentarios y los rankings |
| ~~FEAT-WRK-016~~ | ~~`W-9`~~ | **Resuelto:** no lo sustituye. Son ejes distintos, y el de visibilidad se modelará cuando exista quien lea obras |
| FEAT-COM-034 | `B-2`, `B-3` | Si bloquear revoca el acceso de lector beta y si puede terminar quien ya estaba corrigiendo, y qué pasa con el feedback que el autor **ya pagó** |
| FEAT-USR-014 | `U-17` | Si el contador de correcciones es público y la lista no, de forma deliberada |
| **FEAT-FBK-012** | `AF-1`, `AF-2` | Qué mecanismo antifraude, y si actúa antes o después del abono |
| ~~FEAT-WRK-014~~ | ~~`W-17`~~ | **Resuelto:** cada pregunta declara su alcance, `EVERY_CHAPTER` o `LAST_CHAPTER`, y el precio de un capítulo cuenta solo las que aplican en él |
| FEAT-WRK-012 | `L-9` | Cómo llega la insignia de créditos al catálogo sin acoplar `Work` con `Credits` |
| FEAT-CRD-019 | `C-42`, `C-28` | Qué cupo de descubierto por periodo, y cómo se avisa al autor de que puede quedar en deuda |
| **FEAT-MOD-001, FEAT-MOD-002** | **`MOD-2`** | Límite de reclamaciones y consecuencia de reclamar en falso. Sin ello, reclamar es una forma gratuita de no pagar una corrección |
| FEAT-MOD-005, FEAT-MOD-006 | `MOD-1` | Catálogo de sanciones: qué existe y con qué gravedad |
| FEAT-MOD-004 | `MOD-6` | Cómo se crea el primer `Admin`. Sin él no hay forma de arrancar el backoffice |
| FEAT-USR-013 | `U-3`, `V-4` | Qué pasa con las **obras propias** y con los mensajes directos al anonimizar la cuenta |
| FEAT-USR-038 | `S-36` | Si endurecer el ajuste global permite terminar a quien ya estaba corrigiendo |
| FEAT-USR-038 | `S-13`, `S-16` | Qué opciones tienen los desplegables y qué es «visibilidad de actividad» |
| FEAT-USR-039 | `S-11`, `S-12` | Qué tipos de aviso existen, y si corrección y comentario se separan |

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

**Decisiones del 2026-09-23** sobre la pantalla de Configuración:

| # | Decisión |
|---|---|
| `S-32` | Eliminar una cuenta **la anonimiza**: desaparece la persona, permanece lo que pertenece a otros |
| `S-14` | El ajuste global de privacidad es un **techo**: una obra puede ser más restrictiva, nunca más permisiva |
| — | «Desactivar todas las notificaciones» **no afecta a los mensajes operativos** |
| `S-3`, `S-18` | La pestaña «Perfil» incluye también **nombre de usuario** y **preferencias literarias** |
| `S-4` | La biografía se amplía a **300 caracteres**, y es la descripción que se ve bajo la foto |
| `S-8` | Una cuenta de Google cambia su contraseña **dejando la actual en blanco** |
| `S-6` | La ausencia del modal de recorte es un **error de maqueta** |

Dos consecuencias que no existían antes de tomarlas:

- **`R-1` pasa a ser lo más urgente.** El acceso de lector beta se concede **por obra** y el
  coste ahora se devenga **por capítulo**. La reserva previa de `decision:0004` ya no cubre
  automáticamente lo que se va a gastar, y la decisión afecta a `Reading`, `Feedback` y
  `Credits` a la vez.
- **`FEAT-CRD-012` deja de ser opcional.** Sin medir, las dos formas de morir —nadie tiene
  créditos, o los créditos no valen nada— se descubren por las quejas, semanas tarde y con
  los saldos ya formados. Pasa de `DEFERRED`/`P3` a `TODO`/`P1`.

El sistema de créditos quedó cerrado el 2026-09-23 en
[`decision:0006`](../decisions/0006-credit-system.md): ya no hay ninguna decisión de producto
pendiente que impida implementarlo.

### Lo que queda sin aprobar

**63 de las 65 fichas están `APPROVED`.** Solo dos siguen sin aprobar, y ambas por el mismo
motivo: falta una decisión de producto que no se puede suplir con un valor por defecto.

| Ficha | Bloqueada por | Qué falta |
|---|---|---|
| `FEAT-FBK-012` — control antifraude | `AF-1`, `AF-2` | Qué mecanismo, y si actúa antes o después del abono |
| `FEAT-COM-017` — recomendaciones de la Home | `CM-4` (recomendaciones), `H-3` | Cómo se personaliza la recomendación y qué cuenta como lectura |

`CM-4` quedó resuelta para el **catálogo** y los **comentarios**
([`decision:0008`](../decisions/0008-catalogue-ordering.md) y `FEAT-COM-006`); las
**recomendaciones** y los **rankings** siguen aplazados a propósito, porque dependen de tener
usuarios y datos que hoy no existen.

### Decisiones que quedaron anotadas dentro de fichas aprobadas

Aprobar no significa que no quede nada que afinar. Estas afectan solo a constantes,
presentación o fases posteriores, y por eso no bloquean:

| Ref | Qué | Dónde |
|---|---|---|
| `V-4` | Qué ocurre con los mensajes directos al anonimizar una cuenta | `FEAT-USR-013` |
| `OB-15` | Edad mínima para registrarse, distinta de la mayoría de edad | `FEAT-USR-022` |
| ~~`OB-14`~~ | **Revisar el catálogo de géneros** | **Resuelto:** 18 géneros, los que siembra `Version20260923174500`. Queda confirmar con producto que `DRAMA` es «Teatro» y no «Drama» |
| `N-11` | **Confirmar `/@{username}`** como forma de la URL | `FEAT-USR-035` |
| `N-2` | Confirmar que el nombre **público** no necesita ser único | `FEAT-USR-022` |
| `S-16` | Qué se considera «visibilidad de actividad» | `FEAT-USR-038` |
| `S-41` | Qué se ve de un autor con el perfil en `NOBODY` | `FEAT-USR-038` |
| `C-2` | Vídeo en publicaciones → `FEAT-COM-037`, `DEFERRED` | `FEAT-COM-002` |
| `H-10` | Cómo funciona la cuenta institucional → `FEAT-COM-038` | `FEAT-COM-018` |
| `B-4` | Si bloquear a alguien con una corrección en curso debe quedar registrado | `FEAT-COM-034` |
| `MOD-44` | Qué se conserva de una cuenta expulsada si pide su supresión | `FEAT-MOD-006` |
| `MOD-37` | Si la revisión automática con IA saca obra inédita de la plataforma | `FEAT-MOD-011` |

Las referencias `OB-n` están en [`../ui/account-creation.md`](../ui/account-creation.md).

---

## Alcance no cubierto por el material de partida

Áreas que la plataforma necesitará y que ni los PDFs ni esta documentación detallan todavía:

- ~~administración y moderación de la plataforma~~ → **`Moderation`** (`V-1` resuelta);
- verificación de email y políticas antifraude (`S-3`);
- límites de uso y protección frente al scraping del catálogo (`S-4`, `S-5`);
- exportación o descarga de obras y de feedback;
- términos legales, privacidad y consentimientos;
- analítica de producto.

Se registrarán como funcionalidades en cuanto entren en alcance.
