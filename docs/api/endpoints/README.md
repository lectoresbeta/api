# Contratos de endpoints

Un documento por bounded context. Cada uno describe la **semántica** de sus operaciones:
propósito, autorización, reglas aplicadas y efectos.

La forma exacta de peticiones y respuestas está en [`openapi/`](../../../openapi/) y no se
duplica aquí.

| Contexto | Documento | Estado |
|---|---|---|
| `User` | [user.md](user.md) | Esqueleto |
| `Work` | [work.md](work.md) | Esqueleto |
| `Reading` | reading.md | Por crear |
| `Feedback` | feedback.md | Por crear |
| `Community` | community.md | Por crear |
| `Credits` | credits.md | Por crear |
| `Notification` | notification.md | Por crear |

Se crean a partir de [`../../_templates/endpoint.md`](../../_templates/endpoint.md) conforme
se especifican las funcionalidades correspondientes.
