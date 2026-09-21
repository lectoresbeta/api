# Seguridad y autorización

> Lectores Beta custodia **obra literaria inédita**. Una fuga de contenido no es un fallo
> técnico menor: es el peor fallo posible del producto. La autorización es crítica.

## Autenticación

| Método | Estado |
|---|---|
| Email y contraseña | `FEAT-USR-001`, `FEAT-USR-004` |
| Google (OAuth) | `FEAT-USR-002`, `FEAT-USR-005` — **único proveedor externo de esta fase** |
| Facebook (OAuth) | `DEFERRED` |
| LinkedIn (OAuth) | `DEFERRED` |
| Recuperación de contraseña | `FEAT-USR-007` |
| Enlace público sin sesión | Por especificar — acceso limitado a una obra concreta |

La plataforma **no usa nombre de usuario**: la identidad se establece por email.

**Decisión pendiente** (`S-1`): mecanismo de sesión de la API. Opciones: JWT de vida corta
con refresh token, tokens opacos en servidor, o sesión de Symfony. Debe cerrarse en un ADR
antes de implementar `FEAT-USR-004`.

Detalle del contrato en [`../api/conventions/authentication.md`](../api/conventions/authentication.md).

## Autorización

### El principio

Los roles de Lectores Beta **son relativos a un recurso**, no globales. La pregunta nunca es
*"¿este usuario es escritor?"* sino *"¿qué relación tiene este usuario con esta obra?"*.

| Pregunta | La responde |
|---|---|
| ¿Es el autor de esta obra? | `Work` |
| ¿Tiene acceso de lector beta a esta obra? | `Reading` |
| ¿Puede dejar feedback sobre este fragmento? | `Feedback`, apoyándose en el acceso concedido |
| ¿Puede enviarle un mensaje directo? | `Community`, según las preferencias del destinatario |
| ¿Tiene saldo para recibir feedback? | `Credits` |

Cada contexto responde a lo suyo. La autorización no se concentra en un servicio central que
lo sepa todo, porque eso reintroduce el acoplamiento que la arquitectura evita.

### Estado de la cuenta: la primera barrera

Antes de cualquier regla de autorización por recurso se comprueba el estado de la cuenta.

| Estado | Lectura | Onboarding | Escritura |
|---|---|---|---|
| `PENDING_ACTIVATION` | Sí | Sí | **No** |
| `ACTIVE` | Sí | Sí | Sí |
| `DELETED` | No | No | No |

Una cuenta sin activar no puede crear obras, comentar, publicar en el muro, enviar mensajes
directos ni **recibir comentarios en sus obras**. Tampoco tiene créditos: los 20 de
bienvenida se abonan al activar.

Es una política **única y centralizada**, aplicada en el borde HTTP, no una comprobación
repetida endpoint por endpoint. Detalle en
[`FEAT-USR-025`](../features/user/FEAT-USR-025-block-writes-until-activation.md) y motivación
en [`decision:0003`](../decisions/0003-write-operations-require-activated-account.md).

El caso que no encaja en ese borde es «recibir comentarios»: no depende de quién llama, sino
del estado del autor de la obra. `Feedback` lo resuelve con una proyección alimentada por
`AccountActivated`, nunca consultando las tablas de `User`.

### Dónde vive

- La **regla de negocio** vive en `Domain` o en una política de `Application`.
- El **punto de aplicación** está en `Infrastructure` (controlador, voter de Symfony), que
  delega en la política.
- No se duplica lógica de autorización en los controladores.

### Reglas ya conocidas

| Recurso | Quién accede |
|---|---|
| Contenido de una obra | Su autor, y quien tenga `BetaReaderAccess` vigente sobre ella. Nadie más. |
| Obra o fragmento con `Visibility: HIDDEN` | Solo su autor |
| Obra mediante enlace público | Quien posea el token del enlace, con permisos limitados a leer y comentar |
| Feedback recibido | El autor de la obra y quien lo escribió |
| Feedback oculto por el autor | Solo el autor y quien lo escribió |
| Mensaje directo | Solo si el destinatario los tiene habilitados |
| Propuesta de LB o writing buddy | Solo si el destinatario las tiene habilitadas |
| Saldo e historial de créditos | Solo su titular |
| Datos de perfil no públicos | Solo su titular |
| Fecha de nacimiento y email | **Solo su titular.** Nunca en perfiles, listados, búsquedas ni sugerencias |

### Errores de autorización

Se distingue deliberadamente:

- **`403 Forbidden`** cuando el usuario sabe que el recurso existe (por ejemplo, una obra
  pública del catálogo cuyo contenido no puede leer).
- **`404 Not Found`** cuando revelar la existencia del recurso ya es una fuga (una obra
  oculta, un fragmento privado). **No se confirma la existencia de contenido inédito a quien
  no tiene acceso.**

El criterio por endpoint se fija en [`../api/conventions/errors.md`](../api/conventions/errors.md).

## Protección del contenido

- El contenido de una obra nunca viaja en un evento de integración.
- El contenido nunca se escribe en logs.
- Nunca se devuelve contenido en respuestas de error.
- Las descargas y exportaciones, si existen, requieren la misma autorización que la lectura.
- El enlace público usa un token largo, aleatorio e imposible de enumerar; debe poder
  revocarse y, si se decide, expirar.

## Registro de autoría

El documento de partida pide "un registro cifrando su contenido y la fecha de creación que
garantiza la autenticidad de la obra".

Interpretación técnica propuesta: **no es cifrado, es una huella criptográfica con sello
temporal**. Se almacena `hash(contenido) + timestamp + autor`, de forma inmutable. Permite
demostrar después que un contenido concreto existía en una fecha concreta sin necesidad de
guardar una copia cifrada.

Preguntas abiertas asociadas: qué algoritmo, si se sella con una fuente temporal externa, y
**en qué momentos se genera** (¿en cada edición? ¿al publicar? ¿cuando lo decide el autor?).
El propio documento de origen deja esta última pregunta explícitamente abierta.

## Datos sensibles

Nunca se registran en logs ni se exponen en respuestas:

- contraseñas, tokens de sesión, tokens OAuth, tokens de enlace público;
- contenido de obras y de feedback;
- contenido de mensajes directos;
- datos personales más allá de lo estrictamente necesario para diagnosticar.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| S-1 | ¿Qué mecanismo de sesión usa la API? | Bloquea el diseño de autenticación |
| S-2 | ¿El enlace público expira o se revoca? ¿Tiene límite de usos? | Riesgo de difusión no controlada del contenido |
| S-3 | ¿Se requiere verificación del email al registrarse? | **Resuelto:** sí. Es la barrera de escritura y de créditos (`decision:0003`) |
| S-7 | ¿El registro con Google crea la cuenta ya activada? Google ya verifica el correo | Evitaría una verificación redundante (`OB-11`) |
| S-4 | ¿Hay límite de peticiones (rate limiting) y dónde? | Protección frente a scraping del catálogo |
| S-5 | ¿Cómo se previene el scraping masivo de obras por parte de lectores beta legítimos? | Riesgo real dado el valor del contenido |
| S-6 | ¿Algoritmo y sellado temporal del registro de autoría? ¿Sello externo? | Valor probatorio del registro |
