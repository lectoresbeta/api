---
id: FEAT-MOD-011
title: Revisión automática de contenido
context: Moderation
concept: ContentReview
actors: []
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - conversation:2026-09-23 (servicio aislado de revisión automática)
endpoints: []
events: [ContentReviewPassed, ContentReviewFlagged]
depends_on: []
updated: 2026-09-25
---

# FEAT-MOD-011 — Revisión automática de contenido

## Resumen

Todo texto pasa por un **servicio de revisión automática** antes de quedar visible para otros.

**Hoy ese servicio aprueba todo.** Se implementa desde el principio con una implementación
vacía, para que más adelante pueda sustituirse por una basada en IA **sin tocar nada más**.

## Por qué se construye ahora y vacío

Es la respuesta a `MOD-3`: sin revisión previa, un contenido gravemente dañino permanece
visible hasta que alguien lo denuncie y un moderador lo mire.

Construirlo ahora, aunque no haga nada, tiene un valor concreto:

| Si se construye después | Si se construye ahora vacío |
|---|---|
| Hay que abrir el flujo de publicación, que es delicado | El flujo ya contempla el paso |
| Hay que inventar estados nuevos con obras ya publicadas | Los estados existen desde el día uno |
| Los tests del camino completo no existen | Existen y pasan |
| Cambiar de opinión sobre el punto de corte es caro | Es cambiar una implementación |

**El coste hoy es casi cero y el ahorro después es grande.** Es el caso de libro para un
puerto con adaptador nulo.

## El contrato

```text
Application/Port/
    ContentReviewer.php      ← lo que la aplicación necesita

Infrastructure/ContentReview/
    AlwaysApprovesContentReviewer.php   ← hoy
    AiContentReviewer.php               ← mañana
```

El puerto recibe el texto y devuelve un veredicto: **aprobado** o **marcado**, con motivo.
Nada más. Ni puntuaciones, ni categorías, ni probabilidades: cuanto más rico sea el contrato,
más difícil será sustituir la implementación.

- `RN-1` El revisor vive **tras un puerto** definido por la aplicación. Ningún contexto conoce
  la implementación concreta.
- `RN-2` La implementación actual **aprueba siempre**, sin condiciones.
- `RN-3` El veredicto queda **registrado** con la versión del revisor que lo emitió. Cuando
  llegue la IA, hará falta saber qué revisó qué.
- `RN-4` Un texto **marcado no se publica**: genera una reclamación automática en la cola de
  moderación, con reclamante `SYSTEM`.
- `RN-5` El revisor **nunca decide solo una sanción**. Marca; decide un humano.
- `RN-6` Si el revisor **falla o no responde**, el texto se publica y se marca para revisión
  posterior. No se bloquea a nadie por una caída de infraestructura.
- `RN-7` El servicio es **desactivable** por configuración.
- `RN-8` Se ejecuta **al publicar y en cada modificación del texto** (`MOD-36`). No basta con
  revisar la publicación inicial.
- `RN-9` Se revisa **solo lo que cambia** cuando es posible. Reprocesar una novela entera por
  una errata corregida será caro en cuanto el revisor haga algo de verdad.

`RN-5` es la regla que conviene no relajar nunca. Un sistema automático que sanciona sin
intervención humana se equivoca en silencio y a escala, y el afectado no tiene con quién
hablar.

`RN-6` es la decisión contraria a la intuitiva —lo seguro parecería ser no publicar— y es la
correcta: hoy el revisor aprueba todo, así que bloquear ante un fallo sería impedir publicar
por nada. Cuando exista IA de verdad, conviene revisarla.

## Cuándo se ejecuta: en cada modificación

**Al publicar y en cada edición posterior** (`MOD-36`), antes de que el texto modificado sea
visible para otros.

Revisar solo al publicar dejaría abierto el esquive obvio —publicar un texto inocuo y
editarlo después— que vaciaría de sentido cualquier revisión futura. Con la implementación
actual el veredicto es inmediato y el autor no nota nada.

Cuando la revisión tarde —la IA tardará— aparece una pregunta que hoy no es urgente pero
conviene dejar planteada: **¿espera el autor, o se publica de forma optimista y se retira si
el veredicto es negativo?** (`MOD-34`).

Recomiendo un estado transitorio `UNDER_REVIEW` en `Work` desde ya, aunque hoy dure
milisegundos. Añadirlo después, con obras publicadas, es mucho más caro.

## Eventos

| Evento | Cuándo | Consumidores |
|---|---|---|
| `ContentReviewPassed` | El texto pasa | **`Work`** (lo hace visible) |
| `ContentReviewFlagged` | El revisor lo marca | **`Work`** (no lo publica), `Moderation` (abre reclamación), `Notification` |

## Criterios de aceptación

- [x] Existe un puerto `ContentReviewer` en la capa de aplicación.
- [x] La implementación actual aprueba todo y no consulta nada externo.
- [x] Sustituirla no requiere tocar `Work` ni el flujo de publicación. **La prueba lo hace
  literalmente**: sustituye el revisor por uno que marca y no toca nada más.
- [x] Todo veredicto queda registrado con la versión del revisor.
- [x] Un texto marcado no se publica y genera reclamación automática.
- [x] Ningún veredicto automático impone una sanción por sí solo.
- [x] Si el revisor falla, el texto se publica igualmente.
- [x] El servicio se desactiva por configuración.
- [x] El texto se revisa también **al editarlo**, no solo al publicarlo.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| MOD-34 | Cuando la revisión tarde, ¿espera el autor o se publica de forma optimista? | Hoy no importa; con IA sí |
| MOD-35 | ¿Se revisan también las **correcciones**, o solo los textos? | Una corrección ofensiva es igual de dañina |
| **MOD-37** | Si se usa IA externa, ¿sale obra inédita de la plataforma? | **Anotado y aceptado como decisión a tomar** cuando llegue la implementación real. Hoy el revisor no consulta nada externo |
| MOD-46 | ¿Se revisa cada pulsación de «guardar», o hay agrupación? | Un editor que guarda solo dispararía una revisión por minuto |

Resuelta: `MOD-36` (**se revisa en cada modificación**, no solo al publicar).

`MOD-46` nace de cerrar `MOD-36`: revisar en cada cambio es correcto, pero «cada cambio»
necesita definición o el coste se dispara en cuanto el revisor haga algo real.

`MOD-37` no es un detalle técnico. Mandar manuscritos inéditos a un servicio externo es una
decisión de producto y probablemente de contrato, del mismo orden que la de
[`FEAT-FBK-012`](../feedback/FEAT-FBK-012-correction-fraud-control.md) `RN-4`.

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `DONE` (2026-09-25).

Tres cosas que la ficha no preveía y que la implementación obligó a decidir:

- **no se añade el estado `UNDER_REVIEW` a `Work`**, que la ficha recomendaba «desde ya, aunque
  hoy dure milisegundos». Un texto marcado se retira por el **mismo bloqueo de moderación** que
  una reclamación estimada: ya existe, ya está probado, y ya tiene la salida que hace falta
  —solo un moderador lo levanta—, que es la garantía de que *lo que esconde una máquina lo
  devuelve una persona*. Inventar una transición más en una máquina de estados que hoy funciona
  y que varios contextos escuchan habría hecho lo mismo con más riesgo. Cuando `MOD-34` decida
  si el autor espera al veredicto, ese será el momento, con datos y no por precaución;
- **el disparador es `ChapterContentUpdated`**, el hecho que `Work` ya publica al crear un
  capítulo y en cada edición de su texto, y nunca por otra cosa. Es exactamente lo que piden
  `RN-8` y `RN-9` juntas, y no hizo falta ningún hecho nuevo;
- **lo que hace idempotente la revisión es el cifrado del texto**, no el identificador del
  hecho ni la versión del capítulo. La versión no vale: un capítulo puede editarse sin cambiar
  de versión, porque el versionado solo se abre cuando ya lo ha corregido alguien. El cifrado
  no se equivoca, y de paso cubre dos hechos distintos sobre el mismo texto y una edición que
  no cambia nada. Sin esta clave, una reentrega de RabbitMQ abriría una segunda reclamación
  automática sobre el mismo capítulo.

**Falta** lo que las preguntas abiertas ya anotaban: revisar también las **correcciones**
(`MOD-35`), agrupar los guardados de un editor para no disparar una revisión por minuto
(`MOD-46`), y las dos que solo importan cuando el revisor lea de verdad — si el autor espera
(`MOD-34`) y si obra inédita puede salir de la plataforma (`MOD-37`).
