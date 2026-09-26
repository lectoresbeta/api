---
id: FEAT-WRK-011
title: Generar enlace para compartir en redes sociales y captar LB
context: Work
concept: Manuscript
actors: [Writer]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/features/work/FEAT-WRK-010-public-correction-link.md
  - conversation:2026-09-25
endpoints:
  - getWorkShareCard
events: []
depends_on: [FEAT-WRK-004, FEAT-COM-028]
updated: 2026-09-26
---

# FEAT-WRK-011 — Compartir una obra fuera de la plataforma

## Resumen

Lo que hace falta para que pegar el enlace de una obra en WhatsApp, Twitter o LinkedIn pinte
una tarjeta con su título y su sinopsis en vez de una URL desnuda.

## No se genera ningún enlace, y ese es el diseño

El título de la ficha dice «generar enlace» y la implementación **no genera ninguno**. El
enlace es la dirección canónica de la obra, la de siempre.

Se consideró que llevara un token, y se descartó. Conviene ver por qué, porque la alternativa
parecía más potente:

| | Sin token (lo hecho) | Con token |
|---|---|---|
| Qué circula por redes | Una URL pública | **Una credencial** |
| Caduca | No hace falta | Hay que decidirlo |
| Se revoca | No hace falta | Hay que construirlo |
| Abre algo cerrado | No | Sí, y ese es el problema |

Una obra inédita es el activo del producto. Un enlace que la abre y que circula por redes
sociales es el peor sitio posible para una credencial, y ya existe el mecanismo para
enseñarla a quien tú quieras: [`FEAT-WRK-010`](FEAT-WRK-010-public-correction-link.md), con su
token, su límite de usos y su propósito acotado.

**Esta ficha es otra cosa**: es la previsualización de lo que ya es público.

## Reglas de negocio

- `RN-1` La tarjeta trae la dirección canónica de la obra, su título y un extracto de la
  sinopsis.
- `RN-2` **Solo de obras visibles para cualquiera.** Un borrador, una obra archivada o una
  bloqueada por moderación no tienen tarjeta, y responden lo mismo que una obra que no existe:
  decir cuál de las dos es contaría que hay una obra inédita ahí.

  La comprobación no se escribe aquí: se pregunta a `WorkCards`, que ya responde solo por las
  visibles. Es la misma regla que sostiene la tarjeta viva de `FEAT-COM-028`.
- `RN-3` **Es pública y sin sesión**, y tiene que serlo: quien la pide es un rastreador. Por lo
  mismo se cachea en público, cinco minutos — lo justo para absorber a los rastreadores que
  llegan juntos cuando algo se comparte, sin que cambiar un título tarde una tarde en verse.
- `RN-4` **No lleva a nadie dentro.** Poner el nombre de quien escribe obligaría a resolver su
  privacidad de perfil para alguien sin sesión, que es justo la clase de cosa por la que se
  filtra un dato. La tarjeta habla del contenido, no de la persona.
- `RN-5` La dirección apunta al **frontend**, no a la API: lo que se comparte es una página que
  alguien abre, no un JSON. Sale de `WORK_SHARE_URL_TEMPLATE`.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Tarjeta de una obra | `GET /api/v1/works/{workId}/share` | `getWorkShareCard` |

## Configuración

| Variable | Qué es |
|---|---|
| `WORK_SHARE_URL_TEMPLATE` | La página de la obra en el frontend, con `{id}` donde va el identificador |

## Eventos

Ninguno. Es una lectura.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Ninguno, y esa ausencia es la ficha: no hay nada que guardar porque no se genera nada.

## Criterios de aceptación

- [x] Una obra publicada trae su tarjeta, con enlace canónico y metadatos.
- [x] Responde sin sesión y se cachea en público.
- [x] Un borrador no tiene tarjeta, y no se distingue de una obra inexistente.
- [x] La tarjeta nombra el contenido y no a la persona.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
