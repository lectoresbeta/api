---
id: FEAT-FBK-012
title: Control antifraude de las correcciones
context: Feedback
concept: Correction
actors: []
spec_status: PENDING
impl_status: BLOCKED
priority: P0
sources:
  - conversation:2026-09-22 (se implementará un sistema antifraude, por definir)
  - docs/ui/read-chapter.md
endpoints: []
events: [CorrectionFlagged]
depends_on: [FEAT-FBK-003, FEAT-CRD-016]
updated: 2026-09-22
---

# FEAT-FBK-012 — Control antifraude de las correcciones

## Resumen

Enviar una corrección genera créditos. Eso convierte el formulario en **el único punto de la
plataforma donde escribir texto produce dinero interno**, y por tanto en el objetivo natural
de quien quiera acumular saldo sin aportar nada.

Producto confirma que habrá un sistema de control, **con IA o con otros mecanismos**. El
mecanismo concreto está por definir.

## Por qué no basta con la longitud mínima

[`FEAT-FBK-003`](FEAT-FBK-003-answer-correction-questionnaire.md) exige un mínimo de palabras
por respuesta. Es necesario y es insuficiente: una respuesta larga puede ser igual de vacía.

| Vector | Qué lo hace fácil | Qué no lo detiene |
|---|---|---|
| Respuestas genéricas aplicables a cualquier texto | «Me gustó el ritmo y los personajes» vale para todo | La longitud mínima |
| Texto generado automáticamente | Coste cero para el defraudador | La longitud mínima |
| Plantilla repetida entre capítulos | La corrección es por capítulo: el mismo texto, *n* veces | La longitud mínima |
| Copiar el propio texto de la obra | Rellena palabras con material del autor | La longitud mínima |
| Cuentas coordinadas que se corrigen entre sí | Genera créditos en circuito cerrado | Cualquier control por corrección aislada |

El último es cualitativamente distinto: **no se detecta mirando una corrección**, solo
mirando el grafo de quién corrige a quién. Un control que analice textos de uno en uno lo
dejará pasar entero.

## Lo que hay que decidir

| # | Pregunta |
|---|---|
| `AF-1` | ¿Qué mecanismo: IA evaluando calidad, heurísticas, reputación del corrector, revisión humana, o una combinación? |
| `AF-2` | ¿El control es **previo** al abono o **posterior**, con reversión? |
| `AF-3` | ¿Puede el autor rechazar una corrección y recuperar su crédito? |
| `AF-4` | Si se revierte un abono, ¿qué pasa si el lector ya gastó esos créditos? |
| `AF-5` | ¿Hay apelación? Un falso positivo deja a alguien sin cobrar por un trabajo real |
| `AF-6` | ¿Se detecta la colusión entre cuentas? Requiere análisis del grafo, no del texto |

`AF-2` es la decisión estructural, y las dos opciones tienen costes opuestos:

- **Control previo**: el lector no cobra hasta pasar el filtro. Protege la economía, pero
  introduce latencia y hace que un trabajo legítimo quede en suspenso.
- **Control posterior**: el lector cobra y se revierte si se detecta fraude. Buena
  experiencia, pero `AF-4` se vuelve inevitable: revertir créditos ya gastados obliga a
  admitir saldos negativos, justo lo que
  [`decision:0004`](../../decisions/0004-credit-reservation-on-access-grant.md) evitó.

`AF-3` es delicada por otro motivo: dar al autor poder de veto sobre lo que paga crea el
incentivo de rechazar correcciones duras. **Una crítica negativa no es una corrección
fraudulenta**, y un mecanismo que no distinga ambas cosas destruye el producto: los lectores
aprenderían a escribir elogios.

## Restricciones que ya se pueden fijar

Independientemente del mecanismo elegido:

- `RN-1` El abono al lector y el cargo al autor son **decisiones de `Credits`**. El control
  antifraude aporta una **señal**; no mueve créditos por su cuenta.
- `RN-2` Toda decisión automática queda **registrada y es auditable**: qué se evaluó, con qué
  versión del mecanismo y con qué resultado.
- `RN-3` Un rechazo debe poder **explicarse al usuario** en términos que entienda.
- `RN-4` El texto de las correcciones **no sale del contexto** hacia terceros sin una
  decisión explícita documentada. Si el mecanismo es una IA externa, eso es una integración
  con implicaciones de privacidad y hay que tratarla como tal.
- `RN-5` El mecanismo debe poder **desactivarse** sin bloquear el envío de correcciones.

`RN-4` no es una formalidad: la plataforma existe para custodiar **obra inédita**, y el texto
de una corrección cita y describe esa obra. Mandarlo a un servicio externo es una decisión de
producto y probablemente de contrato, no un detalle de implementación.

## Modelo de datos afectado

Una `CorrectionAssessment` asociada a la corrección: mecanismo, versión, resultado, motivo y
fecha. Nunca se sobrescribe; una reevaluación es un registro nuevo.

## Criterios de aceptación

Provisionales hasta que exista mecanismo:

- [ ] Ninguna decisión antifraude mueve créditos directamente.
- [ ] Toda evaluación queda registrada con su versión y su resultado.
- [ ] El mecanismo puede desactivarse sin impedir enviar correcciones.
- [ ] Una crítica negativa pero legítima no se marca como fraudulenta.

## Estado

**Especificación:** `PENDING`. Producto confirma que existirá; el mecanismo está sin definir.

**Implementación:** `BLOCKED` por `AF-1` y `AF-2`.

Conviene no dejarlo para el final. Construir la economía de créditos sin haber decidido dónde
encaja el control obliga después a reabrir el camino del abono, que es el más delicado del
sistema.
