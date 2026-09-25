---
id: FEAT-COM-004
title: Publicar buscando writing buddy
context: Community
concept: Post
actors: [Writer]
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
depends_on: [FEAT-COM-002, FEAT-USR-011, FEAT-RDG-008]
updated: 2026-09-25
---

# FEAT-COM-004 — Publicar buscando writing buddy

## Resumen

Una publicación del muro con intención `LOOKING_FOR_WRITING_BUDDY`: alguien que escribe dice
en voz alta que busca a otra persona con quien acompañarse.

## Qué añade sobre `FEAT-COM-002`

El tipo ya existía en el enum desde `FEAT-COM-002`. Lo que faltaba era **la regla que la hace
atendible**, y es distinta de la de su hermana `FEAT-COM-003`:

| | `FEAT-COM-003` (lectores beta) | Esta |
|---|---|---|
| Qué impone | **Un adjunto**: la obra que se ofrece | **Una coherencia**: tener la puerta abierta |
| Por qué | Sin obra nadie sabe qué se le ofrece | Con la puerta cerrada nadie puede responder |

## Reglas de negocio

- `RN-1` Cualquier persona con cuenta activada puede publicar con esta intención. **No exige
  obra**: no se ofrece un texto, se ofrece compañía.
- `RN-2` **Se rechaza si quien publica tiene cerradas las propuestas de writing buddy** en sus
  ajustes de recepción (`FEAT-USR-011`). Responde `409` con `code: RECEPTION_CLOSED`.

  Publicar una petición que nadie puede atender manda contra un muro a todo el que responda, y
  quien publicó no se entera nunca de por qué no le escribe nadie. El fallo es silencioso por
  los dos lados, que es la clase de fallo que no se descubre.
- `RN-3` **No se abre el ajuste solo.** Publicar no es consentir, y un ajuste de recepción que
  se abre sin pedirlo deja de ser un ajuste. Quien quiera publicar esto abre la puerta primero,
  a mano y sabiendo lo que hace.
- `RN-4` Se comprueba **al publicar y solo al publicar**. Cerrar la recepción después no
  retira la publicación: una es un acto con fecha y la otra un interruptor que su dueño mueve
  cuando quiere, y hacer desaparecer textos viejos al tocarlo sorprendería a cualquiera.
- `RN-5` Puede llevar una obra, como cualquier publicación que no sea reclutar lectores
  (`FEAT-COM-003` `RN-5`). Enseñar lo que escribes mientras buscas con quién escribir es
  exactamente lo que alguien haría.
- `RN-6` **No crea ningún vínculo.** Es una publicación: quien se interese propone por donde
  se propone, que es `FEAT-RDG-008`.

## Flujos alternativos y errores

| Caso | Respuesta |
|---|---|
| Propuestas de writing buddy cerradas | `409 RECEPTION_CLOSED` |

## Contrato de API

Ninguno nuevo: es `POST /api/v1/posts` (`createPost`) con `type: LOOKING_FOR_WRITING_BUDDY`.

## Cómo cruza el límite entre contextos

`Community` no lee los ajustes de `User`: pregunta por el contrato `ProposalRecipients`, que
gana para esto un método sobre uno mismo —`openDoorsOf`—. Los dos que ya tenía responden «¿puede
este abordar a aquel?» y contestan `false` cuando las dos partes son la misma persona, que es
lo correcto para lo que preguntan y no sirve aquí.

## Eventos

Ninguno propio. Publica `PostPublished`, como cualquier otra publicación.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Ninguno.

## Criterios de aceptación

- [x] Con la puerta abierta —como nace toda cuenta— sale.
- [x] Con las propuestas de writing buddy cerradas, se rechaza.
- [x] Mira **su** puerta y no la otra.
- [x] Cerrar la recepción después no retira lo publicado.
- [x] Puede llevar una obra.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
