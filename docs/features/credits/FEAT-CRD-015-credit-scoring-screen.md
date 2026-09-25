---
id: FEAT-CRD-015
title: Pantalla explicativa de cómo se calcula el precio
context: Credits
concept: Pricing
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/features/credits/FEAT-CRD-014-credits-info-modal.md
  - docs/decisions/0006-credit-system.md
  - conversation:2026-09-25
endpoints:
  - getCreditScoring
events: []
depends_on: [FEAT-CRD-014, FEAT-CRD-016, FEAT-CRD-006]
updated: 2026-09-25
---

# FEAT-CRD-015 — Cómo se gana y cómo se gasta

## Resumen

La pantalla a la que lleva el botón «Ver puntuación de créditos» del modal de
[`FEAT-CRD-014`](FEAT-CRD-014-credits-info-modal.md): qué te da créditos, qué te los quita, y
cómo sale el precio de corregir un capítulo.

## Por qué la sirve el backend

`FEAT-CRD-014` `RN-3` lo pedía y tenía razón: **las cifras no se escriben en el texto**.

Aquí no se copian tampoco. Llegan **por inyección, desde las mismas palancas de
`config/services.yaml` que usa el motor de precios**. Escribirlas a mano en este endpoint
habría sido más corto y habría garantizado que un día la pantalla y el cobro dijeran cosas
distintas — y quien lo descubre es alguien que esperaba cobrar otra cosa.

El ejemplo con cifras reales lo calcula **`ChapterPricing`**, la misma clase que cobra. Si la
fórmula cambia, el ejemplo cambia solo.

## La contradicción del modal, resuelta

`FEAT-CRD-014` registró que la maqueta decía que los créditos se gastan «al poner la obra en
corrección», mientras que [`decision:0006`](../../decisions/0006-credit-system.md) y
`FEAT-CRD-006` establecen que se pagan **por cada corrección entregada**.

**Manda el código, que es el que ya estaba construido**, y esta pantalla lo dice sin
ambigüedad: `chargedOn: FEEDBACK_DELIVERED`. No es una decisión nueva; es dejar de tener dos
versiones por ahí. `M-1` queda resuelta a favor de lo implementado.

## Reglas de negocio

- `RN-1` **Público y sin sesión.** No hay nada de nadie: son las reglas de la casa, y quien se
  está planteando registrarse tiene derecho a leerlas antes.
- `RN-2` Trae los grifos —bienvenida, recompensa por invitación y su tope— y las palancas del
  precio: el mínimo, el máximo, las palabras por crédito de lectura y de escritura, y el suelo
  por pregunta sin mínimo.
- `RN-3` Dice **cuándo se paga**: `FEEDBACK_DELIVERED`.
- `RN-4` **No hay una cifra de «lo que se gana corrigiendo»**, y su ausencia se declara con
  `correctionPaysWhatItCosts`. Una corrección mueve créditos en vez de crearlos: el autor paga
  exactamente lo que el corrector cobra. Decirlo así evita que alguien busque un número que no
  existe.
- `RN-5` El ejemplo lo calcula el motor real, no una copia de la fórmula.
- `RN-6` **No consulta el saldo de nadie.** Es la explicación de las reglas, no un estado, y
  por eso la respuesta es idéntica para todo el mundo y se puede cachear en público.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Puntuación de créditos | `GET /api/v1/credits/scoring` | `getCreditScoring` |

`Cache-Control: public, max-age=3600`. Las palancas se mueven con un despliegue, no con una
petición, así que servir una respuesta de hace un rato no engaña a nadie.

## Eventos

Ninguno. Es una lectura.

## Efectos en créditos

Ninguno. Explica la economía, no la toca.

## Modelo de datos afectado

Ninguno.

## Criterios de aceptación

- [x] Responde sin sesión.
- [x] Trae los grifos y las palancas del precio, con las cifras vigentes.
- [x] Dice que se paga al entregarse la corrección.
- [x] Declara que corregir paga exactamente lo que cuesta.
- [x] El ejemplo coincide con lo que calcula `ChapterPricing`.
- [x] Se puede cachear en público.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
