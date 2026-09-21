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
insuficiente. Depende de `C-1`.

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
| `EMAIL_ALREADY_REGISTERED` | 409 | El email ya tiene cuenta (ver `RN-8` de `FEAT-USR-001`) |
| `USERNAME_TAKEN` | 422 | Nombre de usuario ocupado |
| `WORK_NOT_FOUND` | 404 | La obra no existe o no es visible para este usuario |
| `NOT_WORK_AUTHOR` | 403 | La operación requiere ser el autor de la obra |
| `NO_BETA_READER_ACCESS` | 403 | No tiene acceso de lector beta a esta obra |
| `ACCESS_REQUEST_ALREADY_EXISTS` | 409 | Ya hay una solicitud pendiente |
| `READER_ALREADY_HAS_ACCESS` | 409 | El usuario ya es lector beta de la obra |
| `WORK_IS_PRIVATE` | 403 | La obra no admite solicitudes de acceso |
| `INSUFFICIENT_CREDITS` | 402 | Saldo insuficiente (pendiente de `C-1`) |
| `DIRECT_MESSAGES_DISABLED` | 403 | El destinatario no acepta mensajes directos |
| `PROPOSALS_DISABLED` | 403 | El destinatario no acepta propuestas |
| `INVALID_PUBLIC_LINK` | 404 | Enlace público inexistente o revocado |

Los nombres se corresponden con las excepciones de dominio que `AGENTS.md` propone
(`ManuscriptNotFound`, `AccessRequestAlreadyExists`, `ReaderAlreadyHasAccess`,
`UnauthorizedManuscriptAccess`). La traducción de excepción a respuesta HTTP es
centralizada y vive en `Shared/Infrastructure/Http`.
