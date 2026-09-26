---
id: FEAT-CRD-017
title: Propina del autor a una buena corrección
context: Credits
concept: Transaction
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - conversation:2026-09-23 (rediseño del sistema de créditos)
  - docs/decisions/0006-credit-system.md
endpoints:
  - POST /corrections/{correctionId}/tip
events: [CorrectionTipped]
depends_on: [FEAT-CRD-016]
updated: 2026-09-25
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
- `RN-3` El importe está **entre 1 y 5 créditos**. Por encima de 5 la propina se parece al
  precio de la corrección y deja de leerse como un extra.
- `RN-3b` **Sin tope** de propinas por autor o periodo: es su saldo y el sistema no pierde
  nada (es neto cero).
- `RN-3c` La propina es **visible para el corrector** y se agrega en su perfil («142 créditos
  recibidos en propinas»), pero **no se publica corrección a corrección**: exponer quién
  recibe reconocimiento y quién no desanima al corrector novato.
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
| No es su obra | Se rechaza | `404` |
| Corrección por enlace público | Se rechaza | `404` |
| Petición repetida con la misma `Idempotency-Key` | Devuelve el saldo, sin mover nada | `200` |
| Petición repetida sin llave, o con otra | Se rechaza | `409` |

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

- [x] Un autor puede propinar una corrección recibida y el lector recibe el importe exacto.
- [x] Sin saldo disponible, la propina se rechaza y **no** genera saldo negativo.
- [x] No se puede propinar dos veces la misma corrección.
- [x] No se puede propinar una corrección ajena.
- [x] Las correcciones por enlace público no admiten propina.
- [x] La propina no altera el precio de ninguna corrección futura.
- [x] Dos peticiones con la misma `Idempotency-Key` producen un solo movimiento.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| CM-4 | ¿Cuánto pesa la propina en la fórmula de relevancia? | Es la señal de calidad más fiable del sistema |

Resueltas: `C-23` (**1 a 5**), `C-24` (visible al corrector y **agregada** en su perfil),
`C-25` (**sí, y es la señal principal** de reputación) y `C-26` (**sin tope**).

`C-25` merece subrayarse: la propina es la única métrica de calidad que alguien ha **pagado de
su bolsillo**, así que es mucho más difícil de falsear que un «me gusta».

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `DONE` (2026-09-25). Cargo y abono en la misma transacción, `CorrectionTipped`
publicado, y `Feedback` y `Community` proyectándolo cada uno a lo suyo.

Cuatro cosas que la ficha no preveía y que la implementación obligó a decidir:

- **el endpoint vive en `Credits`, no en `Feedback`.** La ficha pedía comprobar el disponible
  antes de aceptar; con el endpoint en `Feedback`, esa comprobación sería una pregunta a
  `Credits`, y este contexto no publica contratos
  ([`decision:0002`](../../decisions/0002-credits-as-isolated-bounded-context.md)). Aceptar la
  intención y aplicarla después por un evento obligaría a compensar cuando el saldo no llegara,
  que es mucha maquinaria para un gesto voluntario. Y no hace falta preguntar nada: `Credits`
  ya sabe de quién es cada corrección, porque tiene apuntado quién pagó y quién cobró;
- **«no es tuya» se contesta `404` y no `403`.** Distinguirlas diría algo sobre las
  correcciones de otra persona: que existe una con ese identificador. Las tres situaciones —no
  existe, no es suya, no se ha cobrado— comparten respuesta;
- **`RN-6` se cumple sin código propio.** Una corrección por enlace público no costó nada y no
  pagó a nadie, así que `Credits` no tiene ningún cargo apuntado sobre ella y responde como
  ante una ajena. El código `NOBODY_TO_TIP` queda como red de seguridad para un libro mayor
  incoherente —un cargo sin abono—, que es un estado que no debería existir;
- **la `Idempotency-Key` se guarda en el propio movimiento**, no en una tabla de respuestas.
  Basta porque la corrección admite una sola propina: el movimiento duplicado no puede existir,
  y lo único que la llave decide es si repetir la petición se lee como un reintento —y contesta
  el saldo— o como una segunda propina, que se rechaza.

**Falta** el agregado de `RN-3c` en el perfil del corrector —«142 créditos recibidos en
propinas»—. La cifra ya se acumula en `Community` al consumir `CorrectionTipped`; lo que no hay
es la pantalla ni el endpoint que la enseñe, y eso pertenece al perfil público
([`FEAT-USR-014`](../user/FEAT-USR-014-view-public-profile.md)). El corrector sí ve, hoy, que una
corrección suya fue propinada y el abono en su histórico de movimientos.

Y **falta el aviso al lector** del paso 6 del flujo: `Notification` todavía no consume
`CorrectionTipped`.
