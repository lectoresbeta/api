# Documentación de Lectores Beta

Esta carpeta es la **fuente de verdad** del producto y de la plataforma. Todo lo que
se implemente en `src/` debe estar antes descrito aquí: la funcionalidad, sus reglas
de negocio, su contrato de API y sus efectos sobre otros contextos.

Regla base del proyecto: **si no está documentado aquí, no se implementa**; y si se
implementa algo distinto de lo documentado, se corrige la documentación en el mismo
cambio.

Las reglas de arquitectura y de código viven en [`AGENTS.md`](../AGENTS.md) (raíz del
repositorio). Esta documentación no las repite: las aplica y las concreta.

---

## Cómo está organizada

| Carpeta | Qué contiene | Quién la consulta |
|---|---|---|
| [`product/`](product/) | Visión, actores, recorridos de usuario y modelo de dominio de alto nivel | Producto y desarrollo |
| [`features/`](features/) | **Registro maestro de funcionalidades** y una ficha por funcionalidad, con su estado | Todos |
| [`architecture/`](architecture/) | Decisiones estructurales: contextos, capas, mensajería, persistencia, seguridad | Desarrollo |
| [`bounded-contexts/`](bounded-contexts/) | Una ficha por bounded context: qué posee, qué expone, qué consume | Desarrollo |
| [`api/`](api/) | Convenciones transversales de la API y contratos de endpoints (semántica, permisos, errores) | Desarrollo y clientes |
| [`events/`](events/) | Catálogo de eventos de integración entre contextos | Desarrollo |
| [`integrations/`](integrations/) | Servicios externos: OAuth, email, almacenamiento, RabbitMQ | Desarrollo y operación |
| [`decisions/`](decisions/) | ADRs: decisiones arquitectónicas con su contexto y sus consecuencias | Desarrollo |
| [`ui/`](ui/) | Especificaciones de pantalla derivadas de Figma | Producto y desarrollo |
| [`_templates/`](_templates/) | Plantillas para crear documentos nuevos | Todos |
| [`_sources/`](_sources/) | Material de origen (PDFs iniciales) y su trazabilidad | Referencia |
| [`_tools/`](_tools/) | Scripts de validación de la documentación | Desarrollo |

La especificación OpenAPI (contrato máquina-legible) **no vive aquí**: vive en
[`openapi/`](../openapi/) en la raíz, según prescribe `AGENTS.md`. Los documentos de
`api/endpoints/` describen la semántica y enlazan cada operación por su `operationId`.

---

## Por dónde empezar

1. [`product/01-vision.md`](product/01-vision.md) — qué es Lectores Beta y qué problema resuelve.
2. [`product/02-actors-and-roles.md`](product/02-actors-and-roles.md) — quién usa la plataforma.
3. [`glossary.md`](glossary.md) — lenguaje ubicuo (ES ↔ EN). Imprescindible antes de nombrar nada.
4. [`features/README.md`](features/README.md) — qué hay que construir y en qué estado está.
5. [`architecture/01-overview.md`](architecture/01-overview.md) — cómo se construye.

---

## Estado del proyecto

El repositorio está en fase de **especificación**: todavía no hay código en `src/`.
El estado de implementación de todas las funcionalidades es `TODO` salvo indicación
contraria en el [registro maestro](features/README.md).

La documentación se está construyendo de forma incremental a partir de:

- los documentos de partida en [`_sources/`](_sources/) (casos de uso v3.0 y sistema de créditos);
- las páginas de Figma, que se irán incorporando como especificaciones en [`ui/`](ui/)
  y como fichas de funcionalidad detalladas en [`features/`](features/).

---

## Cómo contribuir a esta documentación

Antes de escribir, lee [`conventions.md`](conventions.md). En resumen:

- prosa en español, identificadores técnicos en inglés;
- toda funcionalidad nueva nace como fila en [`features/README.md`](features/README.md);
- cuando se detalla, se crea su ficha a partir de [`_templates/feature.md`](_templates/feature.md);
- cada ficha declara su estado de especificación y su estado de implementación;
- un cambio de API se refleja a la vez en `api/endpoints/` y en `openapi/`.

Validación local:

```bash
python3 docs/_tools/check-docs.py
```
