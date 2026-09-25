---
id: FEAT-FBK-006
title: Valorar una corrección recibida
context: Feedback
concept: Rating
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - conversation:2026-09-25 (bloque «que el autor pueda leer lo que compró»)
endpoints:
  - PUT /corrections/{correctionId}/rating
events: [FeedbackRatedPositively]
depends_on: [FEAT-FBK-004]
updated: 2026-09-25
---

# FEAT-FBK-006 — Valorar una corrección recibida

## Resumen

El autor marca una corrección como **útil** o **no útil**. Una valoración por corrección, y la
puede cambiar.

Resuelve `F-5`: **es binaria**, no graduada.

## Por qué binaria

Una escala fina invita a puntuar a la baja por desacuerdo literario, y eso es exactamente lo
que no queremos medir. La pregunta que esta valoración hace es «¿te ha servido?», no «¿cuánto
te ha gustado lo que dice?».

El modelo ya lo asumía: `Correction` guarda `helpful` como un booleano que puede estar sin
responder, lo que distingue tres situaciones que importan —útil, no útil y **aún no
valorada**— sin inventar una escala.

## Lo que esta valoración ya no hace

**No paga.** Hubo un diseño en el que valorar positivamente abonaba cinco créditos
automáticamente (`FEAT-CRD-004`, retirada). [`decision:0006`](../../decisions/0006-credit-system.md)
lo sustituyó por **la propina** ([`FEAT-CRD-017`](../credits/FEAT-CRD-017-author-tip.md)),
que el autor decide y paga de su saldo.

La diferencia no es de importe: un abono automático convierte el pulgar en un botón de
dinero, y a partir de ahí la valoración deja de significar «me ha servido».

## Reglas de negocio

- `RN-1` Solo **el autor de la obra** valora, y solo lo que ha recibido.
- `RN-2` La valoración es **binaria**: útil o no útil. No valorar es un tercer estado legítimo
  y es el inicial.
- `RN-3` **Se puede cambiar**, las veces que haga falta. Una valoración que no se puede
  rectificar se rellena con más miedo del que merece.
- `RN-4` No se puede valorar una corrección **`LOCKED`**: no se ha leído.
- `RN-5` Una corrección llegada por **enlace público** se puede valorar, y no avisa a nadie:
  no hay cuenta detrás. Sirve igual para el autor, que así ordena lo que ha recibido.
- `RN-6` Valorar **no mueve créditos**. La propina es otra decisión y otra operación.
- `RN-7` Solo la **primera valoración positiva** publica el hecho. Cambiarla después no
  publica nada: avisar a alguien de que su corrección ha dejado de ser útil es una crueldad
  sin función.
- `RN-8` La valoración la ve **quien escribió la corrección**, en «Mis correcciones»
  ([`FEAT-FBK-010`](FEAT-FBK-010-my-corrections.md)). Una valoración que nadie ve no corrige el
  comportamiento de nadie.
- `RN-9` Valorar **no es requisito** para nada, y nada obliga a valorar.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| No es el autor de la obra | Se rechaza sin revelar nada | `404` |
| La corrección está `LOCKED` | Se rechaza | `409` `CORRECTION_LOCKED` |
| Valor distinto de útil / no útil | Se rechaza | `422` |
| La misma valoración otra vez | Se acepta y no ocurre nada | `204` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Valorar o cambiar la valoración | `PUT /corrections/{correctionId}/rating` | `rateCorrection` |

`PUT` con `helpful: true | false`. Retirar la valoración por completo —volver a «sin
valorar»— **no está previsto**: una vez dicho, dicho (`F-19`).

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `FeedbackRatedPositively` | La primera vez que se marca como útil | `correctionId`, `chapterId`, `workId`, `readerId`, `ratedAt` |

Consumidores: `Notification`, que avisa a quien corrigió, y `Community`, que lo cuenta para la
reputación. **`Credits` no lo consume**: aquí no hay dinero.

No existe evento para la valoración negativa. No hay a quién avisar ni qué contar con ella:
sirve al autor para ordenar lo suyo, y es una señal que la moderación puede mirar si alguien
reclama.

## Modelo de datos afectado

`feedback_ctx.correction` ya tiene `helpful` y `rated_at`. Solo falta el caso de uso.

## Criterios de aceptación

- [x] El autor marca una corrección como útil y quien la escribió lo ve.
- [x] La marca como no útil y quien la escribió también lo ve.
- [x] Cambia la valoración y la última es la que vale.
- [x] Solo la primera valoración positiva genera aviso.
- [x] Cambiar de útil a no útil no genera ningún aviso.
- [x] Una corrección bloqueada por descubierto no se puede valorar.
- [x] Valorar no mueve ningún crédito.
- [x] Un tercero no puede valorar.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| F-19 | ¿Se puede retirar la valoración y volver a «sin valorar»? | Hoy no. Con tres estados en el modelo, es barato permitirlo |
| F-20 | ¿Cuenta la proporción de «no útil» para el control antifraude (`FEAT-FBK-012`)? | Es la señal más barata que existe, y también la más fácil de usar como castigo |
| F-21 | ¿Se le muestra a un lector beta la valoración media de sus correcciones antes de aceptar un encargo? | Reputación pública, con todo lo que eso arrastra |

## Estado

**Especificación:** `APPROVED` (2026-09-25). Resuelve `F-5` (binaria).

**Implementación:** `DONE` (2026-09-25).

`F-5` queda resuelta en el código además de en la ficha: binaria, con «sin valorar» como
tercer estado y estado inicial.

Lo que costó decidir bien fue **cuándo se anuncia**: solo la primera valoración positiva.
Cambiarla después no publica nada, así que quien corrigió no recibe nunca un «tu corrección ha
dejado de ser útil». La regla vive en quien publica el hecho, no en quien lo consume, que es lo
que hace que no dependa de que nadie se acuerde.

`F-19`, `F-20` y `F-21` siguen abiertas.
