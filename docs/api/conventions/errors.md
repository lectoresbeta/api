# Errores

## Formato

Todas las respuestas de error usan la misma estructura, basada en RFC 7807:

```json
{
  "type": "https://lectoresbeta.com/errors/insufficient-credits",
  "title": "Créditos insuficientes",
  "status": 402,
  "detail": "El autor no dispone de saldo suficiente para recibir este comentario.",
  "code": "INSUFFICIENT_CREDITS",
  "errors": [
    { "field": "title", "code": "REQUIRED", "message": "El título es obligatorio." }
  ]
}
```

- `code` es estable y legible por máquina. El frontend decide por `code`, **nunca** por el
  texto de `title` o `detail`.
- `errors` solo aparece en errores de validación.
- `detail` es para personas y puede cambiar sin ser un cambio incompatible.

### Miembros de extensión

Un error puede llevar **campos propios al nivel superior**, que es lo que RFC 9457 llama
miembros de extensión. Se usan cuando el cliente necesita **un dato, no una frase**: un texto
que diga «inténtalo dentro de tres semanas» obliga a leerlo a una persona, y lo que hace falta
es que el formulario se desactive solo.

El primero es `availableOn` en `USERNAME_CHANGE_TOO_SOON` (`FEAT-USR-034`), y es la razón de
que ese caso sea un `429` y no un conflicto: un cliente que no pueda saber la fecha dejará el
formulario activo y permitirá reintentar mañana, y pasado — justo lo que el límite evita.

Dos reglas: **nunca pisan los campos del formato** —`type`, `title`, `status`, `code`,
`detail`, `errors`— y **nunca llevan nada que el error no pueda contar ya**. Un miembro de
extensión es tan público como el `detail` que lo acompaña.

### `Retry-After`

Un `429` lleva además la cabecera **`Retry-After`**, en segundos. No sustituye al miembro de
extensión, lo acompaña: la cabecera la entienden los clientes HTTP, los proxies y las
bibliotecas de reintento sin que nadie las programe, y el cuerpo la repite para quien no mire
las cabeceras.

Lo declara la excepción, implementando `RetryAfter`, y no cada controlador: una cabecera que
hay que acordarse de poner es una cabecera que falta en el siguiente `429` que alguien añada.

## Códigos HTTP

| Código | Cuándo |
|---|---|
| `400` | Petición malformada (JSON inválido, tipo incorrecto) |
| `401` | Sin autenticar, o sesión expirada |
| `403` | Autenticado pero sin permiso, **y el recurso puede revelarse** |
| `404` | No existe, **o no debe revelarse que existe** |
| `409` | Conflicto con el estado actual (duplicado, transición inválida) |
| `422` | Sintaxis correcta pero contenido inválido o regla de negocio incumplida |
| `429` | Demasiadas peticiones |
| `500` | Error inesperado |

Pendiente: si `402 Payment Required` es el código adecuado para saldo de créditos
insuficiente, o si encaja mejor un `409`. Los créditos no son dinero real.

Con la reserva previa ([`decision:0004`](../../decisions/0004-credit-reservation-on-access-grant.md))
este error aparece **al solicitar o conceder un acceso de lector beta**, no al enviar
feedback: el corrector cobra siempre, y si el autor no llega su saldo queda en negativo.

## `403` frente a `404`

Distinción deliberada y **crítica para el producto**: revelar que una obra inédita existe ya
es una fuga de información.

| Situación | Código |
|---|---|
| Obra del catálogo público cuyo contenido no puede leer | `403` |
| Obra `HIDDEN` de otro autor | `404` |
| Fragmento oculto | `404` |
| Feedback de una obra a la que no tiene acceso | `404` |
| Recurso propio con una operación no permitida en ese estado | `403` |

Criterio: **si el usuario no debería saber siquiera que el recurso existe, `404`.**

## `ACCOUNT_NOT_ACTIVATED` frente a `FORBIDDEN`

Ambos son `403`, pero se distinguen deliberadamente por su `code`.

`ACCOUNT_NOT_ACTIVATED` significa «esto se arregla activando la cuenta», y la interfaz debe
poder ofrecer el reenvío del correo (`FEAT-USR-021`). Un `403` genérico dejaría al usuario
sin saber qué hacer, y el problema tiene solución inmediata.

## Nunca se expone

`AGENTS.md` lo exige y aquí se concreta. Una respuesta de error jamás contiene:

- trazas de pila;
- SQL o mensajes del motor de base de datos;
- nombres de clase internos;
- rutas de fichero;
- tokens ni credenciales;
- contenido de obras, feedback o mensajes directos;
- detalles de la infraestructura.

Los errores inesperados se registran internamente con contexto suficiente para
diagnosticar, y hacia fuera devuelven un `500` genérico.

## Catálogo de códigos de negocio

Se irá completando conforme se especifiquen las funcionalidades.

| `code` | HTTP | Significado |
|---|---|---|
| `VALIDATION_FAILED` | 422 | Uno o varios campos no son válidos |
| `UNSUPPORTED_FILE_TYPE` | 422 | El fichero subido no es de un tipo admitido |
| `INVALID_IMAGE` | 422 | El fichero dice ser imagen pero no se puede procesar |
| `FILE_TOO_LARGE` | 413 | El fichero supera el máximo de su tipo. El avatar son **2 MB** (`FEAT-USR-037`) |
| `ACCOUNT_NOT_ACTIVATED` | 403 | La cuenta está en `PENDING_ACTIVATION` y la operación es de escritura |
| `INVALID_ACTIVATION_TOKEN` | 404 | Token de activación inexistente, usado o manipulado |
| `ACTIVATION_TOKEN_EXPIRED` | 410 | El token de activación caducó |
| `RESEND_TOO_SOON` | 429 | Reenvío de activación antes del intervalo mínimo |
| `TERMS_NOT_ACCEPTED` | 422 | Falta aceptar condiciones de uso o política de privacidad |
| `WEAK_PASSWORD` | 422 | La contraseña no cumple la política |
| `NOT_ENOUGH_GENRES` | 422 | Menos de tres géneros en el onboarding |
| `UNKNOWN_GENRE` | 422 | Género no presente en el catálogo |
| `ONBOARDING_STEP_OUT_OF_ORDER` | 409 | Se intenta un paso del onboarding sin completar el anterior |
| `EMAIL_ALREADY_REGISTERED` | 409 | El email ya tiene cuenta (ver `RN-8` de `FEAT-USR-001`) |
| `USERNAME_TAKEN` | 409 | Nombre de usuario ocupado: en uso **o** retenido por un alias vigente, que responden igual a propósito (`FEAT-USR-034`) |
| `USERNAME_RESERVED` | 422 | Nombre de usuario de la lista de reservados. Se distingue del ocupado porque ahí no hay nadie a quien proteger |
| `USERNAME_CHANGE_TOO_SOON` | 429 | El nombre de usuario se cambió hace menos de 30 días. Lleva `availableOn` |
| `RESEND_TOO_SOON` | 429 | Se ha pedido el correo de activación hace menos de un minuto (`FEAT-USR-021`). Lleva `retryAfterSeconds` y `Retry-After` |
| `RESEND_LIMIT_REACHED` | 429 | Se ha agotado el máximo de reenvíos del periodo. Se distingue del anterior porque significa «vuelve mañana» y no «espera un minuto» |
| `WORK_NOT_FOUND` | 404 | La obra no existe o no es visible para este usuario |
| `NOT_WORK_AUTHOR` | 403 | La operación requiere ser el autor de la obra |
| `NO_BETA_READER_ACCESS` | 403 | No tiene acceso de lector beta a esta obra |
| `ACCESS_REQUEST_ALREADY_EXISTS` | 409 | Ya hay una solicitud pendiente |
| `READER_ALREADY_HAS_ACCESS` | 409 | El usuario ya es lector beta de la obra |
| `WORK_IS_PRIVATE` | 403 | La obra no admite solicitudes de acceso |
| `CANNOT_SUBSCRIBE_TO_YOURSELF` | 422 | Uno no se sigue a sí mismo (`FEAT-COM-010`) |
| `CANNOT_BLOCK_YOURSELF` | 422 | Uno no se bloquea a sí mismo (`FEAT-COM-034`) |
| `INSUFFICIENT_CREDITS` | 402 | El saldo del autor no cubre el precio del capítulo, así que no admite correcciones ahora mismo |
| `DIRECT_MESSAGES_DISABLED` | 403 | El destinatario no acepta mensajes directos |
| `PROPOSALS_DISABLED` | 403 | El destinatario no acepta propuestas |
| `INVALID_PUBLIC_LINK` | 404 | Enlace público inexistente o revocado |

Los nombres se corresponden con las excepciones de dominio que `AGENTS.md` propone
(`ManuscriptNotFound`, `AccessRequestAlreadyExists`, `ReaderAlreadyHasAccess`,
`UnauthorizedManuscriptAccess`). La traducción de excepción a respuesta HTTP es
centralizada y vive en `Shared/Infrastructure/Http`.
