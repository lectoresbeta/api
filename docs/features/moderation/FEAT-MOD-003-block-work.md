---
id: FEAT-MOD-003
title: Bloquear una obra por reclamación estimada
context: Moderation
concept: Claim
actors: []
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-23 (backoffice de administración)
endpoints: []
events: [ClaimUpheld, WorkBlockedByModeration]
depends_on: [FEAT-MOD-002, FEAT-WRK-016]
updated: 2026-09-23
---

# FEAT-MOD-003 — Bloquear una obra por reclamación

## Resumen

Cuando se estima una reclamación sobre una obra, esta queda **deshabilitada permanentemente**:
deja de ser accesible para todos, **salvo para su autor**, que la sigue viendo marcada como
bloqueada por reclamación.

## Quién ejecuta el bloqueo

**`Work`, no `Moderation`.** `Moderation` publica `ClaimUpheld` y `Work` lo interpreta como un
cambio en el ciclo de vida de la obra, que es su modelo.

Si `Moderation` escribiese el estado directamente, tendría que conocer las reglas de
transición de `Work`, y a partir de ahí dos contextos gobernarían el mismo dato.

## El estado nuevo

| Estado | Quién lee la obra | Quién la corrige |
|---|---|---|
| `DRAFT` | Solo el autor | Nadie |
| `VISIBLE` | Todos | Nadie |
| `IN_CORRECTION` | Todos | Lectores beta |
| **`BLOCKED`** | **Solo el autor**, marcada | **Nadie** |

`BLOCKED` es **terminal**: no se sale de él por ninguna transición ordinaria
([`FEAT-WRK-016`](../work/FEAT-WRK-016-work-status.md)).

## Reglas de negocio

- `RN-1` La obra deja de aparecer en el catálogo, en recomendaciones, en el perfil del autor y
  en cualquier búsqueda.
- `RN-2` **El autor la sigue viendo**, con una marca visible que explica por qué.
- `RN-3` Los **enlaces públicos** de esa obra dejan de servir contenido.
- `RN-4` Las correcciones **en curso** sobre esa obra se cancelan y **no generan cargo**: no
  se ha entregado nada.
- `RN-5` Las correcciones **ya entregadas y pagadas no se revierten**. El corrector hizo su
  trabajo de buena fe antes de que existiera reclamación alguna (`MOD-7`).
- `RN-6` El bloqueo **no borra la obra**. Borrar destruiría el contenido reclamado, que es
  justamente lo que puede hacer falta conservar si alguien discute la decisión.
- `RN-7` El bloqueo es **permanente** salvo acción administrativa nueva y motivada (`MOD-8`).

`RN-5` es la regla incómoda pero correcta: un lector que corrigió un texto meses antes de la
denuncia no tiene por qué pagar la consecuencia de lo que hizo el autor.

`RN-4` es el otro lado de la misma moneda: quien estuviera corrigiendo cuando llega el bloqueo
pierde su trabajo. Es inevitable, pero **debe avisársele** y conviene que el mensaje no le
haga pensar que ha hecho algo mal.

## Flujo

```text
ClaimUpheld (targetType: WORK)
        ↓
Work  → la obra pasa a BLOCKED y publica WorkBlockedByModeration
        ↓
   ├──▶ Feedback      cancela correcciones en curso
   ├──▶ Community     la retira de muros y recomendaciones
   ├──▶ Credits       recalcula corregibilidad (ya no lo es)
   └──▶ Notification  avisa al autor, con el motivo
```

## Criterios de aceptación

- [ ] La obra desaparece del catálogo, del perfil y de las búsquedas.
- [ ] El autor la sigue viendo, marcada como bloqueada, con el motivo.
- [ ] Sus enlaces públicos dejan de servir contenido.
- [ ] Las correcciones en curso se cancelan sin cargo, y se avisa a quien las escribía.
- [ ] Las correcciones ya pagadas **no** se revierten.
- [ ] El contenido no se borra.
- [ ] `BLOCKED` no se abandona por ninguna transición ordinaria.
- [ ] El cambio de estado lo ejecuta `Work`, no `Moderation`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **MOD-8** | ¿Puede el autor recurrir un bloqueo permanente? | «Permanente» sin recurso es una medida muy fuerte para un error |
| MOD-7 | ¿Se confirma que las correcciones ya pagadas no se revierten? | `RN-5` lo asume |
| MOD-13 | ¿Se bloquea la obra entera o puede bloquearse un capítulo? | Una novela con un capítulo problemático se pierde entera |
| MOD-14 | ¿Cuenta una obra bloqueada en las estadísticas del autor? | Aparecería un hueco sin explicar |

`MOD-13` tiene más peso del que parece: bloquear una novela de treinta capítulos por uno solo
destruye el trabajo de los otros veintinueve, y también las correcciones que otros
escribieron sobre ellos.

## Estado

**Especificación:** `DRAFT`.

**Implementación:** `TODO`.
