# 0001 — La documentación de `docs/` es la fuente de verdad del producto

- **Estado:** Aceptada
- **Fecha:** 2026-09-21
- **Afecta a:** todo el proyecto

## Contexto

El proyecto arranca sin código. El material de partida son dos PDFs: los casos de uso v3.0 y
el sistema de créditos. Son útiles pero incompletos: describen funcionalidades sin reglas de
negocio detalladas, sin contratos de API, sin modelo de datos y con preguntas explícitamente
abiertas ("hay que considerar en qué situaciones se crea un registro cifrado").

Las funcionalidades se irán detallando de forma incremental a partir de las páginas de Figma.
Sin un sitio único donde converjan diseño, reglas y contratos, la especificación quedaría
repartida entre PDFs, Figma y conversaciones, y el código acabaría siendo la única
descripción real del sistema.

## Decisión

`docs/` es la fuente de verdad del producto y de la plataforma. Toda funcionalidad se
especifica ahí antes de implementarse, con su contrato de API y su estado.

En concreto:

- el registro maestro de [`docs/features/README.md`](../features/README.md) define el alcance;
- ninguna funcionalidad se implementa sin ficha en `spec_status: APPROVED`;
- el contrato de API vive en `docs/api/` (semántica) y `openapi/` (esquemas);
- las decisiones no obvias se registran como ADR;
- si el código y la documentación divergen, se corrige la documentación en el mismo cambio.

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| Documentación generada desde el código | Nunca se desincroniza | Solo documenta lo ya construido | No sirve para especificar lo que todavía no existe, que es justo lo que hace falta ahora |
| Especificación en una herramienta externa (Notion, Confluence) | Más cómoda para producto | Se separa del código, se revisa aparte, se desincroniza | La documentación debe versionarse y revisarse con el mismo diff que el código |
| Solo OpenAPI como contrato | Un único artefacto | No captura reglas de negocio, flujos ni preguntas abiertas | El contrato de API es una parte pequeña de lo que hay que especificar |
| Documentar sobre la marcha, mientras se implementa | Más rápido al principio | Las decisiones se toman implícitamente en el código | El sistema de créditos y la autorización tienen demasiadas decisiones abiertas para resolverlas escribiendo código |

## Consecuencias

**Positivas**

- Las decisiones de producto se toman antes de escribir código, no durante.
- Las preguntas abiertas son visibles en lugar de convertirse en suposiciones silenciosas.
- El registro de estado da una respuesta única a "qué falta".
- La documentación se revisa en el mismo pull request que el código.

**Negativas**

- Escribir la ficha antes de implementar alarga el arranque de cada funcionalidad.
- La documentación puede quedarse obsoleta si no se mantiene la disciplina.
- Exige actualizar tres sitios coordinadamente al cambiar la API.

**Coste de revertirla**

Bajo al principio, alto después. Abandonar la disciplina a mitad deja una documentación
parcialmente cierta, que es peor que no tener ninguna: se confía en ella y engaña.

## Cumplimiento

- `python3 docs/_tools/check-docs.py` verifica la coherencia entre fichas y registro.
- La *Definition of done* de `AGENTS.md` ya exige actualizar `docs/` y OpenAPI.
- En revisión: un cambio de comportamiento sin cambio de documentación se rechaza.
