---
id: FEAT-USR-011
title: Configurar la recepción de propuestas de lector beta y writing buddy
context: User
concept: Preferences
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/ui/settings.md
  - conversation:2026-09-25
endpoints:
  - getMyReceptionSettings
  - updateMyReceptionSettings
events: []
depends_on: [FEAT-RDG-004]
updated: 2026-09-25
---

# FEAT-USR-011 — Configurar la recepción de propuestas de lector beta y writing buddy

## Resumen

Dos interruptores: **«acepto que me inviten a leer obras»** y **«acepto que me propongan ser
writing buddy»**.

## Recepción no es aviso

`S-19` preguntaba dónde encaja esto, y la respuesta es que **no encaja en la pestaña de
notificaciones**, aunque se le parezca.

Silenciar un aviso deja la invitación creada, esperando respuesta en una lista que su
destinatario ha decidido no mirar. El autor que la mandó espera algo que nunca llega, y el
invitado tiene una deuda pendiente que no sabe que tiene. **Cerrar esto impide que la
invitación exista.**

Es la misma distinción que separa `FEAT-USR-039` (qué me cuentas) de `FEAT-USR-038` (qué
puedes hacer conmigo). Esto es lo segundo.

## Dos interruptores y no uno

Son dos peticiones distintas: leer un texto ajeno es un rato, y ser compañero de escritura es
un compromiso que dura. Con un solo interruptor habría que renunciar a las dos para librarse
de una, y quien quiera seguir leyendo para otros sin que le propongan sociedades no tendría
forma de decirlo.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| Usuario | Ver y cambiar **los suyos** | Sesión iniciada |

No existe la versión de otra persona: los ajustes de recepción de alguien no son asunto de
nadie más. Que estén cerrados se nota al intentar invitar, que es lo justo y lo mínimo.

## Reglas de negocio

- `RN-1` Los dos empiezan **abiertos**, y explícitamente. Una fila que falta se lee como los
  valores por defecto, que es la misma frase dicha en voz alta — nunca como «no ha decidido».
- `RN-2` **Un campo ausente no cambia nada.** La pantalla mueve un interruptor cada vez, y
  exigir los dos en cada petición haría que mover uno pisara el otro con lo que el cliente
  tuviera cargado.
- `RN-3` Cerrar una puerta **no deshace lo que ya llegó**: una invitación pendiente sigue
  esperando respuesta. Es la misma decisión que `FEAT-USR-010` `RN-5` con las conversaciones
  abiertas — el ajuste dice quién puede proponer a partir de ahora, no borra lo que alguien
  propuso de buena fe.
- `RN-4` Invitar a quien tiene el buzón cerrado **falla al invitar**, con `409` y diciendo por
  qué. A diferencia de la edad —que no se revela nunca, porque un mensaje que la insinuara
  convertiría el botón de invitar en un comprobador de quién es menor—, esto sí se dice:
  callarlo dejaría al autor esperando una respuesta que no va a llegar.
- `RN-5` `Reading` lo consulta por **contrato publicado**, nunca leyendo la tabla.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Ver los míos | `GET /api/v1/me/reception-settings` | `getMyReceptionSettings` |
| Cambiarlos | `PUT /api/v1/me/reception-settings` | `updateMyReceptionSettings` |

## Contrato publicado

`ProposalRecipients`, con dos preguntas y **dos booleanos**. Nunca el ajuste, como el resto de
puertas de `User`.

Es **un solo contrato para las dos**, a diferencia de `AuthorAudience` y `MessageAudience`,
que sí se separaron. El criterio es el mismo en los dos casos: se separa lo que son puertas
distintas. Aquellas lo eran —los textos de un autor y su buzón— y estas no: las dos responden
a «¿se le puede abordar con una propuesta?», las pregunta el mismo contexto y en el mismo
momento del flujo. Partirlas en dos interfaces que `Reading` inyectaría juntas sería
ceremonia.

## Eventos

Ninguno. Un cambio de ajuste no es un hecho que a otro contexto le importe: quien necesita la
respuesta la pregunta cuando va a invitar, y así no hay copia que envejezca.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

`user_ctx.user_reception_settings`, una fila por usuario **y solo cuando la toca**. Sembrar
una por cuenta para guardar dos `true` sería mantener una tabla del tamaño del padrón para no
escribir un `?? true`.

## Criterios de aceptación

- [x] Los dos empiezan abiertos para quien nunca los ha tocado.
- [x] Mover un interruptor deja el otro como estaba.
- [x] Con el de invitaciones cerrado, invitar falla y **no queda nada pendiente**.
- [x] Con él abierto, la invitación entra.
- [x] Cerrarlo no toca las invitaciones ya pendientes.

## Preguntas abiertas

Ninguna. `S-19` queda resuelta: es recepción y no aviso, y por eso tiene endpoint propio.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`. El interruptor de writing buddy queda guardado y consultable; lo
consume [`FEAT-RDG-008`](../reading/FEAT-RDG-008-propose-writing-buddy.md).
