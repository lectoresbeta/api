---
id: FEAT-MOD-001
title: Presentar una reclamación
context: Moderation
concept: Claim
actors: [User, Writer]
spec_status: APPROVED
impl_status: PARTIAL
priority: P1
sources:
  - conversation:2026-09-23 (backoffice de administración)
  - docs/bounded-contexts/moderation.md
endpoints:
  - POST /claims
events: [ClaimSubmitted]
depends_on: []
updated: 2026-09-24
---

# FEAT-MOD-001 — Presentar una reclamación

## Resumen

Un usuario señala contenido ajeno para que un moderador lo revise.

| Quién | Sobre qué | Motivos típicos |
|---|---|---|
| Cualquier lector | Una **obra** inapropiada | Contenido ilegal, ofensivo, plagio |
| El autor | Una **corrección** recibida | No aporta valor, demasiado breve, fuera de tema, ofensiva |
| Cualquier usuario | Otro **usuario** | Comportamiento abusivo |

**No produce ningún efecto inmediato.** Se registra y espera.

## Por qué no hay efecto inmediato

Es la decisión que define el mecanismo. Si reclamar ocultase el contenido, congelase créditos
o avisase al reclamado, **el botón de denunciar sería un arma**: bastaría con reclamar para
silenciar a alguien, o para dejar de pagar una corrección que no gustó.

Lo que se paga a cambio es real y conviene decirlo: **un contenido genuinamente dañino
permanece visible hasta que alguien lo revise**. Si eso resulta inaceptable para algún tipo de
contenido, la salida no es ocultar por defecto sino abrir una vía urgente con criterios
estrictos (`MOD-3`).

## Actores y autorización

| Actor | Puede reclamar | Condición |
|---|---|---|
| `User` | Una obra que puede ver | Cuenta activada |
| `Writer` | Una corrección de **su** obra | Es el autor |
| `User` | Otro usuario | Cuenta activada |

Nadie reclama sobre lo propio.

## Reglas de negocio

- `RN-1` La reclamación **no produce ningún efecto**: ni oculta, ni congela, ni avisa al
  reclamado.
- `RN-2` Un usuario **no reclama dos veces** sobre el mismo objeto. Reintentar devuelve la
  reclamación existente.
- `RN-3` Toda reclamación lleva **un motivo del catálogo** y admite texto libre. El catálogo
  ordena el trabajo del moderador; el texto libre es donde está la información real.
- `RN-4` Reclamar una **corrección** solo puede hacerlo el autor de la obra corregida.
- `RN-5` Solo se puede reclamar una corrección **visible**: una bloqueada por descubierto aún
  no se ha leído ([`FEAT-CRD-018`](../credits/FEAT-CRD-018-negative-balance.md)).
- `RN-6` **Máximo 3 reclamaciones al mes** por usuario.
- `RN-6b` Cada reclamación **desestimada bloquea el botón de reclamar**, y el bloqueo es
  **acumulativo**: la primera desestimada bloquea una semana, la segunda dos, la tercera tres,
  y así sucesivamente.
- `RN-6c` Un **invitado sin cuenta puede reclamar** (`MOD-10`), pero no podrá seguir la
  reclamación ni hablar con el moderador después.
- `RN-6d` Quien esté bloqueado o sin cupo ve, **junto al botón deshabilitado, un enlace para
  reclamar por correo** (`MOD-22`). Un moderador la registra después en su nombre. La vía es
  más lenta, pero existe siempre.
- `RN-7` El reclamante **no conoce la identidad del moderador** que la revisa.
- `RN-8` Una corrección **por enlace público** no admite reclamación de créditos —no los
  movió— pero sí por contenido inapropiado (`C-46`).
- `RN-9` Varias reclamaciones sobre **el mismo objeto se agrupan en un solo expediente**
  (`MOD-5`). Diez denuncias del mismo texto son una decisión, no diez, y el número de
  denunciantes es en sí mismo una señal para el moderador.

`RN-5` evita una vía de abuso sutil: reclamar a ciegas una corrección que aún no se ha podido
leer, solo para no pagarla.

## El límite no es una formalidad

**Reclamar una corrección devuelve créditos al autor si se estima.** Eso convierte el botón en
una forma de no pagar:

```text
Recibo una corrección → la reclamo como «no aporta valor» → recupero mis créditos
```

Si el sistema no distingue **una crítica dura de una corrección fraudulenta**, los correctores
aprenderán a escribir elogios, que es exactamente lo contrario del producto.

De ahí las dos defensas, que funcionan juntas:

| Defensa | Cómo |
|---|---|
| **Tope** | 3 reclamaciones al mes por usuario |
| **Coste de fallar** | Cada desestimación bloquea el botón, de forma **acumulativa** |

```text
1.ª desestimada  →  1 semana sin poder reclamar
2.ª desestimada  →  2 semanas
3.ª desestimada  →  3 semanas
…
```

El crecimiento acumulativo es lo que hace que el sistema se defienda solo: reclamar a la
ligera es barato la primera vez y caro la cuarta. Quien reclama de buena fe rara vez acumula
desestimaciones; quien lo usa para no pagar, sí.

### Pero la puerta nunca se cierra del todo

Un usuario bloqueado podría encontrarse algo genuinamente grave y no poder denunciarlo. Por
eso, **junto al botón bloqueado hay un enlace para escribir por correo** (`MOD-22`).

| | Vía normal | **Vía por correo** |
|---|---|---|
| Quién la usa | Cualquiera con cupo | Quien está bloqueado o ha agotado el cupo |
| Velocidad | Inmediata | Lenta: alguien tiene que leer el correo |
| Cómo entra al sistema | Directamente | Un moderador **la crea en nombre del usuario** desde el backoffice ([`FEAT-MOD-005`](FEAT-MOD-005-user-management.md)) |

Es deliberadamente más lenta. El tope y el bloqueo siguen cumpliendo su función —desincentivar
reclamar a la ligera— pero **nadie se queda sin forma de avisar de algo serio**.

**Una reclamación registrada por esa vía consume cupo y cuenta para el bloqueo acumulativo**
igual que cualquier otra (`MOD-45`). Si no lo hiciera, el correo sería sencillamente la forma
de saltarse el límite.

Lo que cambia no es el coste, es **quién decide que merece entrar**: en la vía normal decide
el usuario; en esta, un moderador que ha leído el correo.

**Y el crédito no se mueve hasta que un moderador aprueba.** Mientras la reclamación está
pendiente, el corrector conserva lo que cobró: no hay ningún estado intermedio en el que el
dinero esté en el aire.

## Flujo principal

1. El usuario abre el menú del contenido y elige reclamar.
2. Selecciona motivo y, opcionalmente, explica.
3. Se registra la reclamación en `PENDING`.
4. Se publica `ClaimSubmitted`.
5. `Notification` avisa por correo a los moderadores.
6. El usuario recibe confirmación de que se ha registrado. **Nada más ocurre.**

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Ya reclamó ese objeto | Devuelve la existente | `200` |
| Reclama contenido propio | Se rechaza | `403` |
| Reclama una corrección que no es de su obra | Se rechaza | `403` |
| Reclama una corrección bloqueada | Se rechaza | `409` |
| Supera las 3 del mes | Se rechaza, con explicación | `429` |
| Está bloqueado por desestimaciones | Se rechaza, diciendo hasta cuándo **y ofreciendo la vía por correo** | `429` |
| Motivo fuera del catálogo | Se rechaza | `422` |
| Cuenta sin activar | Se rechaza | `403` |
| El objeto ya no existe | Se rechaza | `404` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Presentar reclamación | `POST /claims` | `submitClaim` |
| Ver mis reclamaciones | `GET /me/claims` | `listMyClaims` |

La respuesta **no dice qué va a pasar**, porque no va a pasar nada todavía. Confirma el
registro y da un identificador con el que el usuario puede seguirla.

## Eventos

**Publica**

| Evento | Cuándo | Payload |
|---|---|---|
| `ClaimSubmitted` | Al registrarse | `claimId`, `type`, `targetType`, `targetId`, `reporterId`, `reason`, `submittedAt` |

**No lleva el texto libre del reclamante.** Es material que puede acusar a alguien y no tiene
por qué circular por la cola: `Notification` avisa de que hay trabajo, no reproduce la
acusación.

## Modelo de datos afectado

`claim`: `id`, `type`, `target_type`, `target_id`, `reporter_id`, `reason`, `description`,
`status`, `submitted_at`.

Referencia al objetivo **por tipo e identificador**, sin clave foránea: una reclamación no
puede impedir que otro contexto borre lo suyo.

Índice único `(reporter_id, target_type, target_id)` para `RN-2`.

## Criterios de aceptación

- [ ] Presentar una reclamación no cambia nada visible para nadie más que para el reclamante.
- [ ] El reclamado **no se entera** de que existe.
- [ ] Un usuario no puede reclamar dos veces el mismo objeto.
- [ ] Solo el autor de la obra puede reclamar sus correcciones.
- [ ] Una corrección bloqueada no se puede reclamar.
- [ ] Superar las 3 del mes devuelve `429` con explicación.
- [ ] Tras `N` desestimaciones, el bloqueo dura `N` semanas.
- [ ] Un invitado sin cuenta puede reclamar.
- [ ] Quien está bloqueado ve el enlace para reclamar por correo junto al botón deshabilitado.
- [ ] Los créditos **no se mueven** mientras la reclamación está pendiente.
- [ ] El evento publicado no contiene el texto libre del reclamante.
- [ ] Si el objeto reclamado desaparece, la reclamación sigue siendo legible.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **MOD-21** | ¿Se reinicia el contador de desestimadas en algún momento? | Sin reinicio, un error de hace dos años sigue pesando |
| C-46 | ¿Puede el autor reclamar una corrección por enlace público? | No hay créditos que devolver, pero sí contenido que moderar |

Resueltas: `MOD-2` (**3 al mes** con bloqueo acumulativo), `MOD-5` (**se agrupan**), `MOD-10`
(**sí puede** reclamar un invitado), `MOD-22` (**vía alternativa por correo**) y `MOD-45` (esa
vía **consume cupo** igualmente).

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `TODO`.

## Estado de la implementación

`PARTIAL` (2026-09-25). Funciona el camino de quien tiene sesión; faltan las dos vías de
excepción.

Hecho: el registro sin efectos (`RN-1`), una por persona y objeto con reintento idempotente
(`RN-2`), el motivo del catálogo con texto libre (`RN-3`), las dos reglas de la corrección
—solo su autor (`RN-4`) y solo si ya se ha podido leer (`RN-5`)—, el cupo mensual (`RN-6`) y
el bloqueo acumulativo (`RN-6b`).

**Falta `RN-6c`: un invitado sin cuenta no puede reclamar todavía.** El modelo exige un
`reporterId`, y admitir a alguien sin cuenta significa decidir qué se guarda en su lugar —una
dirección de contacto, presumiblemente— y qué pasa con el cupo, que hoy es por cuenta. No es
una línea de código y no entra aquí.

**Falta `RN-6d`**, la vía por correo para quien está bloqueado o sin cupo. El modelo ya la
anticipa con `filedOnBehalf`, y lo que falta es el endpoint por el que un moderador registra
una reclamación en nombre de otro — que encaja mejor junto al backoffice de
[`FEAT-MOD-002`](FEAT-MOD-002-review-claim.md).

**Falta `RN-9`**, agrupar varias reclamaciones sobre el mismo objeto en un expediente. Hoy
cada una es una fila; agruparlas es trabajo del lado que las revisa, no del que las recibe.

Una decisión de implementación: `targetType` de `CHAPTER`, `POST` y `POST_COMMENT` registra la
reclamación **sin saber contra quién va**. No hay contrato todavía que diga de quién es un
capítulo o una publicación, y no poder denunciar sería peor que no saber aún el sujeto — el
moderador lo averigua al abrirla.
