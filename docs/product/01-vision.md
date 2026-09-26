# Visión del producto

> Estado: `DRAFT` — pendiente de validar con producto y de completar con las páginas de Figma.

## Qué es Lectores Beta

Lectores Beta es una plataforma donde los escritores publican obras inéditas y reciben
**feedback crítico y estructurado** de lectores beta, antes de que la obra llegue a un
público general o a una editorial.

El problema que resuelve es concreto: un escritor sin círculo literario no tiene forma
fiable de saber si su texto funciona. Y un lector dispuesto a leer y criticar no tiene
incentivo para hacerlo con rigor ni de forma sostenida.

## Propuesta de valor

| Para | Que necesita | Lectores Beta le ofrece |
|---|---|---|
| Escritores | Feedback honesto y útil sobre obras no publicadas | Lectores beta reales, feedback dirigido mediante cuestionario, y prueba de autoría del texto |
| Lectores | Acceso temprano a obras y reconocimiento por su criterio | Catálogo de obras inéditas, créditos y visibilidad en los rankings por dar buen feedback |
| Ambos | Un entorno donde dar y recibir esté equilibrado | Sistema de créditos que hace que recibir feedback exija haberlo dado antes |

## Principios de producto

1. **Reciprocidad obligatoria.** Recibir feedback cuesta créditos; darlo los genera. Nadie
   extrae valor de la comunidad sin aportarlo. Es el mecanismo central del producto y la
   razón de que el sistema de créditos sea un bounded context aislado.
2. **El feedback se dirige, no se improvisa.** El autor adjunta un cuestionario a su obra
   para orientar la crítica hacia lo que realmente quiere saber.
3. **El autor controla quién lee.** Cada obra define su modalidad de acceso: abierta,
   bajo aprobación o solo por invitación.
4. **La obra inédita se protege.** El contenido no publicado nunca es accesible a quien no
   tiene acceso concedido, y el sistema genera registros de autoría verificables.
5. **La calidad se reconoce.** Los rankings y los créditos extra por feedback bien valorado
   premian al que aporta, no al que más publica.

## Alcance de esta documentación

Este repositorio es el **backend** de la plataforma: PHP, Symfony, PostgreSQL y RabbitMQ,
siguiendo DDD con bounded contexts aislados (ver [`AGENTS.md`](../../AGENTS.md)).

Expone una API HTTP consumida por el frontend. La documentación cubre:

- las funcionalidades que el backend debe soportar;
- el contrato de la API;
- el modelo de dominio y las reglas de negocio;
- la comunicación asíncrona entre contextos.

Queda fuera: la implementación del frontend, aunque sus pantallas (Figma) sean la fuente
para especificar las funcionalidades.

## Fuera de alcance por ahora

Decisiones que el material de partida no cubre y que **no se asumen**:

- monetización, suscripciones de pago o compra de créditos;
- publicación comercial o distribución de las obras;
- moderación automatizada de contenido;
- internacionalización de la plataforma más allá del español;
- aplicaciones móviles nativas.

Si alguna entra en alcance, se registra como ADR en [`../decisions/`](../decisions/).

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| V-1 | ¿Existe un rol de administración/moderación de la plataforma? | **Resuelta: sí**, y es un bounded context propio. `Moderation` está implementado: reclamaciones, cola con prioridad, moderadores, administradores, catálogo de sanciones y conversación con las partes (`FEAT-MOD-001` a `FEAT-MOD-012`) |
| V-2 | ¿Los créditos se podrán comprar en algún momento? | Condiciona el diseño del contexto `Credits` y su auditoría |
| V-3 | ¿La plataforma nace solo en español o multi-idioma? | Afecta a búsqueda, rankings y clasificación por extensión de texto |
| V-4 | ¿Hay obligación legal de conservar las obras tras eliminar la cuenta? | Afecta al borrado de cuenta y a la retención de datos |
