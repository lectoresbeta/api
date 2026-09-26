---
id: FEAT-COM-020
title: Compartir una publicación fuera de la plataforma
context: Community
concept: Post
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/features/work/FEAT-WRK-011-share-a-work-outside.md
  - conversation:2026-09-25
endpoints:
  - getPostShareCard
events: []
depends_on: [FEAT-COM-002, FEAT-COM-028, FEAT-WRK-011]
updated: 2026-09-26
---

# FEAT-COM-020 — Compartir una publicación fuera

## Resumen

La hermana de [`FEAT-WRK-011`](../work/FEAT-WRK-011-share-a-work-outside.md) para una
publicación del muro, con la misma mecánica y la misma respuesta.

## La regla que la gobierna

**Solo las de audiencia `EVERYONE`.**

Una tarjeta de previsualización es pública por definición: la pide un rastreador sin sesión y
acaba en la caché de una red social. Darle tarjeta a una publicación para seguidores sería
convertir el botón de compartir en la puerta de atrás de la audiencia que su autor eligió —
que es exactamente lo que `FEAT-COM-029` existe para que decida él.

## Reglas de negocio

- `RN-1` La tarjeta trae la dirección canónica de la publicación y un extracto de su texto.
- `RN-2` **Solo las `EVERYONE`.** Una `FOLLOWERS`, una eliminada y una que no existe responden
  lo mismo: distinguirlas contaría que hay una publicación ahí y que no es para ti, que es más
  de lo que un extraño tiene que saber.
- `RN-3` **Si la publicación cita una obra, manda la obra**: su título y su sinopsis. Quien
  comparte «mirad esto» con un relato dentro quiere que se vea el relato, no las dos primeras
  líneas de su comentario.

  Y si esa obra deja de ser visible, la tarjeta **vuelve a hablar de la publicación** en vez de
  romperse. Es el mismo comportamiento que la tarjeta viva de `FEAT-COM-028`: el texto es de
  quien lo escribió y se queda.
- `RN-4` Si la publicación lleva imagen, la tarjeta la cita. La dirección apunta a la
  publicación y no al almacén, como en el muro.
- `RN-5` Pública, cacheable y **sin nadie dentro**, por las mismas razones que `FEAT-WRK-011`.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Tarjeta de una publicación | `GET /api/v1/posts/{postId}/share` | `getPostShareCard` |

## Configuración

| Variable | Qué es |
|---|---|
| `POST_SHARE_URL_TEMPLATE` | La página de la publicación en el frontend, con `{id}` |

## Eventos

Ninguno.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Ninguno.

## Criterios de aceptación

- [x] Una publicación pública trae su tarjeta.
- [x] Una para seguidores no, y no se distingue de una inexistente.
- [x] Una eliminada tampoco.
- [x] Si cita una obra, manda la obra.
- [x] Si esa obra desaparece, la tarjeta vuelve a hablar de la publicación.
- [x] La tarjeta nombra el contenido y no a la persona.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
