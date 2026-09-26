# Arquitectura — visión general

> Las reglas normativas de arquitectura están en [`AGENTS.md`](../../AGENTS.md).
> Esta carpeta **no las repite**: las concreta para Lectores Beta y documenta las
> decisiones propias del proyecto.

## Stack

| Elemento | Tecnología |
|---|---|
| Lenguaje | PHP |
| Framework | Symfony |
| Base de datos | PostgreSQL |
| ORM | Doctrine |
| Mensajería | Symfony Messenger sobre RabbitMQ |
| Contrato de API | OpenAPI (`openapi/` en la raíz) |
| Análisis estático | PHPStan (nivel 7 como base) |
| Estilo | PHP-CS-Fixer (convenciones Symfony) |

## Forma del sistema

```text
                     ┌──────────────┐
   Frontend  ───────▶│  API HTTP    │
   (fuera de         │  Symfony     │
    este repo)       └──────┬───────┘
                            │
                    Casos de uso (Application)
                            │
                    Modelo de negocio (Domain)
                            │
              ┌─────────────┴─────────────┐
              ▼                           ▼
       ┌────────────┐              ┌────────────┐
       │ PostgreSQL │              │  RabbitMQ  │
       └────────────┘              └─────┬──────┘
                                         │ eventos de integración
                                         ▼
                              Consumidores en otros
                              bounded contexts
```

## Las tres decisiones estructurales

1. **Bounded contexts aislados.** El primer nivel de `src/` son fronteras de negocio, no
   capas. Ningún contexto conoce las clases internas de otro.
2. **Comunicación asíncrona por defecto.** Lo que cruza una frontera es un evento de
   integración por RabbitMQ, no una llamada a un servicio.
3. **`Credits` es intocable desde fuera.** Ningún contexto calcula, suma ni resta créditos.
   Publican hechos; `Credits` decide. Es una regla dura, no una preferencia.

## Estructura de `src/`

```text
src/
    <BoundedContext>/
        <Concept>/
            Domain/
            Application/
            Infrastructure/
```

El **concepto de negocio es el segundo nivel**, antes que las capas. La única excepción es
`src/<BoundedContext>/Infrastructure/routes.yaml`, que declara las rutas del contexto
([`decision:0011`](../decisions/0011-route-files-live-inside-their-context.md)): esa carpeta
no contiene código, y hay un test que lo comprueba.

Ejemplo:

```text
src/
    Work/
        Manuscript/
            Domain/
            Application/
            Infrastructure/
        Chapter/
            Domain/
            Application/
            Infrastructure/
        Questionnaire/
            ...
```

## Índice

| Documento | Contenido |
|---|---|
| [02-bounded-contexts.md](02-bounded-contexts.md) | Qué contextos existen, por qué, y el mapa de relaciones |
| [03-layers-and-dependencies.md](03-layers-and-dependencies.md) | Reparto por capas y control automático de dependencias |
| [04-cross-context-communication.md](04-cross-context-communication.md) | Eventos, Messenger, RabbitMQ, fiabilidad |
| [05-persistence-and-data.md](05-persistence-and-data.md) | PostgreSQL, Doctrine, migraciones, esquemas |
| [06-security-and-authorization.md](06-security-and-authorization.md) | Autenticación, autorización por recurso, protección del contenido inédito |
| [07-observability-and-operations.md](07-observability-and-operations.md) | Logs, métricas, entornos, despliegue |
