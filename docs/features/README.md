# Registro maestro de funcionalidades

Índice único de todo lo que la plataforma debe hacer, con su estado. **Es la fuente de
verdad sobre el alcance.**

- Los valores de estado están definidos en [`../conventions.md`](../conventions.md#4-estados).
- `Spec` = madurez de la especificación. `Impl` = estado del código. Son independientes.
- Cuando una funcionalidad se detalla, se crea su ficha y se enlaza en la columna `Ficha`.
  Mientras tanto la fila es toda la información que existe.
- Ninguna funcionalidad se implementa sin ficha en `APPROVED`.

**Última actualización: 2026-09-21**

---

## Resumen

| Contexto | Funcionalidades | Con ficha | `APPROVED` | `DONE` |
|---|---|---|---|---|
| `User` (USR) | 18 | 1 | 0 | 0 |
| `Work` (WRK) | 14 | 1 | 0 | 0 |
| `Reading` (RDG) | 10 | 0 | 0 | 0 |
| `Feedback` (FBK) | 9 | 0 | 0 | 0 |
| `Community` (COM) | 15 | 0 | 0 | 0 |
| `Credits` (CRD) | 12 | 1 | 0 | 0 |
| `Notification` (NOT) | 7 | 0 | 0 | 0 |
| **Total** | **85** | **3** | **0** | **0** |

Estado global: **especificación inicial**. No hay código en `src/`.

---

## `User` — cuenta, identidad y perfil

Ficha del contexto: [`../bounded-contexts/user.md`](../bounded-contexts/user.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-USR-001 | Registro con email y contraseña | Guest | DRAFT | TODO | P0 | [ficha](user/FEAT-USR-001-register-with-email.md) |
| FEAT-USR-002 | Registro con cuenta de Google | Guest | PENDING | TODO | P1 | — |
| FEAT-USR-003 | Registro con cuenta de Facebook | Guest | PENDING | TODO | P2 | — |
| FEAT-USR-004 | Login con email y contraseña | Guest | PENDING | TODO | P0 | — |
| FEAT-USR-005 | Login con cuenta de Google | Guest | PENDING | TODO | P1 | — |
| FEAT-USR-006 | Login con cuenta de Facebook | Guest | PENDING | TODO | P2 | — |
| FEAT-USR-007 | Recuperar contraseña | Guest | PENDING | TODO | P0 | — |
| FEAT-USR-008 | Editar datos de usuario (email, nombre, contraseña, datos personales) | User | PENDING | TODO | P1 | — |
| FEAT-USR-009 | Editar preferencias literarias | User | PENDING | TODO | P2 | — |
| FEAT-USR-010 | Configurar recepción de mensajes directos | User | PENDING | TODO | P2 | — |
| FEAT-USR-011 | Configurar recepción de propuestas de LB y writing buddy | User | PENDING | TODO | P2 | — |
| FEAT-USR-012 | Configurar notificaciones por email | User | PENDING | TODO | P2 | — |
| FEAT-USR-013 | Eliminar cuenta con confirmación | User | PENDING | BLOCKED | P2 | — |
| FEAT-USR-014 | Ver perfil público de un usuario | User, Guest | PENDING | TODO | P1 | — |
| FEAT-USR-015 | Configurar información de la página de autor (bio, foto, referencias) | Writer | PENDING | TODO | P2 | — |
| FEAT-USR-016 | Personalizar página de autor (fuentes, colores, fondos) | Writer | PENDING | TODO | P3 | — |
| FEAT-USR-017 | Buscar autores por nombre o temática | User | PENDING | TODO | P1 | — |
| FEAT-USR-018 | Invitar a personas a la plataforma por email | User | PENDING | TODO | P2 | — |

> `FEAT-USR-013` está `BLOCKED`: no se puede especificar sin resolver qué ocurre con obras,
> feedback y créditos al eliminar la cuenta (`V-4`, `U-3`).

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
| FEAT-WRK-012 | Buscar obras por tipo, temática y valoración | Reader | PENDING | TODO | P1 | — |
| FEAT-WRK-013 | Calcular el número de palabras y el nivel de extensión | — (sistema) | PENDING | TODO | P0 | — |
| FEAT-WRK-014 | Definir el cuestionario que acompaña a la obra | Writer | PENDING | TODO | P1 | — |

> `FEAT-WRK-009` está `BLOCKED`: el documento de origen deja explícitamente abierto en qué
> momentos se genera el registro (`W-1`).

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
| FEAT-FBK-003 | Responder al cuestionario de la obra | BetaReader | PENDING | TODO | P1 | — |
| FEAT-FBK-004 | Ver los comentarios recibidos | Writer | PENDING | TODO | P0 | — |
| FEAT-FBK-005 | Contestar a un comentario recibido | Writer | PENDING | TODO | P1 | — |
| FEAT-FBK-006 | Valorar positivamente un comentario recibido | Writer | PENDING | TODO | P1 | — |
| FEAT-FBK-007 | Ocultar un comentario recibido | Writer | PENDING | TODO | P2 | — |
| FEAT-FBK-008 | Comentar mediante enlace público sin iniciar sesión | Guest | PENDING | BLOCKED | P2 | — |
| FEAT-FBK-009 | Denunciar un comentario abusivo | User | PENDING | DEFERRED | P3 | — |

> `FEAT-FBK-008` está `BLOCKED` por `A-3`, `C-5` y `F-6`: sin resolver si el comentarista
> anónimo se identifica y si la operación mueve créditos, no se puede especificar.
> `FEAT-FBK-009` no está en el material de partida; depende de que exista moderación (`V-1`).

---

## `Community` — muro, social y rankings

Ficha del contexto: [`../bounded-contexts/community.md`](../bounded-contexts/community.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-COM-001 | Ver las publicaciones del muro principal | User | PENDING | TODO | P1 | — |
| FEAT-COM-002 | Crear una publicación personalizada | User | PENDING | TODO | P1 | — |
| FEAT-COM-003 | Publicar buscando lectores beta para una obra | Writer | PENDING | TODO | P1 | — |
| FEAT-COM-004 | Publicar buscando writing buddy | Writer | PENDING | TODO | P2 | — |
| FEAT-COM-005 | Publicar ofreciéndose como lector beta | Reader | PENDING | TODO | P2 | — |
| FEAT-COM-006 | Comentar una publicación | User | PENDING | TODO | P2 | — |
| FEAT-COM-007 | Reaccionar con emoji a una publicación | User | PENDING | TODO | P2 | — |
| FEAT-COM-008 | Apoyar una publicación con un "me gusta" | User | PENDING | TODO | P2 | — |
| FEAT-COM-009 | Filtrar y buscar publicaciones (tipo, texto, usuario, fecha) | User | PENDING | TODO | P2 | — |
| FEAT-COM-010 | Suscribirse a un autor | User | PENDING | TODO | P2 | — |
| FEAT-COM-011 | Enviar un mensaje directo | User | PENDING | TODO | P2 | — |
| FEAT-COM-012 | Ver y gestionar conversaciones de mensajes directos | User | PENDING | TODO | P2 | — |
| FEAT-COM-013 | Ver y filtrar el ranking de escritores | User | PENDING | BLOCKED | P2 | — |
| FEAT-COM-014 | Ver y filtrar el ranking de obras | User | PENDING | BLOCKED | P2 | — |
| FEAT-COM-015 | Ver y filtrar el ranking de lectores | User | PENDING | BLOCKED | P2 | — |

> Los tres rankings están `BLOCKED` por `CM-4`: el material de partida dice "mejor valorados"
> pero no define la fórmula de puntuación. Sin ella no hay especificación posible.

---

## `Credits` — economía de la plataforma

Ficha del contexto: [`../bounded-contexts/credits.md`](../bounded-contexts/credits.md)

| ID | Funcionalidad | Actores | Spec | Impl | Prio | Ficha |
|---|---|---|---|---|---|---|
| FEAT-CRD-001 | Consultar saldo de créditos | User | PENDING | TODO | P0 | — |
| FEAT-CRD-002 | Abonar créditos de bienvenida al crear cuenta (+20) | — (sistema) | PENDING | TODO | P0 | — |
| FEAT-CRD-003 | Abonar créditos por dar feedback, según nivel del texto | — (sistema) | PENDING | TODO | P0 | — |
| FEAT-CRD-004 | Abonar +5 créditos por feedback valorado positivamente | — (sistema) | PENDING | TODO | P1 | — |
| FEAT-CRD-005 | Abonar +5 créditos por invitación que participa | — (sistema) | PENDING | TODO | P2 | — |
| FEAT-CRD-006 | Cargar créditos al autor por comentario recibido | — (sistema) | DRAFT | BLOCKED | P0 | [ficha](credits/FEAT-CRD-006-charge-author-for-received-feedback.md) |
| FEAT-CRD-007 | Aplicar el coste adicional por preguntas extra del cuestionario | — (sistema) | PENDING | TODO | P1 | — |
| FEAT-CRD-008 | Consultar el historial de movimientos de créditos | User | PENDING | TODO | P1 | — |
| FEAT-CRD-009 | Reservar créditos al conceder acceso a un LB | — (sistema) | PENDING | DEFERRED | P2 | — |
| FEAT-CRD-010 | Calcular el coste con fórmula continua en vez de tramos | — (sistema) | PENDING | DEFERRED | P3 | — |
| FEAT-CRD-011 | Deduplicar eventos para garantizar idempotencia | — (sistema) | PENDING | TODO | P0 | — |
| FEAT-CRD-012 | Monitorizar la salud de la economía de créditos | Admin | PENDING | DEFERRED | P3 | — |

> `FEAT-CRD-006` es el núcleo del producto y está `BLOCKED` por `C-1`: no está decidido qué
> ocurre cuando el autor no tiene saldo. Su ficha documenta las tres opciones.
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

---

## Funcionalidades bloqueadas

Resumen de lo que no se puede especificar hasta tomar una decisión de producto:

| Funcionalidad | Bloqueada por | Decisión necesaria |
|---|---|---|
| FEAT-CRD-006 | `C-1` | Qué ocurre si el autor no tiene saldo suficiente |
| FEAT-COM-013/014/015 | `CM-4` | Fórmula de puntuación de cada ranking |
| FEAT-WRK-009 | `W-1` | Cuándo se genera el registro de autoría |
| FEAT-FBK-008 | `A-3`, `C-5` | Identificación y créditos del comentarista anónimo |
| FEAT-USR-013 | `V-4`, `U-3` | Qué se conserva al eliminar la cuenta |

Estas cinco decisiones son el camino crítico de la especificación. `C-1` es la más urgente:
afecta al recorrido principal del producto.

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
