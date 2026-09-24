# API

La API HTTP es el contrato público del backend. Este apartado documenta **qué significa
cada operación**; la forma exacta de peticiones y respuestas vive en
[`openapi/`](../../openapi/).

## Reparto de responsabilidades

| Dónde | Qué contiene |
|---|---|
| `api/conventions/` | Lo transversal: enrutado, autenticación, errores, paginación, versionado, concurrencia, ficheros |
| `api/endpoints/` | Por contexto: propósito de cada operación, autorización, reglas y efectos |
| `openapi/` (raíz) | Esquemas, tipos, códigos y ejemplos. Contrato máquina-legible |

**No se duplica información.** El markdown explica *por qué* y *cuándo*; OpenAPI define
*qué forma tiene*. Cada operación documentada referencia su `operationId`.

## Regla de oro

Todo cambio de la API se refleja en el mismo commit en los tres sitios que le apliquen:
la ficha de la funcionalidad, el documento de endpoints y la especificación OpenAPI. Es
requisito explícito de `AGENTS.md` y de la *Definition of done*.

## Convenciones

| Documento | Contenido |
|---|---|
| [routing.md](conventions/routing.md) | Dónde se declara cada ruta y cómo se nombra |
| [authentication.md](conventions/authentication.md) | Cómo se autentica y cómo se identifica al usuario |
| [errors.md](conventions/errors.md) | Formato de error, códigos y cuándo se usa cada uno |
| [pagination.md](conventions/pagination.md) | Paginación, filtrado y ordenación de colecciones |
| [versioning.md](conventions/versioning.md) | Versionado de la API y evolución del contrato |
| [concurrency-and-idempotency.md](conventions/concurrency-and-idempotency.md) | Reintentos seguros y conflictos de edición |
| [file-uploads.md](conventions/file-uploads.md) | Subida de manuscritos e imágenes |

## Endpoints por contexto

| Contexto | Documento | Estado |
|---|---|---|
| `User` | [user.md](endpoints/user.md) | Esqueleto |
| `Work` | [work.md](endpoints/work.md) | Esqueleto |
| `Reading` | [reading.md](endpoints/reading.md) | Solicitudes implementadas; invitaciones especificadas |
| `Feedback` | [feedback.md](endpoints/feedback.md) | Esqueleto |
| `Community` | `endpoints/community.md` | Por crear |
| `Credits` | [credits.md](endpoints/credits.md) | Esqueleto |
| `Notification` | `endpoints/notification.md` | Por crear |

## Estilo general

- REST orientado a recursos, salvo que un caso de uso concreto justifique otra cosa.
- Rutas en plural y en inglés: `/works`, `/works/{workId}/chapters`.
- Identificadores UUID v7 en las rutas.
- Cuerpos en JSON, campos en `camelCase`.
- Fechas en ISO 8601 con zona horaria (`2026-09-21T10:15:30Z`).
- Nunca se serializan entidades de Doctrine: siempre DTO explícitos.
- Los controladores son finos: traducen HTTP a casos de uso y de vuelta.

## Acciones que no son CRUD

Algunas operaciones son transiciones de estado, no modificaciones de recursos. Se modelan
como subrecursos con verbo explícito:

```text
POST /works/{workId}/access-requests            solicitar ser LB
POST /access-requests/{id}/acceptance           aceptar la solicitud
POST /feedback/{feedbackId}/positive-rating     valorar un comentario
POST /works/{workId}/public-link                crear enlace público
```

Es preferible a `PATCH` con un campo `status`: hace explícita la autorización y la regla de
negocio de cada transición, que es precisamente lo que aquí importa.
