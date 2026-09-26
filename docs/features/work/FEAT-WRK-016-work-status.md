---
id: FEAT-WRK-016
title: Estado de una obra — borrador, visible y en corrección
context: Work
concept: Manuscript
actors: [Writer]
spec_status: APPROVED
impl_status: PARTIAL
priority: P0
sources:
  - conversation:2026-09-22 (pestaña «Mis relatos»)
  - figma:1800-14718 (modal de créditos, «poner tus obras en corrección»)
  - docs/ui/my-works.md
endpoints: [PUT /works/{workId}/status]
events: [WorkPublished, WorkOpenedForCorrection, WorkClosedForCorrection]
depends_on: [FEAT-WRK-001]
updated: 2026-09-24
---

# FEAT-WRK-016 — Estado de una obra

## Resumen

Una obra está en uno de tres estados, y solo en uno:

| Estado | `WorkStatus` | ¿Se puede leer? | ¿Se puede corregir? |
|---|---|---|---|
| En borrador | `DRAFT` | Solo el autor | No |
| Publicada | `PUBLISHED` | Sí | **No** |
| En corrección | `IN_CORRECTION` | Sí | **Sí** |
| Bloqueada | `blocked_at` | **Solo el autor**, marcada | No |

`BLOCKED` no lo elige el autor: lo impone una reclamación estimada
([`FEAT-MOD-003`](../moderation/FEAT-MOD-003-block-work.md)) y es **terminal**.

El estado responde a **dos preguntas distintas** que hasta ahora estaban mezcladas: si la
obra se puede leer y si se puede comentar. Una obra publicada puede estar cerrada a la
crítica.

## Por qué «visible» y «en corrección» son estados distintos

Recibir feedback **cuesta créditos** al autor
([`decision:0004`](../../decisions/0004-credit-reservation-on-access-grant.md)). Si publicar
implicara automáticamente aceptar comentarios, el autor perdería el control sobre cuándo
empieza a gastar.

Separarlos le permite publicar algo y abrirlo a corrección cuando le convenga —o cuando
tenga saldo—. Es lo que explica la frase del modal de créditos: *«poner tus obras en
corrección»*.

## Qué pasa con los atributos que ya había

**`W-9` resuelta: `WorkStatus` NO sustituye a `Visibility`.** Los dos conviven, y responden a
preguntas distintas:

| Eje | Valores | Responde |
|---|---|---|
| `WorkStatus` | `DRAFT`, `PUBLISHED`, `IN_CORRECTION`, `BLOCKED` | **En qué punto de su vida** está la obra |
| `Visibility` | `VISIBLE`, `HIDDEN` | **Si se muestra** ahora mismo a los demás |
| `BetaReaderAccessMode` | `PUBLIC`, `ON_REQUEST`, `PRIVATE` | **Quién puede corregirla** |

Que sean tres ejes y no uno permite algo que un estado único no sabe expresar: **retirar
temporalmente una obra publicada sin devolverla a borrador**. Un autor que quiere revisar su
texto durante una semana lo oculta; no lo despublica, ni pierde su historial, ni sus
correcciones.

| `WorkStatus` | `Visibility` | Qué significa |
|---|---|---|
| `DRAFT` | — | Solo el autor. La visibilidad **no aplica** |
| `PUBLISHED` | `VISIBLE` | En el catálogo, no corregible |
| `PUBLISHED` | `HIDDEN` | Retirada temporalmente por el autor |
| `IN_CORRECTION` | `VISIBLE` | En el catálogo y corregible |
| `IN_CORRECTION` | `HIDDEN` | **Solo quien ya tiene acceso** puede seguir corrigiendo |
| `BLOCKED` | — | Solo el autor, marcada. La visibilidad **no aplica** |

Las dos filas con «no aplica» importan: en `DRAFT` y `BLOCKED` la visibilidad no es una
elección del autor, así que no se guarda como tal. Guardar un `VISIBLE` que no significa nada
es el camino más corto a un bug de exposición.

La combinación `IN_CORRECTION` + `HIDDEN` es la más útil de todas: permite **cerrar la puerta
a nuevos correctores sin cortar a los que ya están trabajando**, que es lo que un autor quiere
cuando ya tiene suficiente feedback en marcha.

> **Y ahora hay un tercer eje** (`S-14`). La pantalla de Configuración añade un ajuste
> **global del usuario**: «¿Quién puede comentar mis textos?»
> ([`FEAT-USR-038`](../user/FEAT-USR-038-privacy-settings.md)). Responde casi a la misma
> pregunta que `BetaReaderAccessMode`, pero para todo lo que escribe el autor.
>
> **Decidido (`S-14`): el ajuste global es un techo.** Una obra puede ser más restrictiva que
> el perfil, **nunca más permisiva**. Si el usuario endurece su ajuste global, la restricción
> alcanza a todas sus obras, incluidas las que tengan una modalidad más abierta.
>
> El ajuste global **no reescribe** `BetaReaderAccessMode`: las dos se guardan y la
> autorización evalúa ambas, quedándose con la más restrictiva. Así, relajar el perfil
> devuelve a cada obra la modalidad que tenía.

## Reglas de negocio

- `RN-1` Una obra está siempre en exactamente uno de los tres estados.
- `RN-2` Una obra nace en `DRAFT`.
- `RN-3` Solo su autor cambia el estado.
- `RN-4` En `DRAFT`, la obra **no existe para nadie más**: no aparece en el catálogo, ni en
  recomendaciones, ni en búsquedas, y su contenido no es accesible.
- `RN-5` En `PUBLISHED` se puede leer según la modalidad de acceso, pero **no se admite
  feedback nuevo**.
- `RN-6` En `IN_CORRECTION` se admite feedback según `BetaReaderAccessMode`.
- `RN-7` Cerrar la corrección **no borra el feedback ya recibido**.
- `RN-8` Cambiar de estado exige la cuenta activada (`FEAT-USR-025`).
- `RN-9` Entrar en corrección es el momento en que se comprometen créditos, **si se resuelve
  `R-1` a favor de la reserva por obra**. Ver la sección siguiente.

`RN-4` es la que protege el activo del producto: un borrador es obra inédita que su autor
aún no ha decidido enseñar.

## Transiciones

Propuesta, pendiente de confirmar (`W-10`):

```text
            publicar                abrir corrección
  DRAFT ───────────────▶ PUBLISHED ────────────────▶ IN_CORRECTION
                            ▲                              │
                            └──────────────────────────────┘
                                 cerrar corrección
```

| Transición | ¿Permitida? | Nota |
|---|---|---|
| `DRAFT → PUBLISHED` | Sí | Publicar. Exige al menos un capítulo |
| `PUBLISHED → IN_CORRECTION` | Sí | Abrir a corrección. **No mueve créditos** |
| `IN_CORRECTION → PUBLISHED` | Sí | Cerrar la corrección. Quien ya empezó, termina y cobra |
| `DRAFT → IN_CORRECTION` | **No** | Ver abajo |
| `PUBLISHED → DRAFT` | **No, por ahora** | Ver abajo |
| `IN_CORRECTION → DRAFT` | **No, por ahora** | Ídem |

**`W-10` resuelta en lo que hacía falta para implementar, y dejando dicho lo que no.**

*Publicar y abrir son dos pasos.* Saltar de borrador a admitir correcciones escondería una
publicación dentro de otra acción, y el autor empezaría a gastar créditos sobre un texto que
nadie ha visto todavía como lo verán los demás. Dos llamadas no le cuestan nada al cliente.

*Volver a borrador no se ofrece.* Puede que alguien ya lo haya leído, y con correcciones en
marcha es peor: quien lleva horas sobre un capítulo no puede ver cómo desaparece. La decisión
de producto sigue pendiente; mientras tanto, la respuesta honesta es «no», y es reversible —
abrir un camino no rompe a nadie, cerrarlo sí.

## La relación con los créditos

`decision:0004` fijó la reserva previa y dejó abierto si es **por lector o por obra**
(`R-1`).

**Este estado inclina la balanza hacia por obra:**

| | Reserva por lector | Reserva por obra |
|---|---|---|
| Cuándo se compromete | Al conceder cada acceso | **Al entrar en corrección** |
| ¿Hay un momento explícito? | No: ocurre a medida que llegan lectores | Sí: es una acción deliberada del autor |
| ¿Encaja con «poner tus obras en corrección»? | No | **Sí, literalmente** |
| ¿Puede el autor saber el coste de antemano? | Solo por acceso | Sí, declarando cuántas correcciones quiere |
| Compensación entre contextos | Necesaria | **Innecesaria**: el autor actúa y `Credits` responde antes de abrir |

**Esa discusión quedó cerrada de otra manera.**
[`decision:0006`](../../decisions/0006-credit-system.md) elimina la reserva por completo: no
se aparta nada al poner una obra en corrección ni en ningún otro momento. `R-1` desaparece
con ella.

Lo que hace `IN_CORRECTION` es más simple de lo que se pensaba: **abre la puerta**. Si el
saldo del autor cubre el precio de un capítulo, ese capítulo admite correcciones; si no, no.
Sin declarar nada y sin comprometer nada.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Cambiar el estado | `PUT /works/{workId}/status` | `changeWorkStatus` |

Una sola operación para las transiciones, con el estado destino en el cuerpo. El servidor
valida que la transición es legal: el cliente no decide qué caminos existen.

## Eventos

| Evento | Cuándo | Consumidores |
|---|---|---|
| `WorkPublished` | `DRAFT → PUBLISHED` | `Reading`, `Community`, `Notification` |
| `WorkOpenedForCorrection` | Entra en `IN_CORRECTION` | **`Credits`**, `Reading`, `Notification` |
| `WorkClosedForCorrection` | Sale de `IN_CORRECTION` | **`Credits`**, `Feedback`, `Reading`. Quien ya empezó a corregir, termina y cobra |

`WorkOpenedForCorrection` no dispara ningún movimiento de créditos. Lo que hace es que
`Credits` recalcule qué capítulos de esa obra son corregibles y lo publique
(`ChapterCorrectabilityChanged`).

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `work` | `status` y `status_changed_at` |

**`status` no sustituye a `visibility`**, pese a lo que decía una versión anterior de esta
sección: `W-9` resolvió que son ejes distintos. Lo que ocurre es que **el eje de visibilidad
todavía no está modelado**, porque nadie lee obras todavía y no hay de quién esconderlas. El
estado se llama `PUBLISHED` y no `VISIBLE` precisamente para que añadirlo después no produzca
el sinsentido de una obra `VISIBLE` y oculta a la vez.

`BLOCKED` tampoco es un valor de `status` en el modelo: es una marca temporal `blocked_at`
puesta por `Moderation`. Encaja mejor con lo que esta misma ficha dice de él —«no lo elige el
autor»— y evita que una transición del autor pueda pisarlo.

Índices: `work(author_id, status)` para la pestaña «Mis relatos», y `work(status, ...)` para
que el catálogo y las recomendaciones **nunca** consideren borradores.

## Criterios de aceptación

- [ ] Una obra recién creada está en `DRAFT`.
- [ ] Una obra en `DRAFT` no aparece en catálogo, recomendaciones ni búsquedas.
- [ ] El contenido de una obra en `DRAFT` no es accesible para nadie salvo su autor.
- [ ] Una obra `PUBLISHED` se puede leer pero **no admite feedback nuevo**.
- [ ] Una obra sin capítulos no se puede publicar.
- [ ] Un borrador no puede saltar directamente a `IN_CORRECTION`.
- [ ] Una obra `IN_CORRECTION` admite feedback según su modalidad de acceso.
- [ ] Cerrar la corrección conserva el feedback ya recibido.
- [ ] Solo el autor puede cambiar el estado.
- [ ] Una transición no permitida se rechaza con `422`.
- [ ] Con la cuenta sin activar, cambiar el estado devuelve `403`.
- [ ] Abrir la corrección publica `WorkOpenedForCorrection`.

El segundo y tercero son los importantes: un borrador filtrado es obra inédita expuesta.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~W-9~~ | ¿`WorkStatus` sustituye a `Visibility`? | **Resuelto:** no. Son ejes distintos. El de visibilidad se modelará cuando exista quien lea obras |
| ~~R-1~~ | ¿La reserva de créditos se hace al entrar en corrección? | **Desaparece:** `decision:0006` elimina las reservas |
| W-10 | ¿Se puede despublicar? | Lo demás está resuelto arriba. Esta parte sigue abierta, y hoy la respuesta es «no» |
| W-11 | ¿Se puede editar una obra en corrección mientras la comentan? | El texto cambiaría bajo los pies del lector |
| W-14 | ¿Cuántas correcciones admite una obra a la vez? | Con reserva por obra habría que declararlo |
| W-15 | ¿Qué pasa con los accesos concedidos al cerrar la corrección? | ¿Se revocan o caducan? |

## Estado

**Especificación:** `APPROVED` (2026-09-24). `W-9` resuelta: `WorkStatus` y `Visibility` conviven como ejes
distintos. Lo que queda son detalles de transición.

**Implementación:** `PARTIAL`.

Hecho: `PUT /api/v1/works/{workId}/status` con las tres transiciones, la comprobación de que
solo el autor cambia el estado, el rechazo de publicar una obra sin capítulos y los tres
eventos. Cubierto por `tests/Functional/Work/PublishWorkTest.php`.

El estado pasa a llamarse **`PUBLISHED`** y no `VISIBLE`, para no chocar con el eje de
visibilidad que `W-9` dejó vivo.

**Falta:**

- el **eje de visibilidad** (`VISIBLE`/`HIDDEN`), y con él la combinación más útil que
  describe esta ficha: `IN_CORRECTION` + `HIDDEN`, que cierra la puerta a correctores nuevos
  sin cortar a los que ya trabajan. Se modelará cuando exista quien lea obras: hoy no hay
  catálogo ni lectura, así que no hay de quién esconderlas;
- despublicar (`W-10`), que es una decisión de producto, no código;
- ~~que `Credits` consuma `WorkOpenedForCorrection` para recalcular qué capítulos son
  corregibles. El evento se publica y todavía no lo escucha nadie.~~ — **hecho** (2026-09-26),
  y el hueco era mayor de lo que esta línea decía.

  No faltaba un recálculo: faltaba **una de las condiciones**. `CorrectabilityPolicy`
  respondía solo con dinero —saldo, precio y correcciones abiertas—, así que los capítulos de
  un borrador salían como corregibles. `Feedback` proyecta esa respuesta y abre el panel
  contra ella, de modo que la puerta se cerraba **después**, al preguntarle a `Work`: un
  rechazo que llega cuando el lector ya ha pulsado.

  Ahora `Credits` proyecta la puerta —una fila por obra, no una columna por capítulo— y es la
  primera condición de la política. Tres cosas que la implementación obligó a decidir:

  - **`WorkCreated` también se escucha.** Sin él una obra nueva no tendría fila, y la ausencia
    de fila significa «no lo sé», que deliberadamente **no bloquea**: las obras anteriores a
    este cambio siguen comportándose como hasta ahora en vez de apagarse sin que nadie lo
    decida;
  - **archivar cierra la puerta.** Archivar no cambia el estado, así que una obra retirada
    mientras estaba en corrección lo seguía estando, y quien escuchaba la creía abierta.
    `Work` publica ahora también `WorkClosedForCorrection` en ese caso — lo que de paso
    arregla que sus borradores en curso siguieran pudiendo entregarse;
  - **la versión decide, no la llegada.** Los dos hechos llevan ahora `version`, contada por
    `work.status_version`, igual que la del cuestionario
    ([`FEAT-WRK-014`](FEAT-WRK-014-configure-questionnaire.md)). La fecha no valía de
    desempate: publicar y abrir a corrección son dos clics seguidos, así que caen en el mismo
    segundo, y sin versión las reentregas de «abierta» y «cerrada» se turnan para siempre.
