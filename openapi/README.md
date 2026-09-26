# Especificación OpenAPI

Contrato máquina-legible de la API. Ubicación prescrita por [`AGENTS.md`](../AGENTS.md).

```text
openapi/
    openapi.yaml    Documento raíz: info, servers, security, componentes comunes
    paths/          Un fichero por área, referenciado desde openapi.yaml
    schemas/        Esquemas de recurso reutilizables
```

## Relación con `docs/`

| Aquí | En `docs/api/` |
|---|---|
| Forma exacta de peticiones y respuestas | Semántica, autorización, reglas y efectos |
| Tipos, enums, códigos, ejemplos | Por qué existe la operación y cuándo se usa |

No se duplica información entre ambos. Cada operación documentada en
`docs/api/endpoints/` referencia su `operationId` de aquí.

## Reglas

- Toda operación tiene `operationId` único, en `camelCase`.
- Los enums usan exactamente los valores del [glosario](../docs/glossary.md).
- Toda operación declara sus respuestas de error con el esquema `Problem`.
- Una operación solo se añade cuando su funcionalidad está en `spec_status: APPROVED`.
- Todo cambio de la API se hace aquí y en `docs/api/` en el mismo commit.

## Estado

Esqueleto: componentes comunes definidos, `paths` vacío. Las rutas se incorporan conforme
se aprueban las funcionalidades.
