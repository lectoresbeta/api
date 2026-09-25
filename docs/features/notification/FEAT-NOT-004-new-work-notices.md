---
id: FEAT-NOT-004
title: Avisar de nueva obra de un autor al que se sigue
context: Notification
concept: Delivery
actors: []
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/features/README.md
  - docs/bounded-contexts/notification.md
  - conversation:2026-09-25
endpoints: []
events: []
depends_on: [FEAT-NOT-001, FEAT-COM-010, FEAT-WRK-016]
updated: 2026-09-25
---

# FEAT-NOT-004 — Avisar de nueva obra de un autor al que se sigue

## Resumen

Cuando alguien publica una obra, quienes le siguen reciben un aviso.

**Es lo que hace que seguir signifique algo.** Hasta ahora seguir a un autor cambiaba lo que
salía en el muro y nada más: quien no entrara ese día se perdía la publicación y no se
enteraba nunca. El botón «Seguir» prometía algo que el sistema no cumplía.

Es el **único aviso que se reparte a muchos a la vez**, y eso es todo lo que tiene de
particular.

## Alcance: obras, no publicaciones

El título del registro decía «nueva obra **o publicación**». Se avisa solo de **obras**, y la
decisión tiene tres apoyos:

- la frase del aviso estaba escrita desde `FEAT-NOT-001` alrededor del título de una obra
  (`%s ha publicado %s`), no de una publicación del muro;
- el muro **ya reparte las publicaciones** de a quien se sigue (`FEAT-COM-001`, ámbito
  `MINE_AND_FOLLOWED`). Un aviso por publicación duplicaría el muro en la campana;
- publicar en el muro es un gesto de ritmo social —varios al día— y un aviso por cada uno
  enseña a ignorar la campana, que es la misma razón por la que `POST_REPLY` y
  `DIRECT_MESSAGE_RECEIVED` no salen por correo (`FEAT-NOT-002` `RN-1`).

Publicar una obra es lo contrario: pasa pocas veces y es exactamente lo que alguien esperaba
al pulsar «Seguir».

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| — (sistema) | Crear un aviso por seguidor | El seguimiento está vigente cuando llega el hecho |

## Precondiciones

El autor tiene seguidores y publica una obra que estaba en borrador.

## Reglas de negocio

- `RN-1` Un aviso `SUBSCRIBED_AUTHOR_PUBLISHED` **por seguidor**, con su propia fila. Cada
  uno tiene sus preferencias y su bandeja: silenciarlo es cosa de cada cual y no del autor.
- `RN-2` **Solo la primera publicación cuenta.** Pedir el estado que ya se tiene no anuncia
  nada (`FEAT-WRK-016`): la llamada responde bien, pero un hecho es algo que ha pasado y ahí
  no ha pasado nada. Sin esto, pulsar «Publicar» dos veces avisaría a todos dos veces de la
  misma obra.
- `RN-3` **El autor no se avisa a sí mismo** (`FEAT-NOT-001` `RN-3`).
- `RN-4` **Dejar de seguir lo apaga.** Es la mitad que se olvida y la que envejece hacia el
  lado peligroso: un aviso que no se puede apagar dejando de seguir es peor que no haberlo
  mandado nunca. Bloquear lo apaga también, porque el bloqueo deshace los dos seguimientos
  (`FEAT-COM-034` `RN-3`) y este contexto no necesita saber que hubo bloqueo.
- `RN-5` **Es silenciable** por la persona que lo recibe, en bandeja y en correo
  (`FEAT-USR-039`). No es operativo: es una recomendación, y renunciar a ella es legítimo.
- `RN-6` El aviso lleva el **título del momento en que se publicó**, no el de ahora. Describe
  algo que pasó: si la obra se retitula mañana, el aviso de ayer conserva el nombre de ayer.
- `RN-7` Reentregar el hecho no duplica ningún aviso. El índice único
  `(destinatario, tipo, evento)` lo garantiza aunque el reparto se corte a la mitad y se
  reintente entero.
- `RN-8` El reparto va **por páginas**. Un autor con muchos seguidores no cabe en memoria, y
  el día que no quepa no es el día de descubrirlo.

## Flujo principal

1. El autor pasa una obra de `DRAFT` a `PUBLISHED`.
2. `Work` publica `WorkPublished` con el identificador, el autor y el título.
3. `Notification` recorre por páginas su copia del grafo de seguidores de ese autor.
4. Por cada seguidor, `Notify` aplica sus preferencias y guarda su fila.

## La tercera copia del grafo de seguidores

`Community` posee el seguimiento. `User` ya guarda una copia para resolver audiencias
`FOLLOWERS`. Esta es la tercera, y una copia de más es un sitio de más donde envejecer, así
que conviene decir por qué no se resolvió con un contrato.

Porque **un reparto de avisos no tiene a nadie esperando al otro lado**. `AGENTS.md` pide
preferir el hecho asíncrono a la llamada síncrona cuando la hay, y
[`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md) dice que un
contrato es para cuando la respuesta hace falta **ahora**. Aquí no hace falta ahora: hace
falta cuando el hecho llegue.

Y porque el contrato que haría falta sería justo el que `SubscriptionCounts` se negó a ser —
la lista de seguidores de alguien— pedida por páginas y en mitad del consumo de un evento.
Las cifras se publican; la lista de quién está detrás tiene su endpoint, con sus reglas de
visibilidad (`FEAT-COM-027`).

La copia lleva **lo justo para repartir**: el par y la fecha. Nace vacía: los seguimientos
anteriores a la migración no están, y lo que se pierde es el aviso de la siguiente obra de
alguien a quien ya se seguía, no el seguimiento.

## Contrato de API

Ninguno propio. Los avisos se leen por el centro de notificaciones (`FEAT-NOT-009`).

## Eventos

**Publica** — ninguno.

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `WorkPublished` | `Work` | Un aviso por seguidor |
| `AuthorSubscribed` | `Community` | Apunta el par en la copia del grafo |
| `AuthorUnsubscribed` | `Community` | Lo borra |

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

`notification_ctx.author_follower`, con el par como clave primaria en el orden
`(author_id, follower_id)`: se pregunta siempre por autor, y ese orden sirve además para
continuar la página por el segundo componente sin un índice aparte.

## Límite conocido

El reparto hace **una consulta de preferencias y una inserción por destinatario**. Es
aceptable con el tamaño previsible de la plataforma y es lo que permite que cada persona
tenga sus propias preferencias, pero es el sitio donde mirar el día que un autor tenga
decenas de miles de seguidores. La solución entonces no es quitar las preferencias: es un
camino de inserción masiva que las resuelva en bloque.

## Criterios de aceptación

- [x] Publicar una obra avisa a quien sigue al autor y no al autor.
- [x] Quien no le sigue no recibe nada.
- [x] Dejar de seguir apaga los avisos siguientes.
- [x] Bloquear al autor los apaga también.
- [x] Publicar dos veces no avisa dos veces.
- [x] Reentregar el hecho no duplica el aviso.
- [x] Varios seguidores reciben cada uno su fila.
- [x] Quien lo ha silenciado no lo recibe.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
