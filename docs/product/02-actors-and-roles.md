# Actores y roles

> Estado: `DRAFT` — derivado de `_sources/use-cases.pdf`.

Los roles **no son excluyentes**: el mismo usuario es escritor de sus obras y lector beta
de las ajenas. Esto es intencional y es lo que sostiene la economía de créditos.

---

## Catálogo de actores

| Actor | Identificador (`actors`) | Descripción |
|---|---|---|
| Visitante | `Guest` | Persona sin sesión iniciada. Puede registrarse y, con un enlace público, leer y comentar una obra concreta. |
| Usuario registrado | `User` | Cualquier cuenta autenticada. Base de todos los roles siguientes. |
| Escritor | `Writer` | Usuario en relación con **una obra propia**. Controla su contenido, su acceso y su cuestionario. |
| Lector | `Reader` | Usuario navegando el catálogo: busca obras y autores, se suscribe, participa en el muro. |
| Lector beta | `BetaReader` | Usuario **con acceso concedido a una obra concreta**. Es el único que puede dejar feedback sobre ella. |
| Compañero de escritura | `WritingBuddy` | Usuario vinculado recíprocamente a otro para intercambio continuado de feedback. |
| Administrador | `Admin` | Rol de plataforma. **Sin definir todavía** (pregunta abierta V-1). |

> `Writer`, `BetaReader` y `WritingBuddy` son **roles relativos a un recurso**, no roles
> globales del sistema. La autorización se resuelve siempre respondiendo *"¿qué relación
> tiene este usuario con esta obra?"*, nunca *"¿qué rol global tiene?"*. Ver
> [`../architecture/06-security-and-authorization.md`](../architecture/06-security-and-authorization.md).

---

## Qué puede hacer cada actor

### Visitante (`Guest`)

- Registrarse (email y contraseña, Google o Facebook).
- Iniciar sesión y recuperar contraseña.
- Acceder a una obra mediante **enlace público** y dejar un comentario sin sesión.
- Acceder a la landing para **hacerse lector beta** de una obra compartida en redes sociales.

### Usuario registrado (`User`)

- Gestionar su cuenta: datos personales, contraseña, preferencias literarias.
- Configurar si acepta mensajes directos y propuestas de LB o writing buddy.
- Configurar qué notificaciones recibe por email.
- Eliminar su cuenta.
- Ver perfiles públicos, enviar mensajes directos.
- Participar en el muro principal: publicar, comentar, reaccionar, apoyar, filtrar.
- Consultar rankings de escritores, obras y lectores.
- Consultar su saldo y su historial de créditos.
- Invitar a otras personas a la plataforma.

### Escritor (`Writer`)

Sobre **sus propias obras**:

- Crear obras con el editor o subiendo un fichero, estructurarlas en fragmentos.
- Ver, editar y eliminar la obra.
- Configurar la modalidad de acceso de lectores beta y la visibilidad de obra y fragmentos.
- Definir el cuestionario que acompaña a la obra.
- Generar enlaces públicos y enlaces para redes sociales.
- Ver, contestar, valorar y ocultar los comentarios recibidos.
- Buscar lectores beta, proponer a usuarios que lo sean, y gestionar grupos de LB.
- Aceptar o rechazar solicitudes de acceso.
- Proponer a un usuario ser su writing buddy.
- Configurar y personalizar su página de autor.
- Publicar en el muro buscando lectores beta o writing buddy.

### Lector (`Reader`)

- Buscar obras por tipo, temática y valoración.
- Buscar autores por nombre o temática, y suscribirse a ellos.
- Ofrecerse como lector beta mediante una publicación en el muro.
- Convertirse en lector beta según la modalidad de acceso de cada obra.

### Lector beta (`BetaReader`)

Sobre **una obra concreta a la que tiene acceso**:

- Leer su contenido.
- Dejar feedback y responder al cuestionario.
- Valorar la obra.

### Compañero de escritura (`WritingBuddy`)

- Intercambio recíproco de feedback con otro usuario.

> **Pendiente**: el material de partida define el writing buddy como concepto pero no detalla
> qué privilegios concretos otorga (¿acceso automático a todas las obras del otro? ¿solo un
> vínculo social?). Ver pregunta abierta A-1.

### Administrador (`Admin`)

Sin definir.

---

## Cómo se obtiene el rol de lector beta

Depende de la modalidad de acceso configurada en la obra (`BetaReaderAccessMode`):

| Modalidad | Cómo se obtiene el acceso | Interviene el autor |
|---|---|---|
| `PUBLIC` | Cualquier usuario se convierte en LB automáticamente | No |
| `ON_REQUEST` | El usuario solicita acceso y el autor acepta o rechaza | Sí, aprobando |
| `PRIVATE` | No se puede solicitar. Solo por invitación del autor | Sí, invitando |

En los tres casos el resultado es el mismo objeto de dominio: un `BetaReaderAccess` que
vincula usuario y obra. Lo que cambia es el camino para llegar a él.

---

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| A-1 | ¿Qué otorga exactamente ser writing buddy? ¿Acceso automático a las obras del otro? | Define si `WritingBuddy` concede `BetaReaderAccess` o es solo un vínculo social |
| A-2 | ¿Existe rol de administrador y qué puede hacer? | Ver V-1 |
| A-3 | ¿El comentario desde enlace público es anónimo o pide identificarse (nombre/email)? | Afecta al modelo de `Feedback` y a si genera créditos |
| A-4 | ¿Puede el autor revocar el acceso de un lector beta ya concedido? | Nueva funcionalidad en `Reading` |
| A-5 | ¿Un usuario puede ser lector beta de su propia obra? (asumimos que no) | Invariante de dominio |
