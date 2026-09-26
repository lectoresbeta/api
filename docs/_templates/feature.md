---
id: FEAT-XXX-000
title: <Título en español>
context: <BoundedContext>
concept: <Concepto>
actors: [<Actor>]
spec_status: DRAFT
impl_status: TODO
priority: P2
sources:
  - _sources/use-cases.pdf#pN
endpoints: []
events: []
depends_on: []
updated: AAAA-MM-DD
---

# FEAT-XXX-000 — <Título>

## Resumen

Qué hace esta funcionalidad y por qué existe. Dos o tres frases.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|

## Precondiciones

Qué debe cumplirse antes de que la funcionalidad pueda ejecutarse.

## Reglas de negocio

- `RN-1`
- `RN-2`

Numeradas para poder citarlas desde el código, los tests y las revisiones.

## Flujo principal

1.
2.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta de API |
|---|---|---|

## Contrato de API

| Operación | Método y ruta | `operationId` | Documento |
|---|---|---|---|

La forma exacta de la petición y la respuesta está en `openapi/`. Aquí se documenta la
semántica, no el esquema.

## Eventos

**Publica**

| Evento | Cuándo | Payload relevante |
|---|---|---|

**Consume**

| Evento | Origen | Efecto |
|---|---|---|

## Efectos en créditos

Si la funcionalidad genera un hecho que `Credits` pueda interpretar, se indica aquí **el
hecho**, nunca el importe. El importe lo decide `Credits`.

## Modelo de datos afectado

Agregados, tablas nuevas o modificadas, índices necesarios.

## Diseño (Figma)

Enlace a la página o frame y notas de interpretación.

## Criterios de aceptación

- [ ] Afirmaciones verificables, no intenciones.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|

## Estado

**Especificación:** `DRAFT` — qué falta para llegar a `APPROVED`.

**Implementación:** `TODO`.

<!-- Si el estado es PARTIAL: qué parte está hecha y qué parte falta. -->
<!-- Si el estado es BLOCKED: qué lo bloquea exactamente y quién debe desbloquearlo. -->
