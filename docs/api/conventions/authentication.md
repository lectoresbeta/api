# Autenticación

> Estado: `DRAFT` — **bloqueado por `S-1`**: el mecanismo de sesión no está decidido.

## Métodos previstos

| Método | Funcionalidad |
|---|---|
| Email y contraseña | `FEAT-USR-001`, `FEAT-USR-004` |
| Google (OAuth 2.0) | `FEAT-USR-002`, `FEAT-USR-005` |
| Facebook (OAuth 2.0) | `FEAT-USR-003`, `FEAT-USR-006` |
| Enlace público (sin sesión) | `FEAT-WRK-010`, `FEAT-FBK-008` |

## Decisión pendiente (`S-1`)

| Opción | A favor | En contra |
|---|---|---|
| JWT de vida corta + refresh token | Sin estado, escala bien, estándar | Revocación compleja, hay que gestionar el refresh |
| Token opaco en servidor | Revocación inmediata, control total | Requiere consulta en cada petición |
| Sesión de Symfony con cookie | Sencillo, integrado | Peor encaje con un frontend desacoplado y clientes móviles |

Debe cerrarse en un ADR antes de implementar `FEAT-USR-004`.

## Identidad del usuario

- El usuario autenticado se deriva **siempre** del token de la petición.
- **Nunca** se acepta un `userId` o `authorId` en el cuerpo para determinar quién actúa.
- La conversión de token a identidad ocurre en `Infrastructure`. La capa `Application`
  recibe un `UserId` ya resuelto, no un objeto de seguridad de Symfony.

## Endpoints públicos

Accesibles sin autenticación:

```text
POST /auth/register
POST /auth/login
POST /auth/password-reset
POST /auth/password-reset/{token}
GET  /auth/oauth/{provider}
GET  /public-links/{token}
POST /public-links/{token}/feedback
```

Todo lo demás requiere sesión.

## Enlace público

Caso especial: concede acceso a **una obra concreta** sin identificar a la persona.

- El token es largo, aleatorio y no enumerable.
- Concede únicamente leer esa obra y dejar un comentario. Nada más.
- No otorga sesión ni identidad en la plataforma.
- Debe poder revocarse.

Pendiente (`S-2`): si expira, si tiene límite de usos, y cómo se protege frente a la
difusión no controlada del contenido inédito.

## Seguridad

- Las contraseñas se cifran con el mecanismo configurado en Symfony. Nunca en claro,
  nunca en logs.
- Los tokens no aparecen en URLs registradas en logs ni en mensajes de error.
- El login no revela si un email existe: mismo error y mismo tiempo de respuesta.
- Pendiente (`S-4`): límite de intentos de login y de peticiones por cliente.
