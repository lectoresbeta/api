---
id: FEAT-COM-021
title: Guardar una publicación
context: Community
concept: Curation
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P3
sources:
  - docs/ui/post-interactions.md
  - conversation:2026-09-26
endpoints:
  - PUT /posts/{postId}/saved
  - DELETE /posts/{postId}/saved
  - GET /me/saved-posts
events: []
depends_on: [FEAT-COM-001, FEAT-COM-002]
updated: 2026-09-26
---

# FEAT-COM-021 — Guardar una publicación

## Resumen

Apartar una publicación para volver a ella. Una marca **privada** de quien la pone: el autor de
la publicación no se entera, no se cuenta en ninguna parte y no aparece en ningún contador de
la tarjeta.

## Guardar no es un «me gusta»

Conviene fijarlo porque se parecen en la pantalla y no se parecen en nada más.

| | «Me gusta» (`FEAT-COM-008`) | Guardar |
|---|---|---|
| A quién le habla | Al autor: es reconocimiento | A uno mismo: es una nota |
| ¿Se cuenta? | Sí, en la tarjeta | **No** |
| ¿Se sabe quién? | Sí | **No.** Nadie, ni el autor |
| ¿Publica evento? | Sí | **No** |

De ahí que no haya `savedCount` ni lista de quién guardó qué. Un contador de guardados
convertiría una nota privada en una señal pública, y entonces la gente dejaría de usarla para
lo que sirve.

## La lista de guardados es el muro con un filtro

La misma decisión que el muro de un perfil (`FEAT-COM-026`), y por la misma razón.

`GET /me/saved-posts` no es una consulta nueva: es `wallFor` restringido a las publicaciones
que uno guardó. De ahí sale gratis lo que importa: **guardar no conserva acceso**. Una
publicación que se hizo privada, cuyo autor te bloqueó o que se borró deja de aparecer en tus
guardados sin que esta ficha tenga que saber por qué.

Escribir una consulta aparte habría significado dos copias de cada regla de audiencia, y a la
larga una de las dos se queda atrás — que en esta es la que enseña el texto de alguien a quien
ya no debería.

La consecuencia de esa decisión: **el orden es el del muro**, por fecha de publicación
descendente, no por fecha de guardado. Ordenar por cuándo guardaste exigiría un cursor distinto
y una consulta propia, que es exactamente lo que se acaba de descartar.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Guardar y quitar de guardados | Cuenta activada, y **poder ver la publicación** |
| `User` | Ver sus guardados | Los suyos. No existe la lista de otro |

## Reglas de negocio

- `RN-1` Guardar es **privado**. No se cuenta, no se anuncia y nadie más lo ve.
- `RN-2` Solo se guarda lo que se puede ver. Guardar una publicación que no te alcanza responde
  como si no existiera.
- `RN-3` Es **idempotente**: guardar dos veces es guardar. Por eso es `PUT` y no `POST`.
- `RN-4` Quitar de guardados lo que no estaba guardado también responde `204`.
- `RN-5` **Guardar no conserva acceso.** La lista aplica las reglas de audiencia del muro cada
  vez que se lee.
- `RN-6` Uno puede guardar su propia publicación. No hay motivo para impedirlo.
- `RN-7` No publica eventos: no es un hecho de negocio para nadie.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Publicación inexistente, borrada o que no alcanza | Igual que inexistente | `404` `POST_NOT_FOUND` |
| Guardar lo ya guardado | Nada | `204` |
| Quitar lo que no estaba | Nada | `204` |
| Una publicación guardada deja de ser visible | Desaparece de la lista | — |

## Contrato de API

| Operación | Método y ruta | `operationId` | Documento |
|---|---|---|---|
| Guardar | `PUT /posts/{postId}/saved` | `savePost` | `openapi/paths/community.yaml` |
| Quitar | `DELETE /posts/{postId}/saved` | `unsavePost` | `openapi/paths/community.yaml` |
| Mis guardados | `GET /me/saved-posts` | `listSavedPosts` | `openapi/paths/community.yaml` |

La lista devuelve **la misma tarjeta que el muro** y pagina con el mismo cursor. Dos formas
distintas para la misma tarjeta serían dos plantillas que mantener.

Los reposts **no salen** en los guardados: lo que se guarda es una publicación, y enseñarla con
la cabecera de quien la reposteó diría algo sobre por qué está ahí que no es verdad.

## Eventos

Ninguno.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Tabla nueva `community_ctx.saved_post`: clave `(member_id, post_id)`, más `saved_at`. Índice por
`member_id`.

Migración `Version20260928020000`, compartida con `FEAT-COM-022` y `FEAT-COM-033`: las tres
tablas nacen del mismo cambio.

## Estado

**Especificación:** `APPROVED` (2026-09-26). Redactada junto con la implementación, sobre la
maqueta y las decisiones ya tomadas.

**Implementación:** `DONE` (2026-09-26). Guardar, quitar y la lista, con la misma tarjeta y el mismo cursor que el muro.
Los reposts no salen en los guardados: lo que se guarda es una publicación, y enseñarla con
la cabecera de quien la reposteó diría algo sobre por qué está ahí que no es verdad.
