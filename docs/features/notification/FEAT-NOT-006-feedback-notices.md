---
id: FEAT-NOT-006
title: Avisar de feedback recibido, contestado o valorado
context: Notification
concept: Delivery
actors: []
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - docs/features/README.md
  - docs/bounded-contexts/notification.md
  - conversation:2026-09-25
endpoints: []
events: []
depends_on: [FEAT-NOT-001, FEAT-FBK-003, FEAT-FBK-005, FEAT-FBK-006]
updated: 2026-09-25
---

# FEAT-NOT-006 — Avisar de feedback recibido, contestado o valorado

## Resumen

Los tres avisos del ciclo de una corrección: **alguien ha corregido tu obra**, **el autor te
ha contestado** y **el autor ha valorado tu trabajo**. Los dos últimos van al corrector, y
son lo único que la plataforma le devuelve a cambio de haber escrito.

Sin ellos, corregir es escribir en un buzón: se entrega, se cobra, y no se sabe si sirvió de
algo. El que va al autor es el otro extremo del mismo problema — abrir una obra a corrección
y no enterarse de que ha llegado una.

Esta ficha **no introduce mecanismo nuevo**. Los tres avisos los entrega `Notify`
(`FEAT-NOT-001`) a partir de hechos que `Feedback` ya publica. Lo que hacía falta era
escribirlos: el registro los daba por pendientes cuando dos de los tres consumidores llevaban
funcionando desde `FEAT-NOT-001`, y uno de ellos sin una sola prueba que lo cubriera.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| — (sistema) | Crear el aviso al recibir el hecho | El destinatario existe y no lo ha silenciado |
| Autor | Recibir `CORRECTION_RECEIVED` | Es el dueño de la corrección |
| Corrector | Recibir `CORRECTION_REPLIED` y `CORRECTION_RATED` | Escribió la corrección |

## Precondiciones

Existe una corrección entregada (`FEAT-FBK-003`). Los avisos al corrector exigen además que
el autor la haya contestado (`FEAT-FBK-005`) o valorado (`FEAT-FBK-006`).

## Reglas de negocio

- `RN-1` Tres hechos, tres avisos:

  | Hecho | Aviso | Va a |
  |---|---|---|
  | `FeedbackSubmitted` | `CORRECTION_RECEIVED` | El autor |
  | `FeedbackReplied` | `CORRECTION_REPLIED` | El corrector |
  | `FeedbackRatedPositively` | `CORRECTION_RATED` | El corrector |

- `RN-2` **Ninguno lleva una línea del texto** (`FEAT-NOT-001` `RN-4`). Ni la corrección, ni
  la respuesta del autor, ni el capítulo. El aviso dice que hay algo que leer y enlaza a
  dónde leerlo, que es donde se comprueba quién puede.
- `RN-3` **Solo llegan las primeras veces.** Sustituir una respuesta no vuelve a anunciar, y
  cambiar una valoración a «no útil» no anuncia nada. Las dos decisiones son de quien publica
  los hechos (`FEAT-FBK-005` `RN-6`, `FEAT-FBK-006` `RN-4`), así que aquí no hay ninguna
  condición que mantener.
- `RN-4` **Avisar de que una corrección ha dejado de ser útil es una crueldad sin función.**
  Por eso no existe un `FeedbackRatedNegatively` al que suscribirse: el hecho que viaja es la
  valoración positiva, y solo la primera.
- `RN-5` `CORRECTION_RECEIVED` y `CORRECTION_REPLIED` **también salen por correo**;
  `CORRECTION_RATED` no (`FEAT-NOT-002` `RN-1`). La valoración es ritmo social: llega seguida
  y un correo por cada una enseña a ignorar el remitente.
- `RN-6` **El aviso al autor se retira solo** cuando abre la corrección (`FEAT-FBK-004`
  `RN-7`). Un centro de notificaciones que sigue marcando como nuevo algo ya leído deja de
  significar nada, y entonces se deja de mirar.
- `RN-7` Nadie se avisa a sí mismo. Un autor que corrige por enlace público su propia obra, o
  cualquier otro camino en el que el causante y el destinatario coinciden, no genera aviso
  (`FEAT-NOT-001` `RN-3`).
- `RN-8` Reentregar el mismo hecho no crea un segundo aviso (`FEAT-NOT-001` `RN-2`).

## Flujo principal

1. `Feedback` publica el hecho al confirmar la operación.
2. RabbitMQ lo entrega a `Notification`, que lo modela con su propia clase.
3. El consumidor traduce el hecho a un tipo de aviso y pide a `Notify` que lo entregue.
4. `Notify` comprueba silenciamiento, resuelve el nombre de quien lo causó y el título de la
   obra, y guarda la fila.
5. Si el tipo llega al buzón de correo y su dueño lo quiere, sale además el correo.

## Flujos alternativos y errores

| Caso | Comportamiento |
|---|---|
| El destinatario ha silenciado ese tipo | La fila se crea fuera de la bandeja, para poder anotar el correo si sí lo quiere |
| El destinatario ya no existe | No se crea nada, y no se reintenta: reintentar no lo va a resucitar |
| El hecho llega dos veces | El índice único `(destinatario, tipo, evento)` lo impide |
| El autor abre la corrección | El aviso `CORRECTION_RECEIVED` se marca como leído |

## Contrato de API

Ninguno propio. Los avisos se leen por el centro de notificaciones (`FEAT-NOT-009`).

## Eventos

**Publica** — ninguno.

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `FeedbackSubmitted` | `Feedback` | `CORRECTION_RECEIVED` al autor |
| `FeedbackReplied` | `Feedback` | `CORRECTION_REPLIED` al corrector |
| `FeedbackRatedPositively` | `Feedback` | `CORRECTION_RATED` al corrector |
| `CorrectionRead` | `Feedback` | Retira el aviso del autor |

## Efectos en créditos

Ninguno. Avisar de que algo se pagó no es pagarlo.

## Modelo de datos afectado

Ninguno nuevo. Filas en `notification_ctx.notification`.

## Criterios de aceptación

- [x] Entregar una corrección avisa al autor y no al corrector.
- [x] Contestar una corrección avisa al corrector y no al autor.
- [x] Valorar positivamente una corrección avisa al corrector.
- [x] Ninguno de los tres avisos contiene texto de la corrección ni de la respuesta.
- [x] Sustituir la respuesta no crea un segundo aviso.
- [x] Abrir la corrección retira el aviso del autor.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`. Los consumidores existen desde `FEAT-NOT-001`;
`FEAT-NOT-006` añade las pruebas de regresión de los dos que no tenían ninguna y corrige el
nombre de un campo de `CorrectionRead` que decía `readerId` llevando dentro el del autor.
