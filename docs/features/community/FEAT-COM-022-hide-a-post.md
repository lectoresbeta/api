---
id: FEAT-COM-022
title: Ocultar una publicación del muro
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
  - PUT /posts/{postId}/hidden
  - DELETE /posts/{postId}/hidden
events: []
depends_on: [FEAT-COM-001]
updated: 2026-09-26
---

# FEAT-COM-022 — Ocultar una publicación del muro

## Resumen

«Esto no me interesa.» Una tarjeta concreta desaparece del muro de quien la oculta, y de nadie
más.

## Lo que no es

| No es | Porque |
|---|---|
| Borrar | La publicación sigue existiendo para todo el mundo. Borrar es de su autor (`FEAT-COM-002` `RN-13`) |
| Denunciar | Ocultar no le dice nada a nadie. Denunciar abre una reclamación (`FEAT-MOD-001`) |
| Silenciar al autor | Oculta **esa** publicación, no las demás. Para las demás está `FEAT-COM-033` |
| Bloquear | No corta nada: esa persona sigue pudiendo comentarte y escribirte |

**No se avisa al autor**, y no se cuenta. Un contador de «cuánta gente ha ocultado esto» sería
una métrica de rechazo entregada a quien escribió el texto.

## Oculta en todas partes, no solo en el muro general

Una publicación oculta no vuelve **por ningún camino**: ni en el muro general, ni cuando
alguien la repostea, ni en el muro del perfil de su autor, ni en los guardados.

Es lo que hace que la acción signifique algo. Si reaparecer bastara con visitar el perfil de
quien la escribió, «no me interesa» habría sido un gesto sin efecto, y el usuario lo aprende a
la segunda.

**Ocultar no es una regla de acceso**, es una preferencia sobre lo que se te enseña sin
pedirlo. No corta nada: esa publicación sigue siendo tuya de comentar, de repostear y de leer
por cualquier vía que no sea el muro.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Ocultar y volver a mostrar | Cuenta activada, y poder ver la publicación |

## Reglas de negocio

- `RN-1` Ocultar es **privado y unilateral**. Solo afecta al muro de quien oculta.
- `RN-2` Es **idempotente**, y por eso es `PUT`.
- `RN-3` Volver a mostrar lo que no estaba oculto responde `204`.
- `RN-4` Una publicación oculta no aparece por ningún camino del muro: propia, reposteada, de
  perfil o guardada.
- `RN-5` **No es una regla de acceso**: comentar, repostear o pedir su tarjeta de compartir
  siguen funcionando sobre una publicación oculta.
- `RN-6` Ocultar la propia no se impide: es su muro, y esconderse a uno mismo no hace daño a
  nadie.
- `RN-7` No publica eventos y no se cuenta.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|
| Publicación inexistente o que no alcanza | Igual que inexistente | `404` `POST_NOT_FOUND` |
| Ocultar lo ya oculto | Nada | `204` |
| Mostrar lo que no estaba oculto | Nada | `204` |

## Contrato de API

| Operación | Método y ruta | `operationId` | Documento |
|---|---|---|---|
| Ocultar | `PUT /posts/{postId}/hidden` | `hidePost` | `openapi/paths/community.yaml` |
| Volver a mostrar | `DELETE /posts/{postId}/hidden` | `unhidePost` | `openapi/paths/community.yaml` |

**No hay lista de ocultas**, y es deliberado: una pantalla de «lo que escondiste» convierte un
gesto de un segundo en una bandeja que gestionar.

Deshacerlo solo necesita el identificador, y quien acaba de ocultar lo tiene: el «deshacer» del
aviso que aparece justo después es el caso real, y `DELETE` funciona aunque la tarjeta ya no
esté en el muro. Si algún día hace falta una lista —porque alguien se arrepiente al día
siguiente—, será su propia decisión y su propio endpoint, no una que se cuela aquí «por si
acaso».

## Eventos

Ninguno.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Tabla nueva `community_ctx.hidden_post`: clave `(member_id, post_id)`, más `hidden_at`.
Migración `Version20260928020000`.

## Estado

**Especificación:** `APPROVED` (2026-09-26). Redactada junto con la implementación, sobre la
maqueta y las decisiones ya tomadas.

**Implementación:** `DONE` (2026-09-26). Ocultar y deshacer. **Deshacer no necesita ninguna pantalla nueva**: el aviso lleva el
identificador que quien acaba de ocultar todavía tiene en la mano, y con él basta. No hay
`GET /posts/{postId}` en esta API, así que una publicación oculta desaparece del muro y nada
más — que es exactamente lo que se pidió.
