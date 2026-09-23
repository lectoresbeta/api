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
| [0004](0004-credit-reservation-on-access-grant.md) | ~~Los créditos se reservan al conceder acceso~~ | **Sustituida por [0006](0006-credit-system.md)** | 2026-09-22 |
| [0005](0005-username-with-temporary-aliases.md) | El nombre de usuario existe, es editable y deja un alias temporal al cambiarlo | Aceptada | 2026-09-22 |
| [0006](0006-credit-system.md) | **Sistema de créditos: precio por esfuerzo, transferencia pura, sin retenciones y con saldo negativo como red** | Aceptada | 2026-09-23 |
| [0007](0007-jwt-sessions.md) | **La sesión de la API se resuelve con JWT**, asumiendo hasta 15 minutos de retardo en la revocación | Aceptada | 2026-09-24 |
| [0008](0008-catalogue-ordering.md) | **El catálogo reparte trabajo, no premia popularidad**: ordena por capacidad del autor, desatención y frescura | Aceptada | 2026-09-24 |
| [0009](0009-one-postgresql-schema-per-bounded-context.md) | **Un esquema de PostgreSQL por bounded context**, sin claves foráneas entre ellos | Aceptada | 2026-09-24 |
| [0010](0010-routes-declared-in-yaml-per-context.md) | **Las rutas se declaran en YAML**, un fichero por bounded context, nunca con atributos | Aceptada, ubicación modificada por [0011](0011-route-files-live-inside-their-context.md) | 2026-09-24 |
| [0011](0011-route-files-live-inside-their-context.md) | Los ficheros de rutas viven **dentro de su contexto**, en `src/<Contexto>/Infrastructure/` | Aceptada | 2026-09-24 |

## Decisiones pendientes

Bloquean trabajo y deben cerrarse antes de implementar lo que afectan:

| Ref | Decisión | Bloquea |
|---|---|---|
| `CM-4` | Fórmula de puntuación de los **rankings**. Catálogo y comentarios ya resueltos ([0008](0008-catalogue-ordering.md)) | `FEAT-COM-013/014/015`, `FEAT-COM-017` |
| `V-4` | Qué ocurre con los mensajes directos al anonimizar una cuenta | `FEAT-USR-013`, anotado dentro de la ficha |
| `W-1` | En qué momentos se genera el registro de autoría | `FEAT-WRK-009` |
| `AF-1`, `AF-2` | Qué mecanismo antifraude y si actúa antes o después del abono | `FEAT-FBK-012` |
| `T-5` | Cómo se recoge la aceptación legal en el alta con Google: casilla previa o pantalla intermedia | `FEAT-USR-002`, `FEAT-USR-024` |

## Estados

| Estado | Significado |
|---|---|
| Propuesta | Escrita, en discusión |
| Aceptada | Decidida. Se implementa así |
| Rechazada | Se consideró y se descartó. Se conserva para no volver a plantearla |
| Sustituida | Reemplazada por otro ADR, que se indica |

Un ADR **no se borra ni se edita para cambiar la decisión**: se escribe uno nuevo que lo
sustituye. El valor del registro está en poder ver qué se pensó en cada momento.
