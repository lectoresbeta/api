---
id: FEAT-FBK-002
title: Valorar una obra
context: Feedback
concept: Rating
actors: [BetaReader]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - docs/bounded-contexts/feedback.md
  - docs/events/README.md
  - conversation:2026-09-25
endpoints:
  - rateWork
events:
  - WorkRated
depends_on: [FEAT-FBK-003]
updated: 2026-09-25
---

# FEAT-FBK-002 — Valorar una obra

## Resumen

Una nota de 1 a 5 sobre una obra, puesta por quien la ha corregido.

Es la pieza que faltaba para que exista una noción de calidad en la plataforma: el catálogo
ordena por otras cosas a propósito ([`decision:0008`](../../decisions/0008-catalogue-ordering.md)),
pero los rankings necesitan un número y hasta ahora nadie lo producía. `WorkRated` llevaba
documentado en el catálogo de eventos sin que ningún contexto lo emitiera.

## Quién puede valorar, y por qué importa

**Solo quien ha entregado al menos una corrección de esa obra.**

Es la decisión que da sentido al número. Una media abierta a cualquiera que pueda abrir la
obra mide cuánta gente pasó por allí; una media de quienes entregaron una corrección dice lo
que opina quien de verdad la leyó y se molestó en escribir sobre ella.

Tiene además dos ventajas que no son de diseño sino de realidad:

- **es la única señal de lectura que la plataforma tiene.** No hay seguimiento de lectura
  —`H-3` lleva abierta desde el principio y nadie ha definido qué cuenta como «lectura»—, así
  que la corrección entregada es lo único que acredita que alguien leyó;
- **resiste el fraude que `FEAT-FBK-012` ya teme.** Inflar la nota de una obra exigiría
  entregar correcciones, que cuesta trabajo, deja rastro y pasa por el control antifraude.

Un **borrador no cuenta**: lo que acredita haber leído es el trabajo entregado, no el
empezado.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `BetaReader` | Valorar de 1 a 5 | Ha entregado una corrección de esa obra |
| `BetaReader` | Cambiar su nota | La misma condición; sustituye la anterior |
| `Writer` | — | **No valora su propia obra** |

## Reglas de negocio

- `RN-1` Valora quien ha **entregado** una corrección de esa obra. Un borrador no basta.
- `RN-2` **Una valoración por lector y obra.** Volver a valorar sustituye la anterior, nunca
  acumula: cambiar de opinión tras leer más capítulos es lo que se espera, y acumular notas
  del mismo lector le daría más voz que a los demás. Lo garantiza el índice único
  `(work_id, reader_id)`.
- `RN-3` **El autor no valora su propia obra.**
- `RN-4` La escala es **1 a 5**, enteros. Cero no existe: una escala de estrellas empieza en
  una. Esto resuelve `F-3`, que preguntaba entre 1–5, 1–10 y positiva/negativa; el catálogo de
  eventos ya la fijaba en 1–5 y esta ficha lo confirma.
- `RN-5` Una obra que quien pregunta **no puede ver** responde `404`, el mismo que una que no
  existe. Un borrador ajeno no se distingue de nada.
- `RN-6` No valorar tras haber corregido **se explica**, a diferencia del resto de negativas
  de esta plataforma. Y se puede: para llegar ahí hay que ver la obra, así que no se revela
  nada, y «corrige un capítulo y podrás valorarla» es una instrucción accionable, no un muro.
- `RN-7` Valorar **no mueve créditos** en ninguna dirección. Lo que el sistema paga es el
  cuestionario respondido.

## Flujo principal

1. Alguien entrega una corrección de un capítulo de la obra (`FEAT-FBK-003`).
2. Desde la obra, pone su nota con `PUT /works/{workId}/rating`.
3. `Feedback` comprueba que la obra se ve, que no es suya y que hay una corrección entregada.
4. Guarda la nota —creándola o sustituyéndola— y publica `WorkRated`.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| La obra no se ve o no existe | No se distinguen | `404 WORK_NOT_FOUND` |
| No ha corregido esa obra | Se explica | `403 CORRECTION_REQUIRED_TO_RATE` |
| Es su propia obra | Se explica | `403 CANNOT_RATE_YOUR_OWN_WORK` |
| Nota fuera de 1–5 | Se explica | `422 VALIDATION_FAILED` |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Valorar o cambiar la nota | `PUT /api/v1/works/{workId}/rating` | `rateWork` |

`PUT` y no `POST` porque fija un estado. El endpoint documentado en
`docs/api/endpoints/feedback.md` decía `POST`; se corrige aquí por la misma razón por la que
los «me gusta» son `PUT`: repetirlo no suma.

**No es `FEAT-FBK-006`.** Aquella es la valoración de **una corrección** por el autor que la
recibe; esta es la valoración de **la obra** por quien la corrigió. Van en direcciones
contrarias y no comparten nada salvo la palabra.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|
| `WorkRated` | Al poner o cambiar una nota | `workId`, `authorId`, `readerId`, `rating` (1–5), `firstTime` |

`rating` es una cifra dentro de un hecho, que es poco habitual en este sistema; se justifica
como en `ChapterPriceChanged`: **el valor es el hecho**, y quien lo consuma tendría que
preguntarlo de vuelta si no viajara aquí.

`firstTime` distingue poner de cambiar. Quien promedie necesita las dos cosas: sin ello,
sustituir una nota se contaría como una nota más.

**Hoy no lo consume nadie.** Los tres rankings siguen bloqueados por `CM-4` —la fórmula de
puntuación no está definida— y publicar el hecho igualmente es lo que permite que, el día que
se defina, haya histórico que promediar.

**Consume** — ninguno.

## Efectos en créditos

Ninguno. Valorar no es corregir.

## Modelo de datos afectado

`feedback_ctx.work_rating`, que ya existía con su índice único `(work_id, reader_id)` y su
entidad. Esta ficha añade el repositorio, el caso de uso y el endpoint; no hace falta
migración.

## Criterios de aceptación

- [x] Quien entregó una corrección puede valorar, y el hecho sale con la nota.
- [x] Quien no ha corregido la obra no puede, y se le dice por qué.
- [x] Un borrador empezado no basta.
- [x] Volver a valorar sustituye, y el hecho lo dice con `firstTime: false`.
- [x] El autor no valora su propia obra.
- [x] Fuera de 1 a 5 no hay nota, tampoco el cero.
- [x] Una obra que no se puede ver no se distingue de una que no existe.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| `CM-4` | La fórmula de puntuación de los rankings | Sin ella nadie consume `WorkRated` todavía |
| `W-12` | ¿«Más valorados» usa esta nota o los «me gusta»? | La tarjeta del catálogo muestra un corazón, no estrellas |

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
