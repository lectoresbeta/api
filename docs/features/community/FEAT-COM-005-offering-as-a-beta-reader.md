---
id: FEAT-COM-005
title: Publicar ofreciéndose como lector beta
context: Community
concept: Post
actors: [Reader]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/ui/create-post.md
  - docs/features/community/FEAT-COM-002-create-post.md
  - conversation:2026-09-25
endpoints:
  - createPost
events: []
depends_on: [FEAT-COM-002, FEAT-USR-011, FEAT-RDG-004]
updated: 2026-09-25
---

# FEAT-COM-005 — Publicar ofreciéndose como lector beta

## Resumen

Una publicación del muro con intención `OFFERING_AS_BETA_READER`: alguien que lee se ofrece a
leer, y dice qué y cómo.

Es **la imagen especular de `FEAT-COM-003`**. Allí un autor busca lectores para un texto
concreto; aquí un lector se ofrece sin texto ninguno, porque lo que ofrece es su tiempo.

## Reglas de negocio

- `RN-1` Cualquier persona con cuenta activada puede publicar con esta intención. **No exige
  obra**, y no podría: no se ofrece un texto, se ofrece uno mismo.
- `RN-2` **Se rechaza si quien publica tiene cerradas las invitaciones de lector beta** en sus
  ajustes de recepción (`FEAT-USR-011`). Responde `409` con `code: RECEPTION_CLOSED`.

  Ofrecerse a leer teniendo cerrada la puerta por la que llegan los encargos es una
  contradicción que solo descubre quien intenta responder, y entonces ya es tarde para los dos.
- `RN-3` **No se abre el ajuste solo**, por lo mismo que en `FEAT-COM-004` `RN-3`.
- `RN-4` Se comprueba al publicar y solo al publicar.
- `RN-5` Mira **su propia puerta**: las invitaciones de lector beta, no las propuestas de
  writing buddy. Son dos ajustes independientes desde `FEAT-USR-011` y aquí se nota.
- `RN-6` **No concede acceso a nada.** Es una publicación: quien se interese invita por donde
  se invita, que es `FEAT-RDG-004`.

## Flujos alternativos y errores

| Caso | Respuesta |
|---|---|
| Invitaciones de lector beta cerradas | `409 RECEPTION_CLOSED` |

## Contrato de API

Ninguno nuevo: es `POST /api/v1/posts` (`createPost`) con `type: OFFERING_AS_BETA_READER`.

## Eventos

Ninguno propio. Publica `PostPublished`.

## Efectos en créditos

Ninguno **al publicar**. Leer y corregir sí los mueve, pero eso ocurre después y lo decide
`Credits` (`FEAT-CRD-006`).

## Modelo de datos afectado

Ninguno.

## Criterios de aceptación

- [x] Con la puerta abierta sale, sin exigir obra.
- [x] Con las invitaciones de lector beta cerradas, se rechaza.
- [x] Mira **su** puerta y no la de writing buddy.
- [x] Cerrar la recepción después no retira lo publicado.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
