# Bounded context: `<Name>`

> Estado: `DRAFT` | `REVIEW` | `APPROVED` — Prefijo de funcionalidades: `<CTX>`

## Responsabilidad

Una o dos frases: qué pregunta de negocio responde este contexto.

## Qué posee

- Modelo de negocio y sus invariantes.
- Persistencia propia.
- Casos de uso.

## Qué NO posee

Lista explícita de lo que podría parecer suyo y no lo es, con el contexto propietario.

## Conceptos (segundo nivel de `src/<Name>/`)

| Concepto | Responsabilidad |
|---|---|

## Agregados y modelo

| Agregado | Identidad | Invariantes principales |
|---|---|---|

### Value objects y enums

| Nombre | Valores / reglas |
|---|---|

## Casos de uso

| Caso de uso | Actor | Funcionalidad |
|---|---|---|

## Eventos publicados

| Evento | Cuándo | Consumidores | Payload |
|---|---|---|---|

## Eventos consumidos

| Evento | Origen | Efecto en este contexto |
|---|---|---|

## Endpoints

Enlace al documento de contratos en `api/endpoints/<name>.md`.

## Persistencia

Tablas principales, índices previstos y consideraciones.

## Reglas de negocio transversales

`RN-1`, `RN-2`… numeradas para poder citarlas desde tests y revisiones.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
