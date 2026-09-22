# 0004 — Los créditos se reservan al conceder acceso y se confirman al recibir el feedback

- **Estado:** Aceptada
- **Fecha:** 2026-09-22
- **Afecta a:** `Credits`, `Reading`, `Feedback`, `Work`
- **Resuelve:** `C-1`, y con ella `C-2` y `M-1`

## Contexto

Recibir feedback cuesta créditos al autor. `credit-system.pdf` lo enuncia, pero no dice
**cuándo** se cobra ni qué ocurre si el autor no tiene saldo. Era la decisión abierta más
importante del proyecto: condiciona el diseño de tres bounded contexts y el recorrido
principal del producto.

Las tres opciones estaban descritas en
[`FEAT-CRD-006`](../features/credits/FEAT-CRD-006-charge-author-for-received-feedback.md):

| | A. Post-pago | B. Reserva previa | C. Validación síncrona |
|---|---|---|---|
| Cuándo se cobra | Al recibir cada comentario | Se reserva al conceder el acceso, se confirma al recibir el comentario | Se comprueba el saldo antes de aceptar el comentario |
| Riesgo | Saldo negativo sin límite | Créditos inmovilizados | Sigue habiendo carrera entre consulta y gasto |

El modal informativo de créditos del diseño apunta en la misma dirección: dice que los
créditos sirven para *«poner tus obras en corrección»*, es decir, se comprometen al abrir la
obra a feedback y no al recibir cada comentario.

## Decisión

Se adopta la **opción B, reserva previa**.

1. Cuando `Reading` concede acceso de lector beta a una obra, `Credits` **retiene** del saldo
   del autor el coste de un feedback para esa obra.
2. Cuando ese lector envía su feedback, la retención se **confirma**: se convierte en un
   movimiento real de gasto.
3. Si el acceso se revoca, la obra se elimina o la retención caduca sin feedback, se
   **libera** y el saldo vuelve a estar disponible.
4. **Si el autor no tiene saldo disponible, el acceso no se concede.**

De ahí se derivan dos conceptos que antes no existían:

- **Saldo disponible** = saldo − retenciones vigentes. Es el número que gobierna lo que el
  autor puede hacer, y el que se muestra en la interfaz.
- **Retención** (`CreditReservation`), con su propio ciclo de vida: `HELD → CONFIRMED` o
  `HELD → RELEASED`.

Detalle en [`FEAT-CRD-009`](../features/credits/FEAT-CRD-009-reserve-credits-on-access-grant.md).

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| **A. Post-pago** | La más simple; ningún concepto nuevo | El saldo queda negativo y el autor entra en un estado del que no puede salir sin comentar obras ajenas | Deja al usuario bloqueado por una acción que no controla: comentar es decisión de otro |
| **C. Validación síncrona** | Parece la comprobación natural | Acopla `Feedback` a `Credits`, y entre consultar y gastar puede llegar otro cargo | Rompe [`decision:0002`](0002-credits-as-isolated-bounded-context.md) y ni siquiera garantiza el cobro |
| **B2. Reserva por obra** | El autor declara cuántos feedbacks quiere y reserva por todos de una vez; encaja mejor con «poner la obra en corrección» | Hace falta decidir el número de plazas, qué ocurre al agotarlas y cómo se amplían | **No descartada.** Ver `R-1`: es una variante de esta misma decisión, no una alternativa contraria |

## Consecuencias

**Positivas**

- El saldo nunca queda negativo.
- El lector beta nunca pierde su trabajo: si tiene acceso, su feedback se puede pagar.
- El compromiso se adquiere **en el momento correcto**, que es cuando se concede el acceso;
  el comentario es solo su consecuencia.
- El autor sabe de antemano cuánto le va a costar abrir su obra.
- Desaparece la necesidad de decidir qué hacer con un saldo negativo.

**Negativas**

- **Aparece una compensación entre contextos.** `Reading` concede el acceso y `Credits` puede
  rechazar la retención después, de forma asíncrona. Hay que deshacer el acceso.
- Créditos inmovilizados en accesos cuyo lector nunca comenta. Se mitiga con caducidad.
- Dos números de saldo en lugar de uno. Hay que ser explícito en cada pantalla y en cada
  endpoint sobre cuál se está mostrando.
- Un autor sin saldo disponible deja de poder recibir lectores beta, no solo comentarios.
  Es el efecto buscado, pero hay que comunicarlo bien.

**Coste de revertirla**

Alto. La retención es un agregado con ciclo de vida propio y su ausencia cambia el contrato
de tres contextos. Conviene cerrar además `R-1` antes de implementar.

> **Actualización 2026-09-22.** La corrección resulta ser **por capítulo** (`R-2`), no por
> obra. El acceso de lector beta se concede por obra, así que la unidad sobre la que se
> reserva y la unidad sobre la que se gasta **han dejado de coincidir**.
>
> Esta decisión no se invalida —la reserva previa sigue siendo la respuesta a `C-1`—, pero su
> **granularidad queda abierta**. La opción B2 («reserva por obra») gana peso, y aparece una
> tercera: reservar por obra y descontar por capítulo. Ver `R-1` en
> [`FEAT-CRD-009`](../features/credits/FEAT-CRD-009-reserve-credits-on-access-grant.md).

## La compensación

Es la consecuencia que más cuidado exige, así que se documenta aquí y no solo en la ficha.

```text
Reading: concede el acceso
    │
    ├──▶ BetaReaderAccessGranted
    │        │
    │        ▼
    │    Credits: intenta retener
    │        │
    │        ├── hay saldo ──▶ CreditsReserved ──▶ (nada que hacer)
    │        │
    │        └── no hay ────▶ CreditReservationRejected
    │                             │
    ▼                             ▼
  (acceso vigente)          Reading: revoca el acceso y avisa a ambos
```

Entre la concesión y el rechazo hay una ventana en la que el lector tiene acceso a una obra
que su autor no puede pagar. Para que sea rara, no para que sea imposible:

- `Reading` mantiene una **proyección del saldo disponible** de cada autor, alimentada por
  `CreditBalanceChanged`, y no concede accesos cuando sabe que no hay saldo;
- esa proyección es de consistencia eventual, así que **no es una garantía**: la compensación
  sigue siendo obligatoria para los casos de carrera.

Camino rápido por proyección, corrección por compensación. Lo contrario —confiar solo en la
proyección— sería exactamente la validación síncrona que la opción C hacía mal.

## Cumplimiento

- `Credits` es el único que calcula importes y decide si hay saldo. Ningún otro contexto
  consulta ni interpreta retenciones.
- Todo handler de retención es idempotente por `eventId`, como el resto de `Credits`.
- Test: conceder acceso a un autor sin saldo disponible acaba con el acceso revocado.
- Test: el saldo disponible siempre es igual a saldo menos retenciones vigentes.
- Test: confirmar una retención no altera el saldo total una segunda vez.
