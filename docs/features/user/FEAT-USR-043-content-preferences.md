---
id: FEAT-USR-043
title: Preferencias de contenido sensible
context: User
concept: Preferences
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - conversation:2026-09-23 (filtros de contenido sensible en el perfil)
endpoints:
  - GET /me/content-preferences
  - PUT /me/content-preferences
events: [ContentPreferencesChanged]
depends_on: [FEAT-WRK-017]
updated: 2026-09-23
---

# FEAT-USR-043 — Preferencias de contenido sensible

## Resumen

El usuario elige **qué contenidos no quiere ver**. Las obras etiquetadas con algo que ha
excluido dejan de aparecerle.

Es la otra mitad de [`FEAT-WRK-017`](../work/FEAT-WRK-017-content-rating.md): el autor declara
qué hay, el lector decide qué quiere.

## Dónde se aplica

**En todas partes, no solo en el catálogo.** Si el filtro solo actuara en la búsqueda, el
contenido excluido aparecería igualmente en el muro, en las recomendaciones y en «También te
puede interesar», que es justo donde uno no lo espera.

| Superficie | Se filtra |
|---|---|
| Catálogo «Leer» | Sí |
| Recomendaciones de la Home | Sí |
| Muro | Sí |
| «También te puede interesar» | Sí |
| Perfil de un autor | Sí |
| **Enlace directo a la obra** | **No**: se muestra advertencia y se pide confirmación |

La última fila es la importante. Filtrar no es censurar: si alguien llega por un enlace
directo, se le avisa de lo que hay y decide. Ocultarlo sin explicación haría pensar que la
obra no existe.

## Reglas de negocio

- `RN-1` Las preferencias son **del usuario** y solo él las ve.
- `RN-2` El filtrado se aplica **en el backend**, no ocultando tarjetas en el cliente.
- `RN-3` Por defecto **no se filtra nada**, salvo lo que imponga la edad (`RN-4`).
- `RN-4` El contenido **no apto para menores** se filtra para quien no cumple la edad mínima
  **con independencia de sus preferencias**. Eso no es una preferencia, es una regla.
- `RN-5` Ante un **enlace directo**, no se oculta: se advierte y se pide confirmación.
- `RN-6` Excluir un contenido **no impide corregir** una obra ya aceptada: quien empezó,
  termina.
- `RN-7` Las preferencias **no se comparten con los autores**. Un autor no debe poder deducir
  cuánta gente ha excluido su obra.

`RN-2` no es una formalidad: un filtro de cliente significa que el contenido **viaja hasta el
navegador** de quien pidió no verlo. Para material sensible eso no sirve.

`RN-7` evita un efecto perverso: si el autor supiera cuánta audiencia pierde por etiquetar
bien, tendría un incentivo directo para etiquetar mal.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Consultar | `GET /me/content-preferences` | `getMyContentPreferences` |
| Modificar | `PUT /me/content-preferences` | `updateMyContentPreferences` |

Forman parte del contexto de sesión
([`FEAT-USR-027`](FEAT-USR-027-session-context.md)) solo para pintar estados; **el filtrado
real lo hace el servidor** en cada consulta.

## Criterios de aceptación

- [ ] El usuario puede excluir etiquetas del catálogo cerrado.
- [ ] El filtro se aplica en catálogo, muro, recomendaciones y perfiles ajenos.
- [ ] El contenido excluido no llega al cliente.
- [ ] Un enlace directo muestra advertencia y pide confirmación en vez de ocultar.
- [ ] El contenido no apto para menores se filtra por edad aunque el usuario no lo excluya.
- [ ] Un autor no puede saber cuántos usuarios han excluido su obra.
- [ ] Por defecto no se filtra nada.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **U-18** | ¿Dónde viven estas preferencias: en Configuración o en el perfil? | El usuario habló de «su perfil»; la pestaña Privacidad también encaja |
| U-19 | ¿Se recuerda la confirmación de un enlace directo, o se pregunta cada vez? | Preguntar siempre cansa; recordar contradice la exclusión |
| U-20 | ¿Afectan las preferencias a los resultados de búsqueda de autores? | Un autor no tiene etiquetas; sus obras sí |
| OB-7 | ¿Hay edad verificable? | Sin ella, `RN-4` no se puede aplicar de verdad |

## Estado

**Especificación:** `DRAFT`. Depende de que exista el catálogo de etiquetas (`W-20`).

**Implementación:** `TODO`.
