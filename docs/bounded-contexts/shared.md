# `Shared`

> No es un bounded context. Es el espacio para primitivos técnicos genuinamente genéricos.

## Qué puede vivir aquí

- Identificadores genéricos (generación y validación de UUID).
- Primitivas de paginación.
- Abstracción de reloj (`Clock`).
- Contratos de excepción comunes.
- Infraestructura genérica de eventos (envoltorio, publicación, serialización base).
- Utilidades genéricas de base de datos.
- Utilidades compartidas de test.
- Abstracciones de framework independientes de cualquier concepto de negocio.

## Qué NO puede vivir aquí

- **Lógica de negocio de ningún tipo.**
- Entidades o value objects con significado de producto (`Work`, `Credit`, `Feedback`…).
- Enums de negocio (`TextTier`, `PostType`, `BetaReaderAccessMode`…).
- Servicios compartidos entre contextos para evitar duplicar una regla.
- Modelos usados por más de un contexto.

## La prueba decisiva

> Si el concepto seguiría teniendo exactamente el mismo sentido en un proyecto que no fuera
> Lectores Beta, puede ir en `Shared`. Si no, pertenece a un bounded context.

`Shared` **no es el sitio donde dejar lo que no se sabe de quién es**. Si dos contextos
necesitan la misma regla de negocio, casi siempre significa una de dos cosas: que la
frontera está mal trazada, o que cada contexto necesita su propia versión del concepto.
Duplicar un enum en dos contextos es correcto cuando cada uno lo interpreta a su manera.

## Contenido previsto

| Elemento | Propósito |
|---|---|
| `Shared/Domain/ValueObject/Uuid` | Base de los identificadores |
| `Shared/Domain/Clock` | Tiempo inyectable y controlable en tests |
| `Shared/Domain/Pagination` | Criterios de paginación y resultado paginado |
| `Shared/Domain/Event/DomainEvent` | Contrato base de evento de dominio |
| `Shared/Infrastructure/Messenger` | Publicación y serialización genérica de eventos |
| `Shared/Infrastructure/Doctrine/Type` | Tipos personalizados de Doctrine (UUID, enums) |
| `Shared/Infrastructure/Http` | Traducción centralizada de excepciones a respuestas |
