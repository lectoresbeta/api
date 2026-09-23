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
| `ClaimType` | `INAPPROPRIATE_WORK`, `FRAUDULENT_FEEDBACK`, `ABUSIVE_USER` |
| `ClaimStatus` | `PENDING`, `UNDER_REVIEW`, `UPHELD`, `REJECTED` |
| `ClaimReason` | Motivos por tipo. Para feedback: `NO_VALUE`, `TOO_SHORT`, `OFF_TOPIC`, `OFFENSIVE` |
| `SanctionType` | Por definir (`MOD-1`) |

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
| `ClaimSubmitted` | Se presenta una reclamación | **`Notification`** (avisa a los moderadores) |
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

## El problema que hay que resolver antes de implementar

**Una reclamación de corrección fraudulenta devuelve créditos al autor.** Eso convierte el
botón de reclamar en una forma de no pagar:

```text
Recibo una corrección → la reclamo como «no aporta valor» → recupero mis créditos
```

Si el moderador la estima, el autor recupera el dinero y el corrector pierde el suyo. Si el
sistema no distingue **una crítica dura de una corrección fraudulenta**, los correctores
aprenderán a escribir elogios, que es exactamente lo contrario del producto.

Tres defensas, y conviene tener las tres:

1. **Límite de reclamaciones** por autor y periodo.
2. **Consecuencia por reclamar en falso**: un autor con muchas reclamaciones desestimadas
   pierde la posibilidad de reclamar durante un tiempo.
3. **Criterios explícitos** para el moderador, que separen «no me ha gustado lo que dice» de
   «esto no es una corrección».

Es el mismo problema que `FEAT-FBK-012` aborda desde el otro lado —el fraude del corrector— y
las dos piezas deben diseñarse juntas: son los dos extremos de la misma cuerda.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **MOD-1** | ¿Qué sanciones existen y con qué gravedad? | Sin catálogo no hay sanción que imponer |
| **MOD-2** | ¿Qué límite de reclamaciones y qué consecuencia por reclamar en falso? | Sin ello, reclamar es una forma gratuita de no pagar |
| MOD-3 | ¿Hay vía urgente para contenido gravemente dañino? | Hoy permanece visible hasta que alguien lo revise |
| MOD-4 | ¿Puede el reclamado alegar antes de la decisión? | Cambia el ciclo de vida entero |
| MOD-5 | ¿Se agrupan varias reclamaciones sobre el mismo objeto? | Diez denuncias del mismo texto no son diez expedientes |
| MOD-6 | ¿Quién concede el rol de moderador? | Hace falta un rol por encima: `Admin` |
| MOD-7 | ¿Qué pasa con las correcciones ya recibidas de una obra bloqueada? | Los correctores cobraron de buena fe |
| MOD-8 | ¿Puede el autor recurrir el bloqueo permanente de su obra? | «Permanente» sin recurso es fuerte |
| MOD-9 | ¿Qué plazos de respuesta se asumen? | Hay jurisdicciones que los imponen |

## Persistencia

Tablas propias: `claim`, `claim_review`, `sanction`, `moderator_role`, `audit_entry`.

Referencias a objetos de otros contextos **por identificador y tipo** (`targetType`,
`targetId`), nunca por clave foránea: una reclamación sobre una obra no puede impedir que
`Work` borre esa obra.

Eso obliga a aceptar que **un objetivo puede dejar de existir**, y la reclamación tiene que
seguir siendo legible sin él.
