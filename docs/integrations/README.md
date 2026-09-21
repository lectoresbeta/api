# Integraciones externas

Servicios de terceros de los que depende la plataforma. Cada uno se accede **a través de un
puerto** definido en una capa interna e implementado en `Infrastructure`: el dominio nunca
conoce al proveedor.

> Estado: `DRAFT`. Ningún proveedor está elegido todavía.

| Integración | Para qué | Puerto | Estado |
|---|---|---|---|
| Google OAuth | Registro y login | `OAuthProvider` | Por especificar |
| Facebook OAuth | Registro y login | `OAuthProvider` | Por especificar |
| Proveedor de email | Notificaciones, recuperación de contraseña, invitaciones | `Mailer` | Sin elegir (`N-3`) |
| Almacenamiento de ficheros | Manuscritos subidos, imágenes de perfil | `FileStorage` | Sin elegir |
| Extracción de texto | Leer `.doc`, `.pdf`, `.txt` | `DocumentTextExtractor` | Sin elegir |
| RabbitMQ | Transporte de eventos de integración | — (infraestructura propia) | Decidido en `AGENTS.md` |
| Sellado temporal | Registro de autoría con valor probatorio | `ContentHasher` / por definir | Sin decidir (`S-6`) |

## Reglas

- La elección de proveedor es una decisión de `Infrastructure`. Cambiarlo no debe tocar
  `Domain` ni `Application`.
- Las credenciales van en variables de entorno. Nunca en el repositorio.
- Todo fallo de un servicio externo se traduce a un error de aplicación con significado, no
  se propaga tal cual al cliente de la API.
- No se mantiene una transacción de base de datos abierta durante una llamada externa lenta.
- Toda integración nueva se documenta con su propio fichero en esta carpeta.

## Plantilla de una integración

Cada documento debe cubrir: propósito, proveedor elegido y por qué, puerto que implementa,
configuración necesaria (variables de entorno, sin valores), comportamiento ante fallo,
límites de uso del proveedor, coste, y qué datos salen de la plataforma.

Esta última pregunta es la importante: **qué información abandona nuestra infraestructura**.
Con contenido literario inédito no es una formalidad.

## RabbitMQ

Es el transporte de toda la comunicación entre contextos. Su configuración (exchanges,
colas, enrutado, reintentos, cola de fallos) vive en `Infrastructure` y se documenta en
[`../architecture/04-cross-context-communication.md`](../architecture/04-cross-context-communication.md).

Ni `Domain` ni `Application` conocen su existencia.
