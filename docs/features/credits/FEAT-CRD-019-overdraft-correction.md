---
id: FEAT-CRD-019
title: Corrección en descubierto como gancho de reactivación
context: Credits
concept: Reservation
actors: []
spec_status: DRAFT
impl_status: TODO
priority: P2
sources:
  - conversation:2026-09-23 (rediseño del sistema de créditos)
  - docs/decisions/0006-credit-system.md
endpoints: []
events: [OverdraftCorrectionGranted, CorrectionUnlocked]
depends_on: [FEAT-CRD-009, FEAT-CRD-018]
updated: 2026-09-23
---

# FEAT-CRD-019 — Corrección en descubierto

## Resumen

A usuarios **seleccionados** que han dejado de participar se les permite recibir **una**
corrección sin tener saldo.

El corrector cobra con normalidad. El autor queda en negativo y **ve que la corrección existe,
pero no su contenido**, hasta que reponga saldo. Recibe un correo avisándole.

El mensaje se escribe solo, porque es la tesis de la plataforma en miniatura:

> **Para leer una corrección, haz una corrección.**

No es «paga para ver»: es reponer tu saldo haciendo por otro exactamente lo que alguien acaba
de hacer por ti. Que el precio de leerla sea aproximadamente el trabajo que costó escribirla
es lo más justo que este sistema puede ofrecer.

## Qué separa esto de un patrón oscuro

Cobrar a alguien por algo que no pidió y ocultárselo hasta que pague tiene mala pinta. Tres
condiciones lo cambian por completo, y **las tres son obligatorias**:

| Condición | Por qué |
|---|---|
| **El texto seguía abierto a corrección** | Esa corrección no es mercancía no solicitada: es el cumplimiento de una petición que el autor hizo y luego abandonó |
| **Se le avisó antes**, al dejar la obra abierta | Con el aviso es un trato aceptado; sin él, una sorpresa el día que vuelve |
| **Ha corregido antes** | Se extiende crédito a quien ha demostrado que sabe devolverlo |

Si el autor había cerrado sus textos, no hay nada que cobrar y no hay gancho. Esa es la línea.

## Reglas de negocio

- `RN-1` **Máximo una corrección en descubierto por autor.** Sale gratis de `RN-2` de
  [`FEAT-CRD-018`](FEAT-CRD-018-negative-balance.md): con saldo negativo no se reciben más.
- `RN-2` Solo se concede a autores que cumplen las tres condiciones de arriba, más: llevar
  **dormidos `N` días**, no tener ya una deuda viva y **poder recibir el correo**.
- `RN-3` El corrector **cobra íntegro** y no sabe que el autor estaba en descubierto. Para él
  no cambia nada.
- `RN-4` El autor ve **metadatos**, no contenido: quién, cuándo, sobre qué capítulo y cuánto
  se ha escrito.
- `RN-5` Al volver a saldo ≥ 0, la corrección **se desbloquea automáticamente**.
- `RN-6` La corrección bloqueada **no se borra nunca**. Es trabajo de otra persona.
- `RN-7` El mecanismo **se puede desactivar** globalmente sin afectar a nada más.
- `RN-8` Quien tiene notificaciones desactivadas **no se selecciona**: sin correo no hay
  gancho, solo deuda.

`RN-3` importa: el lector no debe enterarse de la situación económica del autor. Es
información que no le corresponde, y saberlo podría desanimarle a corregir.

`RN-8` evita el peor resultado posible: generar deuda a alguien que nunca va a recibir el
aviso que la justificaba.

## Por qué solo una

Tres motivos que apuntan al mismo sitio:

| | |
|---|---|
| **Económico** | La emisión máxima es *usuarios dormidos × precio*, no ilimitada |
| **De producto** | El gancho ya funciona con una. Una segunda no añade curiosidad, solo deuda |
| **Humano** | Cada corrección bloqueada es una hora de trabajo de un lector que quizá nadie lea. Una es aceptable; cinco es malgastar a tus mejores usuarios |

## Qué se muestra sin mostrar el contenido

Un «tienes una corrección» a secas motiva poco. Los metadatos motivan mucho y no regalan nada:

```text
Marta ha corregido «El último tren» · capítulo 2 · hace 3 días · 480 palabras
                    [ Bloqueada — repón tu saldo para leerla ]
```

**Nada de mostrar un fragmento difuminado.** O regalas parte del valor, o pareces un muro de
pago de periódico.

## El coste económico, dicho claramente

**Una deuda que no se recupera es crédito emitido sin respaldo.** El coste de este mecanismo
es proporcional a su **tasa de fracaso**: si reactiva al 30%, el 70% restante es emisión pura.

No lo invalida —es un coste de captación, como cualquier otro— pero obliga a medirlo. Es una
de las métricas de [`FEAT-CRD-012`](FEAT-CRD-012-economy-health.md), y es la que decide si
esto sigue encendido:

> Si de cada diez deudas vuelven tres, sale a cuenta. Si vuelve una, se está regalando crédito
> para nada y conviene apagarlo o restringir más a quién se le ofrece.

## Eventos

**Publica**

| Evento | Cuándo | Consumidores |
|---|---|---|
| `OverdraftCorrectionGranted` | Se concede la retención en descubierto | `Feedback` (marca la corrección como bloqueada), `Notification` (el correo gancho) |
| `CorrectionUnlocked` | El autor vuelve a saldo ≥ 0 | `Feedback`, `Notification` |

**Quién bloquea el contenido es `Feedback`, no `Credits`.** `Credits` publica el hecho
económico; `Feedback`, que es quien posee la corrección, decide qué enseña. Si `Credits`
controlara la visibilidad del texto, estaría gobernando el modelo de otro contexto.

## Criterios de aceptación

- [ ] Solo se concede a autores que cumplen las tres condiciones.
- [ ] Nunca hay dos correcciones en descubierto del mismo autor.
- [ ] El corrector cobra íntegro y no ve nada distinto.
- [ ] El autor ve metadatos y no el contenido.
- [ ] Al reponer saldo, la corrección se desbloquea sin intervención.
- [ ] Una corrección bloqueada no se borra aunque pasen meses.
- [ ] No se selecciona a quien tiene las notificaciones desactivadas.
- [ ] El mecanismo se desactiva globalmente por configuración.
- [ ] La tasa de recuperación es consultable.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **C-27** | ¿Cuántos días de inactividad? | Demasiado pronto molesta; demasiado tarde no reactiva |
| C-28 | ¿Se avisa al autor **en el momento** de dejar la obra abierta, o basta con las condiciones generales? | Decide si es un trato aceptado |
| C-29 | ¿Puede el autor renunciar a este mecanismo desde Configuración? | Debería |
| C-30 | ¿Qué pasa si el autor nunca vuelve? ¿Se desbloquea por bondad pasado un año? | Hoy no; conviene decidirlo |
| C-31 | ¿Se le enseña al autor el saldo que le falta y cuántas correcciones son? | Un objetivo concreto motiva más que «repón saldo» |

`C-30` tiene doble filo: desbloquear por bondad al año regala lo que no se pagó y enseña que
esperar funciona; no hacerlo deja una corrección escrita que nadie leerá jamás. Me inclino por
no desbloquear, y por dejar de escribir correcciones nuevas a quien no vuelve.

## Estado

**Especificación:** `DRAFT`. `C-27` y `C-28` deben cerrarse antes de `APPROVED`.

**Implementación:** `TODO`. Es lo último que conviene construir: no aporta nada hasta que haya
usuarios dormidos que reactivar.
