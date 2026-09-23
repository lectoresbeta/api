# Bounded context: `Moderation`

> Estado: `DRAFT` — Prefijo: `MOD` — Origen: conversación 2026-09-23

## Responsabilidad

Recibir las **reclamaciones** que los usuarios presentan sobre contenido ajeno, ponerlas en
manos de un moderador y registrar su decisión y sus efectos.

Es el contexto que existe para que la plataforma tenga una respuesta cuando algo va mal:
un texto inapropiado, una corrección que no aporta nada, un usuario que se comporta de forma
abusiva.

## Por qué es un contexto aparte

Podría parecer que la moderación es una pantalla más del backoffice. No lo es, y conviene
verlo antes de empezar:

- **Tiene su propio ciclo de vida**, independiente de lo que modera. Una reclamación nace,
  espera, se revisa y se resuelve, y eso ocurra sobre una obra, una corrección o un usuario.
- **Cruza todos los contextos.** Resolver una reclamación puede mover créditos, bloquear una
  obra y sancionar a un usuario: tres contextos distintos.
- **Su modelo no se parece al de nadie.** Una reclamación no es una obra ni un comentario: es
  un expediente.
- **Sus reglas cambian solas.** La política de moderación evoluciona por motivos legales y de
  producto que no tienen nada que ver con el resto del sistema.

Meterla dentro de `Community` o de `Feedback` acabaría con cada contexto conociendo las reglas
de sanción de los demás.

## Qué posee

- Las **reclamaciones** y su ciclo de vida.
- La **decisión** del moderador y su motivación.
- Las **sanciones** impuestas y su vigencia.
- El **rol de moderador** y quién lo tiene.
- El **registro de auditoría** de toda acción administrativa.
- Los **ajustes manuales de créditos** hechos desde el backoffice, como hecho administrativo
  (el movimiento lo aplica `Credits`).

## Qué NO posee

| No es suyo | Es de |
|---|---|
| Las obras y su contenido | `Work` |
| Las correcciones | `Feedback` |
| Los comentarios y publicaciones | `Community` |
| Las cuentas de usuario | `User` |
| El saldo y los movimientos de créditos | `Credits` |
| El envío de los correos | `Notification` |

**`Moderation` decide; los demás aplican.** Publica el resultado de una decisión y cada
contexto lo interpreta en su modelo: `Credits` revierte el movimiento, `Work` bloquea la obra,
`User` aplica la sanción. Si `Moderation` escribiese directamente en esas tablas, se
convertiría en el contexto que lo sabe todo sobre todos.

## Conceptos

| Concepto | Responsabilidad |
|---|---|
| `Claim` | La reclamación: quién, sobre qué, por qué, y en qué estado |
| `Review` | La decisión del moderador, su motivación y cuándo se tomó |
| `Sanction` | Medida impuesta a un usuario, con alcance y vigencia |
| `ModeratorRole` | Quién puede moderar y quién recibe los avisos |
| `Conversation` | Los hilos privados entre el moderador y cada parte |
| `ContentReview` | La revisión automática previa a la publicación |
| `AuditLog` | Registro inmutable de toda acción administrativa |

## Agregados

| Agregado | Identidad | Invariantes |
|---|---|---|
| `Claim` | `ClaimId` | Estado `PENDING → UNDER_REVIEW → UPHELD \| REJECTED`. **Nunca vuelve atrás.** Un mismo usuario no reclama dos veces sobre el mismo objeto |
| `Sanction` | `SanctionId` | Tiene alcance, motivo y una reclamación que la originó. Las temporales caducan solas |
| `AuditEntry` | `AuditEntryId` | **Inmutable.** Quién, qué, cuándo, sobre qué y por qué |

### Value objects y enums

| Nombre | Valores |
|---|---|
| `ClaimType` | `INAPPROPRIATE_WORK`, `INAPPROPRIATE_CHAPTER`, `FRAUDULENT_FEEDBACK`, `ABUSIVE_USER`, `MISLABELLED_CONTENT` |
| `ClaimStatus` | `PENDING`, `UNDER_REVIEW`, `UPHELD`, `REJECTED` |
| `ClaimReason` | Motivos por tipo. Para feedback: `NO_VALUE`, `TOO_SHORT`, `OFF_TOPIC`, `OFFENSIVE` |
| `SanctionType` | `WARNING`, `PARTIAL_SUSPENSION`, `FULL_SUSPENSION`, `EXPULSION` |
| `SuspensionDuration` | `THREE_DAYS`, `ONE_WEEK`, `ONE_MONTH` (solo parcial) |
| `ThreadParty` | `REPORTER`, `SUBJECT` |

## El ciclo de una reclamación

```text
Un lector ve un texto inapropiado
o un autor recibe una corrección que no aporta nada
        ↓
Presenta la reclamación          ← NINGÚN EFECTO INMEDIATO
        ↓
Claim en PENDING
        ↓
Notification avisa por correo a los moderadores
        ↓
Un moderador la revisa           ← no puede ser parte implicada
        ↓
   ┌────┴────┐
UPHELD    REJECTED
   ↓            ↓
Efectos      Se archiva
por tipo     con motivo
```

**Presentar una reclamación no produce ningún efecto.** Ni oculta el contenido, ni congela
créditos, ni avisa al reclamado. Es una decisión deliberada: lo contrario convertiría el
botón de denunciar en un arma.

Tiene un coste que conviene asumir de frente: **un contenido genuinamente dañino permanece
visible hasta que alguien lo revise**. Si eso resulta inaceptable para algún tipo de
contenido, la solución no es ocultar por defecto sino abrir una **vía urgente** con criterios
estrictos (`MOD-3`).

## Efectos de una reclamación estimada

| Tipo | Efecto |
|---|---|
| **Corrección fraudulenta** | Se **revierten los créditos**: se devuelven al autor y se retiran al corrector. Se notifica por correo **a ambos** |
| **Corrección con contenido inapropiado** | Lo anterior, **más sanción** a su autor |
| **Obra inapropiada** | La obra queda **deshabilitada permanentemente**. Sigue siendo visible para su autor, marcada como bloqueada por reclamación |
| **Usuario abusivo** | Sanción, según gravedad |

### La reversión de créditos es un movimiento nuevo

No se edita ni se borra el movimiento original: los movimientos son inmutables
([`decision:0006`](../decisions/0006-credit-system.md)). Una reversión son **dos apuntes
nuevos** —abono al autor, cargo al corrector— con motivo `CLAIM_UPHELD` y referencia a la
reclamación.

Y como el corrector puede haber gastado ya esos créditos, la reversión **puede dejarle en
negativo**. Es coherente con el resto del sistema y no hace falta ninguna regla nueva: con
saldo negativo no recibe correcciones, pero sí puede corregir para saldarlo.

## Eventos publicados

| Evento | Cuándo | Consumidores |
|---|---|---|
| `ClaimSubmitted` | Se presenta una reclamación | **`Notification`** (un correo por reclamación) |
| `ClaimMessageSent` | El moderador o una parte escribe en su hilo | `Notification` |
| `ContentReviewPassed` | El revisor automático aprueba un texto | **`Work`** (lo hace visible) |
| `ContentReviewFlagged` | El revisor lo marca | **`Work`**, `Notification` |
| `ClaimUpheld` | El moderador la estima | **`Credits`**, **`Work`**, **`User`**, `Notification` |
| `ClaimRejected` | El moderador la desestima | `Notification` |
| `SanctionImposed` | Se sanciona a un usuario | **`User`**, `Notification` |
| `SanctionLifted` | Caduca o se levanta | `User`, `Notification` |
| `CreditAdjustmentOrdered` | Ajuste manual desde el backoffice | **`Credits`**, `Notification` |

`ClaimUpheld` lleva **qué se ha estimado y sobre qué**, nunca los efectos concretos: no dice
«devuelve 6 créditos» ni «bloquea la obra». Cada contexto decide qué significa en su modelo,
que es la regla que sostiene todo el sistema
([`decision:0002`](../decisions/0002-credits-as-isolated-bounded-context.md)).

## Eventos consumidos

| Evento | Origen | Efecto |
|---|---|---|
| `UserDeleted` | `User` | Anonimiza al reclamante y al reclamado; **la reclamación permanece** |
| `WorkDeleted` | `Work` | La reclamación sobre esa obra se archiva |

## Reglas de negocio

- `RN-1` Presentar una reclamación **no produce ningún efecto inmediato**.
- `RN-2` Un usuario **no reclama dos veces** sobre el mismo objeto.
- `RN-3` **Nadie modera un asunto en el que es parte**: ni su obra, ni su corrección, ni una
  reclamación que él presentó o que le señala a él.
- `RN-4` Toda decisión lleva **motivación escrita**. Un expediente sin motivo no es auditable
  y no se puede defender si alguien lo discute.
- `RN-5` Toda acción administrativa queda en el **registro de auditoría**, que es inmutable.
- `RN-6` El **estado de una reclamación no retrocede**. Un error se corrige con una acción
  nueva y motivada, no reabriendo el expediente.
- `RN-7` El reclamante **no conoce la identidad del moderador**, ni el reclamado la del
  reclamante.
- `RN-8` Los avisos a moderadores son **operativos**: van a quien tiene el rol y la
  notificación activada, y no dependen de las preferencias generales del usuario.
- `RN-9` Una obra bloqueada por reclamación **sigue siendo accesible para su autor**, marcada
  como tal, y deja de serlo para todos los demás.
- `RN-10` Reclamar en falso de forma reiterada **tiene consecuencias**. Ver abajo.

`RN-3` parece obvio y no lo es: con pocos moderadores, lo más cómodo es que cualquiera revise
cualquier cosa. Es justo el camino por el que se pierde la confianza en el sistema entero.

`RN-7` protege a las dos partes. Quien denuncia a un usuario abusivo no debería quedar
expuesto a él, y un moderador no debería recibir represalias por su decisión.

## La revisión automática, construida vacía

Todo texto pasa por un **revisor automático** antes de ser visible
([`FEAT-MOD-011`](../features/moderation/FEAT-MOD-011-automated-content-review.md)). Hoy ese
revisor **aprueba todo**: es un puerto con una implementación nula, preparada para sustituirse
por IA.

Se construye así a propósito. Abrir el flujo de publicación más adelante, con obras ya
publicadas y estados que inventar, cuesta mucho más que dejar el hueco hecho desde el
principio.

## El etiquetado de contenido sensible defiende en los dos sentidos

El autor declara qué contiene su obra
([`FEAT-WRK-017`](../features/work/FEAT-WRK-017-content-rating.md)) y el lector filtra lo que
no quiere ver ([`FEAT-USR-043`](../features/user/FEAT-USR-043-content-preferences.md)).

| Situación | Qué ocurre con una reclamación |
|---|---|
| Contenido fuerte, **bien etiquetado** | **Se desestima** |
| Contenido fuerte, **sin etiquetar** | **Prospera** |

Con eso, el sistema deja de castigar el contenido difícil y pasa a castigar **el engaño**. La
literatura incómoda tiene derecho a existir; lo que no lo tiene es aparecer sin avisar.

## El problema que hay que resolver antes de implementar

**Una reclamación de corrección fraudulenta devuelve créditos al autor.** Eso convierte el
botón de reclamar en una forma de no pagar:

```text
Recibo una corrección → la reclamo como «no aporta valor» → recupero mis créditos
```

Si el moderador la estima, el autor recupera el dinero y el corrector pierde el suyo. Si el
sistema no distingue **una crítica dura de una corrección fraudulenta**, los correctores
aprenderán a escribir elogios, que es exactamente lo contrario del producto.

Tres defensas, y están las tres:

1. **Tope de 3 reclamaciones al mes** por usuario.
2. **Bloqueo acumulativo por reclamar en falso**: la primera desestimada bloquea una semana,
   la segunda dos, la tercera tres. Reclamar a la ligera es barato la primera vez y caro la
   cuarta.
3. **Criterios explícitos** para el moderador, que separen «no me ha gustado lo que dice» de
   «esto no es una corrección».

Y una cuarta que es estructural: **el crédito no se mueve hasta que un moderador aprueba**. No
hay ningún estado intermedio en el que el dinero esté en el aire, así que reclamar no produce
beneficio por sí solo.

Es el mismo problema que `FEAT-FBK-012` aborda desde el otro lado —el fraude del corrector— y
las dos piezas deben diseñarse juntas: son los dos extremos de la misma cuerda.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **MOD-27** | ¿Qué alcances tiene la suspensión parcial, y son combinables? | Sin alcance, «suspensión parcial» no significa nada |
| **MOD-26** | Si la expulsión anonimiza, ¿cómo se impide volver a registrarse? | No se puede borrar a alguien y recordarlo a la vez |
| **W-20** | ¿Qué catálogo de etiquetas de contenido sensible? | Define el filtro y el criterio del moderador |
| MOD-21 | ¿Se reinicia el contador de reclamaciones desestimadas? | Sin reinicio, un error de hace años sigue pesando |
| MOD-22 | ¿El tope de 3 al mes es por usuario o por tipo? | Quien gaste el cupo reclamando correcciones no podría denunciar algo grave |
| MOD-36 | ¿Se revisa el texto en cada edición o solo al publicar? | Publicar limpio y editar después es el esquive obvio |
| MOD-37 | Si la revisión usa IA externa, ¿sale obra inédita de la plataforma? | Es lo que la plataforma existe para custodiar |
| MOD-9 | ¿Qué plazos de respuesta se asumen? | Hay jurisdicciones que los imponen |
| MOD-41 | El umbral de 3 capítulos, ¿absoluto o proporcional? | 3 de 4 y 3 de 40 no son lo mismo |

**Resueltas el 2026-09-23:** `MOD-1` (cuatro familias de sanciones), `MOD-2` (3 reclamaciones
al mes y bloqueo acumulativo), `MOD-3` (revisión automática, hoy vacía), `MOD-4` (conversación
con el moderador), `MOD-5` (se agrupan), `MOD-6` (comando de consola), `MOD-7` (no se
revierte lo ya pagado), `MOD-8` (recurso por correo), `MOD-10` (un invitado puede reclamar),
`MOD-12` (se revierte la reputación), `MOD-13` (bloqueo por capítulo, umbral de 3), `MOD-15`
(decide el `Admin`), `MOD-16` (un correo por reclamación) y `MOD-17` (segundo factor).

## Persistencia

Tablas propias: `claim`, `claim_review`, `sanction`, `moderator_role`, `audit_entry`.

Referencias a objetos de otros contextos **por identificador y tipo** (`targetType`,
`targetId`), nunca por clave foránea: una reclamación sobre una obra no puede impedir que
`Work` borre esa obra.

Eso obliga a aceptar que **un objetivo puede dejar de existir**, y la reclamación tiene que
seguir siendo legible sin él.
