# Decisiones de arquitectura (ADR)

Un ADR documenta **por qué** existe una decisión: su contexto, las alternativas que se
descartaron y lo que costará vivir con ella. No documenta cómo funciona el código.

Se escribe un ADR cuando la decisión es difícil de revertir, afecta a varios contextos, o
alguien va a preguntar dentro de seis meses por qué se hizo así.

Plantilla: [`../_templates/adr.md`](../_templates/adr.md).

## Registro

| # | Decisión | Estado | Fecha |
|---|---|---|---|
| [0001](0001-documentation-as-source-of-truth.md) | La documentación de `docs/` es la fuente de verdad del producto | Aceptada | 2026-09-21 |
| [0002](0002-credits-as-isolated-bounded-context.md) | `Credits` es un bounded context aislado y solo accesible por eventos | Aceptada | 2026-09-21 |

## Decisiones pendientes

Bloquean trabajo y deben cerrarse antes de implementar lo que afectan:

| Ref | Decisión | Bloquea |
|---|---|---|
| `C-1` | Qué ocurre cuando el autor no tiene créditos para recibir un comentario | `FEAT-CRD-006`, `FEAT-FBK-001`. **La más urgente** |
| `S-1` | Mecanismo de sesión de la API | Todo `User` y toda la autorización |
| `CM-4` | Fórmula de puntuación de los rankings | `FEAT-COM-013/014/015` |
| `W-1` | En qué momentos se genera el registro de autoría | `FEAT-WRK-009` |
| `P-1` | Un esquema de PostgreSQL por contexto, o uno solo | La primera migración |
| `V-4` | Qué se conserva al eliminar una cuenta | `FEAT-USR-013` |
| `D-1` | Si el feedback se ancla al fragmento o a la obra | `Feedback` y el cálculo de créditos |

## Estados

| Estado | Significado |
|---|---|
| Propuesta | Escrita, en discusión |
| Aceptada | Decidida. Se implementa así |
| Rechazada | Se consideró y se descartó. Se conserva para no volver a plantearla |
| Sustituida | Reemplazada por otro ADR, que se indica |

Un ADR **no se borra ni se edita para cambiar la decisión**: se escribe uno nuevo que lo
sustituye. El valor del registro está en poder ver qué se pensó en cada momento.
