---
id: FEAT-WRK-016
title: Estado de una obra — borrador, visible y en corrección
context: Work
concept: Manuscript
actors: [Writer]
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - conversation:2026-09-22 (pestaña «Mis relatos»)
  - figma:1800-14718 (modal de créditos, «poner tus obras en corrección»)
  - docs/ui/my-works.md
endpoints: [PUT /works/{workId}/status]
events: [WorkPublished, WorkOpenedForCorrection, WorkClosedForCorrection]
depends_on: [FEAT-WRK-001]
updated: 2026-09-22
---

# FEAT-WRK-016 — Estado de una obra

## Resumen

Una obra está en uno de tres estados, y solo en uno:

| Estado | `WorkStatus` | ¿Se puede leer? | ¿Se puede comentar? |
|---|---|---|---|
| En borrador | `DRAFT` | Solo el autor | No |
| Visible | `VISIBLE` | Sí | **No** |
| En corrección | `IN_CORRECTION` | Sí | **Sí** |

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

| Atributo documentado | Qué ocurre con él |
|---|---|
| `Visibility`: `VISIBLE` / `HIDDEN` | **Se sustituye por `WorkStatus`.** `HIDDEN` equivale a `DRAFT`, `VISIBLE` a `VISIBLE` |
| `BetaReaderAccessMode`: `PUBLIC` / `ON_REQUEST` / `PRIVATE` | **Se conserva**, pero solo tiene efecto en `IN_CORRECTION`. Responde «quién puede comentar», no «si se puede» |

Son dos ejes distintos y conviene no volver a mezclarlos: el estado dice **si** la puerta
está abierta; la modalidad, **para quién**.

> **Y ahora hay un tercer eje** (`S-14`). La pantalla de Configuración añade un ajuste
> **global del usuario**: «¿Quién puede comentar mis textos?»
> ([`FEAT-USR-038`](../user/FEAT-USR-038-privacy-settings.md)). Responde casi a la misma
> pregunta que `BetaReaderAccessMode`, pero para todo lo que escribe el autor.
>
> Propuesta: el ajuste global actúa como **techo**. Una obra puede ser más restrictiva que el
> perfil, nunca más permisiva. Si se permitiera lo contrario, el ajuste de privacidad sería
> una recomendación, y un ajuste de privacidad que se puede ignorar no es un ajuste de
> privacidad.

Pendiente de confirmar (`W-9`).

## Reglas de negocio

- `RN-1` Una obra está siempre en exactamente uno de los tres estados.
- `RN-2` Una obra nace en `DRAFT`.
- `RN-3` Solo su autor cambia el estado.
- `RN-4` En `DRAFT`, la obra **no existe para nadie más**: no aparece en el catálogo, ni en
  recomendaciones, ni en búsquedas, y su contenido no es accesible.
- `RN-5` En `VISIBLE` se puede leer según la modalidad de acceso, pero **no se admite
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
  DRAFT ───────────────▶ VISIBLE ──────────────────▶ IN_CORRECTION
    ▲                       ▲                              │
    │                       └──────────────────────────────┘
    │                            cerrar corrección
    └─ ¿despublicar? (W-10)
```

| Transición | ¿Permitida? | Nota |
|---|---|---|
| `DRAFT → VISIBLE` | Sí | Publicar |
| `VISIBLE → IN_CORRECTION` | Sí | Abrir a feedback. Aquí se comprometen los créditos |
| `IN_CORRECTION → VISIBLE` | Sí | Cerrar la corrección. Las retenciones sin usar se liberan |
| `DRAFT → IN_CORRECTION` | Probablemente sí | Publicar y abrir en un solo paso |
| `VISIBLE → DRAFT` | **Sin decidir** (`W-10`) | Despublicar algo que otros ya han visto |
| `IN_CORRECTION → DRAFT` | **Sin decidir** | Implicaría cerrar la corrección primero |

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

La última fila es la más valiosa: con la reserva por obra desaparece la compensación que
`decision:0004` tuvo que introducir, porque el autor realiza una acción explícita y puede
recibir un «no hay saldo» de inmediato en lugar de que se le revoque un acceso después.

**No se cambia `decision:0004` aquí.** Pero `R-1` debería resolverse con esta pantalla
delante, y antes de implementar el ciclo de retenciones.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Cambiar el estado | `PUT /works/{workId}/status` | `changeWorkStatus` |

Una sola operación para las transiciones, con el estado destino en el cuerpo. El servidor
valida que la transición es legal: el cliente no decide qué caminos existen.

## Eventos

| Evento | Cuándo | Consumidores |
|---|---|---|
| `WorkPublished` | `DRAFT → VISIBLE` | `Reading`, `Community`, `Notification` |
| `WorkOpenedForCorrection` | Entra en `IN_CORRECTION` | **`Credits`**, `Reading`, `Notification` |
| `WorkClosedForCorrection` | Sale de `IN_CORRECTION` | **`Credits`** (libera retenciones), `Reading` |

`WorkOpenedForCorrection` sería el disparador de la reserva si se resuelve `R-1` por obra.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `work` | `status` sustituye a `visibility`; `status_changed_at` |

Índices: `work(author_id, status)` para la pestaña «Mis relatos», y `work(status, ...)` para
que el catálogo y las recomendaciones **nunca** consideren borradores.

## Criterios de aceptación

- [ ] Una obra recién creada está en `DRAFT`.
- [ ] Una obra en `DRAFT` no aparece en catálogo, recomendaciones ni búsquedas.
- [ ] El contenido de una obra en `DRAFT` no es accesible para nadie salvo su autor.
- [ ] Una obra `VISIBLE` se puede leer pero **no admite feedback nuevo**.
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
| **W-9** | ¿Se confirma que `WorkStatus` sustituye a `Visibility`? | Modelo de `Work` |
| **R-1** | ¿La reserva de créditos se hace al entrar en corrección? | Cierra `decision:0004` y elimina la compensación |
| W-10 | ¿Qué transiciones son legales? ¿Se puede despublicar? | Máquina de estados |
| W-11 | ¿Se puede editar una obra en corrección mientras la comentan? | El texto cambiaría bajo los pies del lector |
| W-14 | ¿Cuántas correcciones admite una obra a la vez? | Con reserva por obra habría que declararlo |
| W-15 | ¿Qué pasa con los accesos concedidos al cerrar la corrección? | ¿Se revocan o caducan? |

## Estado

**Especificación:** `DRAFT`. Los tres estados están claros. Para llegar a `APPROVED` hacen
falta `W-9`, `W-10` y, sobre todo, `R-1`.

**Implementación:** `TODO`.
