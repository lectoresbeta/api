---
id: FEAT-CRD-017
title: Propina del autor a una buena corrección
context: Credits
concept: Transaction
actors: [Writer]
spec_status: DRAFT
impl_status: TODO
priority: P2
sources:
  - conversation:2026-09-23 (rediseño del sistema de créditos)
  - docs/decisions/0006-credit-system.md
endpoints:
  - POST /corrections/{correctionId}/tip
events: [CorrectionTipped]
depends_on: [FEAT-CRD-016]
updated: 2026-09-23
---

# FEAT-CRD-017 — Propina del autor

## Resumen

El autor puede dar créditos extra **de su propio saldo** a una corrección que le ha servido.

Sustituye a la bonificación automática por «feedback valorado positivamente», y la mejora en
dos frentes a la vez.

## Por qué una propina y no una bonificación del sistema

| | Bonificación automática | **Propina** |
|---|---|---|
| De dónde salen los créditos | De la nada: es un grifo | **Del saldo del autor**: es una transferencia |
| Efecto en la masa | La aumenta | **Ninguno** |
| Colusión entre dos cuentas | Cada una gana 5 por valorarse | **Neto cero**: lo que A da, A lo pierde |
| Qué significa | Un clic gratis | **Algo que a quien lo da le cuesta** |

La tercera fila es la decisiva. Con una bonificación del sistema, dos cuentas que se valoran
mutuamente ganan créditos sin aportar nada. Con propina, el intercambio se anula solo: **el
fraude no tiene premio que repartir**.

Y la cuarta es la que hace que funcione como señal de calidad. Un «me ha servido» que cuesta
créditos significa mucho más que uno que no cuesta nada.

## Reglas de negocio

- `RN-1` La propina sale del **saldo disponible** del autor. Sin disponible, no hay propina:
  a diferencia del pago de una corrección, esto **no** genera descubierto.
- `RN-2` Solo el **autor de la obra** puede propinar, y solo sobre correcciones de su obra.
- `RN-3` El importe está acotado (`C-23`). Propuesta: entre 1 y 5.
- `RN-4` Es **opcional** y **única** por corrección: no se propina dos veces la misma.
- `RN-5` Es **irrevocable**. Como cualquier movimiento, no se deshace: se compensa con otro,
  y aquí no hay compensación posible.
- `RN-6` No se puede propinar una corrección **por enlace público**: no hay cuenta a la que
  abonar ([`FEAT-FBK-008`](../feedback/FEAT-FBK-008-public-link-correction.md)).
- `RN-7` La propina **no influye en el precio** de futuras correcciones ni en ninguna fórmula.
  Es un regalo, no una tarifa.

`RN-1` es deliberada: el descubierto existe para que un lector nunca trabaje sin cobrar, y una
propina es voluntaria. Permitir endeudarse por ser generoso sería una trampa.

`RN-5` merece que la interfaz lo advierta antes de confirmar.

## Flujo principal

1. El autor lee una corrección recibida.
2. Decide propinar y elige el importe.
3. Se comprueba su disponible.
4. Se carga al autor y se abona al lector.
5. Se publica `CorrectionTipped`.
6. `Notification` avisa al lector.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| Sin disponible | Se rechaza | `409` |
| Importe fuera de rango | Se rechaza | `422` |
| Ya propinada | Se rechaza | `409` |
| No es su obra | Se rechaza | `403` |
| Corrección por enlace público | Se rechaza | `409` |
| Petición repetida | Devuelve la propina existente | `200` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Propinar | `POST /corrections/{correctionId}/tip` | `tipCorrection` |

Admite `Idempotency-Key`: es un movimiento de créditos y no puede duplicarse por un reintento
de red.

## Eventos

**Publica**

| Evento | Cuándo | Consumidores |
|---|---|---|
| `CorrectionTipped` | Al propinar | `Notification`, `Community` (reputación del corrector) |

Que `Community` lo consuma es lo que permite que la propina alimente la **reputación** sin que
`Credits` sepa nada de rankings.

## Efectos en créditos

Cargo al autor y abono al lector por el mismo importe. Masa constante.

## Criterios de aceptación

- [ ] Un autor puede propinar una corrección recibida y el lector recibe el importe exacto.
- [ ] Sin saldo disponible, la propina se rechaza y **no** genera saldo negativo.
- [ ] No se puede propinar dos veces la misma corrección.
- [ ] No se puede propinar una corrección ajena.
- [ ] Las correcciones por enlace público no admiten propina.
- [ ] La propina no altera el precio de ninguna corrección futura.
- [ ] Dos peticiones con la misma `Idempotency-Key` producen un solo movimiento.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| C-23 | ¿Qué rango tiene la propina? | Propuesta: 1 a 5 |
| C-24 | ¿Se ve públicamente qué correcciones han sido propinadas? | Sería una señal de calidad fuerte, y también de exposición |
| C-25 | ¿Cuenta la propina para la reputación del corrector, y cuánto? | Ligado a `CM-4` |
| C-26 | ¿Hay un tope de propinas por autor y periodo? | Sin tope no hay fraude —es neto cero— pero sí ruido |

## Estado

**Especificación:** `DRAFT`.

**Implementación:** `TODO`.
