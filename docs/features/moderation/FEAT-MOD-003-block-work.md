---
id: FEAT-MOD-003
title: Bloquear un capítulo o una obra por reclamación estimada
context: Moderation
concept: Claim
actors: []
spec_status: APPROVED
impl_status: PARTIAL
priority: P1
sources:
  - conversation:2026-09-23 (backoffice de administración)
endpoints: []
events: [ClaimUpheld, WorkBlockedByModeration]
depends_on: [FEAT-MOD-002, FEAT-WRK-016]
updated: 2026-09-25
---

# FEAT-MOD-003 — Bloquear por reclamación

## Resumen

**Se bloquea lo que se reclamó.** Si la reclamación era sobre un capítulo, se bloquea ese
capítulo; si era sobre la obra, la obra entera.

Y hay un umbral: **una obra con 3 capítulos bloqueados queda bloqueada por completo**.

Lo bloqueado deja de ser accesible para todos **salvo para su autor**, que lo sigue viendo
marcado como bloqueado por reclamación.

## Quién ejecuta el bloqueo

**`Work`, no `Moderation`.** `Moderation` publica `ClaimUpheld` y `Work` lo interpreta como un
cambio en el ciclo de vida de la obra, que es su modelo.

Si `Moderation` escribiese el estado directamente, tendría que conocer las reglas de
transición de `Work`, y a partir de ahí dos contextos gobernarían el mismo dato.

## Bloqueo por capítulo, con umbral

Bloquear una novela de treinta capítulos por uno solo destruiría el trabajo de los otros
veintinueve **y las correcciones que otros escribieron sobre ellos**. De ahí la granularidad:

```text
Reclamación sobre el capítulo 7  →  se bloquea el capítulo 7
Reclamación sobre la obra        →  se bloquea la obra
3 capítulos bloqueados           →  se bloquea la obra entera
```

El umbral de 3 es lo que impide el otro extremo: una obra cuyos capítulos van cayendo uno a
uno seguiría publicada indefinidamente, ofreciendo una lectura llena de huecos.

| Estado | Quién lee | Quién corrige |
|---|---|---|
| `DRAFT` | Solo el autor | Nadie |
| `PUBLISHED` | Todos | Nadie |
| `IN_CORRECTION` | Todos | Lectores beta |
| **`BLOCKED`** | **Solo el autor**, marcado | **Nadie** |

`BLOCKED` aplica tanto a un capítulo como a una obra, y **no se sale de él por ninguna
transición ordinaria** ([`FEAT-WRK-016`](../work/FEAT-WRK-016-work-status.md)): solo lo revoca
un moderador.

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
- `RN-7` El bloqueo es **indefinido**, y **solo un moderador puede revocarlo**.
- `RN-8` El autor **no puede recurrir desde la plataforma**: lo hace **por correo** (`MOD-8`).
  Sin formulario de recurso, sin cola de apelaciones, sin estado nuevo en el expediente.
- `RN-8b` Al producirse el bloqueo se le **envía un correo** que dice qué se ha bloqueado, por
  qué y **a qué dirección debe escribir si quiere recurrir** (`MOD-40`). Es un mensaje
  **operativo**: se envía aunque tenga las notificaciones desactivadas
  ([`FEAT-USR-039`](../user/FEAT-USR-039-notification-preferences.md) `RN-3`).
- `RN-9` Al tercer capítulo bloqueado, **la obra entera queda bloqueada** automáticamente.
- `RN-10` Desbloquear un capítulo **no desbloquea la obra** si sigue habiendo 3 bloqueados.

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

## El correo de bloqueo

Es la única vía por la que el autor se entera y la única por la que puede reaccionar, así que
tiene que llevar las tres cosas:

| Qué | Por qué |
|---|---|
| **Qué** se ha bloqueado | Capítulo concreto u obra entera |
| **Por qué** | El motivo de la reclamación estimada, no la motivación interna del moderador |
| **Dónde recurrir** | La dirección a la que escribir |

Sin la tercera, el recurso existe sobre el papel y no en la práctica: **un recurso que no se
sabe dónde presentar no existe**.

## Criterios de aceptación

- [x] La obra desaparece del catálogo, del perfil y de las búsquedas.
- [x] El autor la sigue viendo, marcada como bloqueada, con el motivo.
- [ ] Sus enlaces públicos dejan de servir contenido.
- [ ] Las correcciones en curso se cancelan sin cargo, y se avisa a quien las escribía.
- [x] Las correcciones ya pagadas **no** se revierten.
- [x] El contenido no se borra.
- [x] `BLOCKED` no se abandona por ninguna transición ordinaria.
- [x] Una reclamación sobre un capítulo bloquea ese capítulo, no la obra.
- [x] Al tercer capítulo bloqueado, la obra entera queda bloqueada.
- [ ] Un moderador puede revocar un bloqueo.
- [x] No existe ningún endpoint de recurso: el recurso es por correo.
- [x] El correo de bloqueo indica qué, por qué y **a qué dirección recurrir**.
- [x] Ese correo se envía aunque el usuario tenga las notificaciones desactivadas.
- [x] El cambio de estado lo ejecuta `Work`, no `Moderation`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| MOD-14 | ¿Cuenta una obra bloqueada en las estadísticas del autor? | Aparecería un hueco sin explicar |
| MOD-41 | El umbral de 3, ¿es absoluto o proporcional a la longitud de la obra? | 3 de 4 capítulos y 3 de 40 no son lo mismo |

Resueltas: `MOD-13` (**por capítulo**, con umbral de 3 para la obra entera), `MOD-8`
(**recurso por correo**, y el bloqueo lo revoca un moderador), `MOD-40` (**la dirección va en
el correo de bloqueo**) y `MOD-7` (las correcciones ya pagadas **no se revierten**).

`MOD-41` merece un momento: en un relato de cuatro capítulos, tres bloqueados son el 75% de la
obra y el umbral llega tarde; en una novela de cuarenta, son el 7% y llega pronto.

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `PARTIAL` (2026-09-25). El bloqueo funciona de punta a punta: `Work`
consume `ClaimUpheld`, bloquea **lo que se reclamó** —el capítulo o la obra—, cuenta y al
tercer capítulo bloqueado tumba la obra entera, y publica `WorkBlockedByModeration`. El autor
recibe el correo operativo con qué, por qué y a qué dirección escribir
(`MODERATION_APPEALS_EMAIL`).

Dos detalles de implementación que conviene dejar escritos:

- **el umbral se cuenta después de confirmar el bloqueo del capítulo**, no dentro de la misma
  transacción. Contarlo dentro leería un estado en el que ese capítulo todavía no está
  bloqueado, y la obra no caería nunca — que es exactamente lo que ocurrió al escribirlo;
- **la reentrega no vuelve a anunciar nada.** El mensaje puede llegar dos veces y `block()` es
  idempotente, así que lo que decide si se publica el hecho es si el estado cambió de verdad:
  un correo de bloqueo repetido es, para quien lo recibe, un segundo bloqueo.

**Lo que no está:**

- **`RN-4`: cancelar las correcciones en curso** sobre la obra bloqueada, sin cargo y
  avisando a quien las escribía. Hoy quien estuviera corrigiendo descubre el bloqueo al
  intentar entregar. Es la deuda más incómoda de las tres, porque la ficha subraya que a esa
  persona hay que avisarla y que el mensaje no la haga pensar que hizo algo mal;
- **`RN-7`: revocar un bloqueo.** Solo un moderador puede, dice la regla, y todavía no hay
  por dónde: el modelo lo admite (`unblock()`), falta el endpoint y su registro de auditoría.
  Mientras tanto, un bloqueo es definitivo de hecho, no solo de derecho;
- **`RN-3`: los enlaces públicos.** No dejan de servir contenido porque todavía no sirven
  ninguno: `PublicLink` existe como entidad y no tiene endpoint. Cuando lo tenga, tendrá que
  mirar `blocked_at` como ya hace el catálogo;
- **`Community`** no retira todavía la obra de muros y recomendaciones: ese contexto está sin
  escribir. El hecho ya se publica, así que es un consumidor más el día que exista.

`MOD-14` y `MOD-41` siguen abiertas. `MOD-41` merece atención antes de que haya obras largas
en el sistema: tres capítulos de cuatro son el 75% de la obra y el umbral llega tarde; tres de
cuarenta son el 7% y llega pronto.
