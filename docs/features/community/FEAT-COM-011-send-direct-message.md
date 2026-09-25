---
id: FEAT-COM-011
title: Enviar un mensaje directo
context: Community
concept: Messaging
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - _sources/use-cases.pdf#p6
  - docs/ui/app-layout-and-navigation.md
  - conversation:2026-09-25
endpoints: [POST /users/{userId}/messages]
events: []
depends_on: [FEAT-USR-010, FEAT-COM-034]
updated: 2026-09-25
---

# FEAT-COM-011 — Enviar un mensaje directo

## Resumen

Escribirle a alguien. La primera vez abre la conversación; el resto la continúa.

**Una conversación por par de personas.** No hay grupos, no hay asunto y no hay conversaciones
paralelas con la misma persona: el par ordenado es la identidad, así que `(A,B)` y `(B,A)` son
la misma fila.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Escribir a otra persona | Con la cuenta activada, si el destinatario lo admite y no hay bloqueo |

## Las dos puertas, y por qué son dos

| Puerta | Quién la decide | Qué corta |
|---|---|---|
| El ajuste `messagePermission` | `User` (`FEAT-USR-010`) | **Abrir** una conversación nueva |
| El bloqueo | `Community` (`FEAT-COM-034`) | Todo, también lo ya abierto, en los dos sentidos |

Son dos porque significan cosas distintas. El ajuste es una **preferencia** —«prefiero que no
me escriba cualquiera»— y el bloqueo es una **regla de acceso** contra una persona concreta.
Un ajuste que cerrase hilos abiertos convertiría el primero en el segundo sin que nadie lo
pidiera.

El ajuste lo responde `User` por su contrato `MessageAudience`, y **devuelve un booleano**:
este contexto no ve nunca el valor. El bloqueo lo decide `Community` con su propia
proyección, porque es suyo.

## Reglas de negocio

- `RN-1` Una conversación por par. La primera vez se crea; después se reutiliza.
- `RN-2` **Nadie se escribe a sí mismo.**
- `RN-3` Abrir una conversación exige que el destinatario lo admita (`FEAT-USR-010`).
- `RN-4` **Continuar una ya abierta no lo exige** (`FEAT-USR-010` `RN-5`): un hilo a medias no
  se queda sin respuesta porque alguien tocase un ajuste después.
- `RN-5` Un bloqueo **corta siempre**, en los dos sentidos, abierta o no.
- `RN-6` El cuerpo no puede estar vacío y tiene un tope de 4.000 caracteres. No es texto
  literario: es una conversación.
- `RN-7` Un destinatario que **no existe, está eliminado o expulsado** responde `404`. No se
  distingue de «no te admite» más de lo necesario, porque distinguirlo confirmaría quién está
  en la plataforma.
- `RN-8` El envío **avisa al destinatario** (`DIRECT_MESSAGE_RECEIVED`), y ese aviso es **solo
  de plataforma**: nadie quiere un correo por cada mensaje.
- `RN-9` **El aviso no lleva el mensaje.** Ni una línea. Lo que viaja es quién escribió y a
  qué conversación entrar.

`RN-9` no es una precaución genérica: un aviso que llevara el cuerpo lo sacaría de la
plataforma por un canal que su remitente no eligió.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Escribir | `POST /api/v1/users/{userId}/messages` | `sendDirectMessage` |

Cuelga de la **persona** y no de la conversación, y es deliberado: quien escribe por primera
vez no tiene identificador de conversación que poner, y obligarle a crearla antes sería dos
llamadas para lo que el diseño enseña como un botón.

Devuelve `201` con el identificador de la conversación y el del mensaje, que es lo que el
cliente necesita para entrar en el hilo.

## Eventos

**Ninguno hacia otros contextos.** El aviso se crea llamando a `Notification` por el camino
de siempre, y no hay nada aquí que interese a `Credits`, a `Work` ni a `Reading`.

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `conversation` | El par ordenado, cuándo empezó y cuándo fue el último mensaje |
| `direct_message` | El cuerpo, quién lo mandó y si se leyó |

Las dos **ya existían** desde el primer corte del esquema, sin nada que las usara.

## Criterios de aceptación

- [x] Escribir por primera vez crea la conversación.
- [x] Escribir otra vez reutiliza la misma, escriba quien escriba de los dos.
- [x] Nadie se escribe a sí mismo.
- [x] Con `NOBODY` no se abre una conversación nueva.
- [x] Con `FOLLOWERS` solo la abre quien sigue al destinatario.
- [x] Endurecer el ajuste no impide continuar una conversación ya abierta.
- [x] Un bloqueo impide escribir en los dos sentidos, también en una abierta.
- [x] Un cuerpo vacío se rechaza.
- [x] El destinatario recibe un aviso, y el aviso **no lleva el mensaje**.
- [x] Sin la cuenta activada, `403`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| CM-10 | ¿Se pueden adjuntar imágenes a un mensaje? | El diseño no lo enseña |
| CM-11 | ¿Se puede editar o borrar un mensaje enviado? | Lo mismo |

## Estado

**Especificación:** `APPROVED` (2026-09-25).

**Implementación:** `DONE` (2026-09-25).
