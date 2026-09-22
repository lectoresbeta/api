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
| [0003](0003-write-operations-require-activated-account.md) | Las operaciones de escritura exigen tener la cuenta activada | Aceptada | 2026-09-21 |
| [0004](0004-credit-reservation-on-access-grant.md) | Los créditos se reservan al conceder acceso y se confirman al recibir el feedback | Aceptada | 2026-09-22 |

## Decisiones pendientes

Bloquean trabajo y deben cerrarse antes de implementar lo que afectan:

| Ref | Decisión | Bloquea |
|---|---|---|
| `R-1` | Si la reserva de créditos es por lector o por obra | `FEAT-CRD-009`. Variante de `decision:0004`, conviene cerrarla **antes de implementar** |
| `S-1` | Mecanismo de sesión de la API | Todo `User` y toda la autorización |
| `CM-4` | Fórmula de puntuación de los rankings | `FEAT-COM-013/014/015` |
| `W-1` | En qué momentos se genera el registro de autoría | `FEAT-WRK-009` |
| `P-1` | Un esquema de PostgreSQL por contexto, o uno solo | La primera migración |
| `V-4` | Qué se conserva al eliminar una cuenta | `FEAT-USR-013` |
| `D-1` | Si el feedback se ancla al fragmento o a la obra | `Feedback` y el cálculo de créditos |
| `OB-11` | Si el alta con Google crea la cuenta ya activada, dado que Google ya verifica el correo | `FEAT-USR-002`, `FEAT-USR-020` |
| `T-5` | Cómo se recoge la aceptación legal en el alta con Google: casilla previa o pantalla intermedia | `FEAT-USR-002`, `FEAT-USR-024` |
| `OB-7` | Si hay edad mínima de registro | `FEAT-USR-022`. Tiene implicaciones legales |
| `N-2` | Si el nombre público debe ser único | `FEAT-USR-022`. Sin unicidad, dos homónimos son indistinguibles |
| `M-2` | Qué es una obra «en corrección» y si es un estado de `Work` | `Work`, `Credits`, glosario |
| `H-5` | Si conviven «me gusta» y las reacciones con emoji | `FEAT-COM-007`, `FEAT-COM-008` |

## Estados

| Estado | Significado |
|---|---|
| Propuesta | Escrita, en discusión |
| Aceptada | Decidida. Se implementa así |
| Rechazada | Se consideró y se descartó. Se conserva para no volver a plantearla |
| Sustituida | Reemplazada por otro ADR, que se indica |

Un ADR **no se borra ni se edita para cambiar la decisión**: se escribe uno nuevo que lo
sustituye. El valor del registro está en poder ver qué se pensó en cada momento.
