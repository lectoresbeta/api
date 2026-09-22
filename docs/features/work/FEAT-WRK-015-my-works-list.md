---
id: FEAT-WRK-015
title: Mis relatos — listado con filtros y ordenación
context: Work
concept: Catalog
actors: [Writer]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-22 (pestaña «Mis relatos»)
  - docs/ui/my-works.md
endpoints: [GET /me/works]
events: []
depends_on: [FEAT-WRK-016]
updated: 2026-09-22
---

# FEAT-WRK-015 — Mis relatos: listado con filtros y ordenación

## Resumen

Pestaña del perfil propio que lista las obras del autor, con filtros por estado y cuatro
criterios de ordenación.

## Filtros

| Chip | Devuelve |
|---|---|
| **Todas** | Todas las obras del autor |
| **En corrección** | Estado `IN_CORRECTION` |
| **Visibles** | Estado `VISIBLE` |
| **En borrador** | Estado `DRAFT` |

Los tres estados son excluyentes, así que los tres filtros **suman el total**: el perfil dice
«4 Relatos» y las pestañas muestran 1 + 2 + 1.

## Ordenación

| Opción | Criterio |
|---|---|
| **Más valorados** | Valoración de la obra. Qué métrica exactamente: `W-12` |
| **Más antiguos** | Fecha de creación, ascendente |
| **Más recientes** | Fecha de creación, descendente |
| **Más leídos** | Número de lecturas |

Los cuatro son **calculables sin ambigüedad**, a diferencia del «Más relevantes» del muro y
de los comentarios. Aquí no hace falta definir ninguna fórmula.

## Datos de cada tarjeta

| Dato | Origen |
|---|---|
| Portada, título, sinopsis, géneros | `Work` |
| Número de fragmentos | `Work` |
| Valoraciones | Agregado, alimentado por `Feedback` |
| Lecturas | Agregado. **Concepto sin definir** (`H-3`) |
| Tiempo de lectura | Derivado del número de palabras |
| **Estado** | `FEAT-WRK-016` |

La insignia de estado **solo se muestra al autor**: es información de gestión, no del
catálogo público.

## Reglas de negocio

- `RN-1` Devuelve **solo las obras del usuario autenticado**.
- `RN-2` Incluye los borradores, que en ningún otro listado aparecen.
- `RN-3` Nunca devuelve el contenido de las obras, solo su sinopsis.
- `RN-4` Se pagina.
- `RN-5` Funciona con la cuenta sin activar: es solo lectura.
- `RN-6` Los agregados de valoraciones y lecturas se sirven desde una proyección; **no se
  calculan con un `COUNT` por tarjeta**.
- `RN-7` La insignia de estado no se expone en los listados públicos de obras.

`RN-1` parece obvia y es la que hay que probar: un fallo aquí expone los borradores de otro
autor, que es obra inédita que nadie ha decidido enseñar.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Mis obras | `GET /me/works` | `listMyWorks` |

Parámetros: `status` para el filtro y `sort` para el orden, además de la paginación estándar
(`pagination.md`). Un `sort` no admitido devuelve `422`, no se ignora en silencio.

## Criterios de aceptación

- [ ] Devuelve solo las obras del usuario autenticado.
- [ ] **Nunca devuelve obras de otro autor, ni siquiera sus borradores.**
- [ ] El filtro «En borrador» devuelve las obras en `DRAFT`.
- [ ] Los tres filtros de estado suman el total de «Todas».
- [ ] «Más recientes» y «Más antiguos» ordenan por fecha en sentidos opuestos.
- [ ] «Más leídos» ordena por número de lecturas.
- [ ] No se devuelve el contenido de ninguna obra.
- [ ] El listado se pagina.
- [ ] Un criterio de orden no admitido devuelve `422`.
- [ ] Funciona con la cuenta sin activar.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| W-12 | ¿«Más valorados» usa `WorkRating` o los «me gusta»? La tarjeta muestra un corazón | Métrica distinta según la respuesta |
| H-3 | ¿Qué cuenta como «lectura»? | Concepto nuevo con coste de escritura en cada apertura |
| H-2 | ¿Qué es «1 / 22»? El primer número es siempre 1 | Si es progreso, hace falta seguimiento de lectura |
| W-13 | ¿Se conservan filtro y orden al volver a la pestaña? | Detalle de interfaz |
| W-16 | ¿Hay una vista equivalente para el perfil **público** de un autor? | Ahí no deberían aparecer borradores ni insignias de estado |

## Estado

**Especificación:** `DRAFT`. El listado está claro; dependen de `FEAT-WRK-016` los estados y
de `H-3` el contador de lecturas.

**Implementación:** `TODO`.
