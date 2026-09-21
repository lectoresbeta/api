# Convenciones de la documentación

Estas reglas mantienen la documentación consistente y utilizable como fuente de verdad.

---

## 1. Idioma

- **La prosa se escribe en español**: descripciones, reglas de negocio, flujos, criterios
  de aceptación, preguntas abiertas.
- **Los identificadores técnicos se escriben en inglés** y se escriben exactamente como
  aparecerán en el código: bounded contexts, conceptos, entidades, value objects, eventos,
  comandos, endpoints, campos JSON, enums, tablas y columnas.
- Esto es coherente con la regla de `AGENTS.md`: *todo el código fuente debe estar en inglés*.
- El [glosario](glossary.md) fija la traducción oficial de cada término de producto.
  No se improvisan traducciones: si falta un término, se añade al glosario primero.

Ejemplo correcto:

> Un `Work` puede contener uno o varios `Chapter`. Cuando el autor publica la obra se emite
> el evento `WorkPublished`.

Ejemplo incorrecto:

> Una Obra puede contener uno o varios Fragmentos. Cuando el autor publica la obra se emite
> el evento `ObraPublicada`.

---

## 2. Nombres de ficheros y carpetas

- Carpetas y ficheros en **inglés, minúsculas y `kebab-case`**.
- Los documentos ordenados se prefijan con un número de dos dígitos: `01-overview.md`.
- Las fichas de funcionalidad usan el patrón: `FEAT-<CTX>-<NNN>-<slug-en-ingles>.md`.
- Los ADR usan: `NNNN-<slug-en-ingles>.md`, numeración correlativa desde `0001`.
- Los prefijos `_` (`_templates`, `_sources`, `_tools`) marcan carpetas de soporte que no
  son contenido de producto.

---

## 3. Identificadores de funcionalidad

Formato: `FEAT-<CTX>-<NNN>`

| Prefijo | Bounded context | Ámbito |
|---|---|---|
| `USR` | `User` | Cuenta, autenticación, perfil, página de autor, preferencias |
| `WRK` | `Work` | Obras, fragmentos, autoría, visibilidad, cuestionario |
| `RDG` | `Reading` | Acceso de lectores beta, grupos, writing buddies |
| `FBK` | `Feedback` | Comentarios, valoraciones, respuestas al cuestionario |
| `COM` | `Community` | Muro, publicaciones, reacciones, mensajes directos, rankings |
| `CRD` | `Credits` | Sistema de créditos |
| `NOT` | `Notification` | Notificaciones in-app y por email |

Reglas:

- El identificador **es inmutable**. Si una funcionalidad cambia de alcance se reescribe su
  ficha; si desaparece se marca `DEPRECATED`, pero el número **nunca se reutiliza**.
- La numeración es correlativa dentro de cada contexto y no implica orden de ejecución.
- Una funcionalidad que cruza contextos se asigna al contexto **donde se origina la acción
  del usuario**, y declara sus efectos en los demás mediante eventos.

---

## 4. Estados

Cada funcionalidad tiene **dos estados independientes**. Mezclarlos es el error más habitual:
una funcionalidad puede estar perfectamente especificada y sin implementar, o implementada a
medias sobre una especificación provisional.

### 4.1 Estado de especificación (`spec_status`)

| Valor | Significado |
|---|---|
| `PENDING` | Solo existe el enunciado en el registro maestro. No hay ficha. |
| `DRAFT` | Hay ficha, pero incompleta o sin contrastar con diseño/producto. |
| `REVIEW` | Ficha completa, pendiente de validación por producto. |
| `APPROVED` | Validada. Es contrato: implementar exactamente esto. |

### 4.2 Estado de implementación (`impl_status`)

| Valor | Significado |
|---|---|
| `TODO` | No hay código. |
| `IN_PROGRESS` | En desarrollo activo. |
| `PARTIAL` | Hay código en producción que cubre parte del alcance. La ficha debe indicar **qué falta**. |
| `DONE` | Implementada por completo, con tests y OpenAPI actualizado. |
| `BLOCKED` | No se puede avanzar. La ficha debe indicar **qué la bloquea**. |
| `DEFERRED` | Decidido conscientemente no hacerlo por ahora. |
| `DEPRECATED` | Existió o se planificó, y se ha retirado del alcance. |

### 4.3 Reglas de estado

- Solo se implementa lo que está en `spec_status: APPROVED`. Empezar sobre un `DRAFT` es
  aceptable únicamente si la ficha lo justifica de forma explícita.
- `DONE` exige cumplir la *Definition of done* de `AGENTS.md`: tests, PHPStan, PHP-CS-Fixer,
  migraciones, OpenAPI y documentación actualizados.
- `PARTIAL` y `BLOCKED` **obligan** a rellenar la sección correspondiente de la ficha. Un
  estado sin explicación no es información.
- El estado vive en el *front matter* de la ficha; el registro maestro lo replica para poder
  leerlo de un vistazo. `docs/_tools/check-docs.py` verifica que ambos coincidan.

---

## 5. Front matter

Toda ficha de funcionalidad empieza con un bloque YAML. Campos:

```yaml
---
id: FEAT-WRK-001              # obligatorio, inmutable
title: Crear obra con el editor
context: Work                 # bounded context propietario
concept: Manuscript           # concepto dentro del contexto (segundo nivel de src/)
actors: [Writer]              # roles del catálogo de product/02-actors-and-roles.md
spec_status: DRAFT
impl_status: TODO
priority: P1                  # P0 crítico | P1 alto | P2 medio | P3 bajo
sources:                      # trazabilidad al material de origen
  - _sources/use-cases.pdf#p1
  - figma:<url>
endpoints: [POST /works]      # operaciones de API implicadas
events: [WorkCreated]         # eventos publicados o consumidos
depends_on: [FEAT-USR-001]    # otras funcionalidades necesarias
updated: 2026-09-21
---
```

`priority` refleja importancia de producto, no orden de ejecución; el orden vive en el
roadmap.

---

## 6. Contratos de API

- `api/conventions/` define lo transversal: autenticación, errores, paginación, versionado,
  concurrencia, subida de ficheros. **No se repite en cada endpoint**: se enlaza.
- `api/endpoints/` describe cada operación: propósito, autorización, reglas de negocio,
  códigos de error específicos y efectos secundarios (eventos, créditos).
- `openapi/` contiene la forma exacta de peticiones y respuestas (esquemas, tipos, ejemplos).
- **No se duplica información entre markdown y OpenAPI.** El markdown explica *por qué* y
  *cuándo*; OpenAPI define *qué forma tiene*. Cada endpoint del markdown referencia su
  `operationId`.
- Todo cambio de contrato se hace en el mismo commit en ambos sitios.

---

## 7. Eventos

- Todo evento de integración está en [`events/README.md`](events/README.md) antes de usarse.
- Se nombran en pasado y describen un hecho consumado: `FeedbackSubmitted`, no
  `AddCreditsToUser`.
- El catálogo indica productor, consumidores, payload estable y garantías de entrega.

---

## 8. Estilo

- Frases cortas. Una idea por párrafo.
- Las reglas de negocio se numeran (`RN-1`, `RN-2`…) dentro de cada ficha para poder citarlas
  desde tests, código y revisiones.
- Los criterios de aceptación se escriben como afirmaciones verificables, no como intenciones.
- Las dudas no se ocultan: van a la sección **Preguntas abiertas** de la ficha. Una pregunta
  abierta documentada vale más que una suposición silenciosa.
- No se copia código en la documentación salvo que sea el contrato (payloads, nombres).
- Las tablas se prefieren a las listas cuando hay más de dos dimensiones que comparar.

---

## 9. Trazabilidad

Cada ficha declara su origen en `sources`. Los orígenes válidos son:

- `_sources/use-cases.pdf#pN` — documento de casos de uso v3.0, página N.
- `_sources/credit-system.pdf#pN` — documento del sistema de créditos, página N.
- `figma:<url>` — página o frame concreto de Figma.
- `decision:NNNN` — ADR que fija la decisión.
- `conversation:<fecha>` — acuerdo tomado fuera de documento. Debe resumirse en la ficha.

Si una regla de la ficha contradice el material de origen, la ficha manda **y lo dice
explícitamente**, indicando qué sustituye y por qué.

---

## 10. Mantenimiento

- Cambiar una funcionalidad implica actualizar su ficha, su fila en el registro y, si aplica,
  `api/`, `openapi/` y `events/`.
- `updated` se actualiza en cada cambio sustantivo del contenido, no en correcciones de estilo.
- `python3 docs/_tools/check-docs.py` debe pasar antes de dar por cerrado un cambio.
