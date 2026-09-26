---
id: FEAT-USR-043
title: Preferencias de contenido sensible
context: User
concept: Preferences
actors: [User]
spec_status: APPROVED
impl_status: PARTIAL
priority: P1
sources:
  - conversation:2026-09-23 (filtros de contenido sensible en el perfil)
endpoints:
  - GET /me/content-preferences
  - PUT /me/content-preferences
events: [ContentPreferencesChanged]
depends_on: [FEAT-WRK-017]
updated: 2026-09-25
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

- [x] El usuario puede excluir etiquetas del catálogo cerrado.
- [ ] El filtro se aplica en catálogo, muro, recomendaciones y perfiles ajenos. **Solo el
  catálogo**: las otras tres pantallas no existen.
- [x] El contenido excluido no llega al cliente.
- [ ] Un enlace directo muestra advertencia y pide confirmación en vez de ocultar.
- [x] El contenido no apto para menores se filtra por edad aunque el usuario no lo excluya.
- [x] Un autor no puede saber cuántos usuarios han excluido su obra.
- [x] Por defecto no se filtra nada.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **U-18** | ¿Dónde viven estas preferencias: en Configuración o en el perfil? | El usuario habló de «su perfil»; la pestaña Privacidad también encaja |
| U-19 | ¿Se recuerda la confirmación de un enlace directo, o se pregunta cada vez? | Preguntar siempre cansa; recordar contradice la exclusión |
| U-20 | ¿Afectan las preferencias a los resultados de búsqueda de autores? | Un autor no tiene etiquetas; sus obras sí |
| OB-7 | ¿Hay edad verificable? | Sin ella, `RN-4` no se puede aplicar de verdad |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `PARTIAL` (2026-09-25). Las dos operaciones, el catálogo cerrado
comprobado al guardar y el filtrado en el catálogo de obras, que se aplica **sin que la
petición lo pida**: es lo que esta persona decidió hace tiempo en su configuración.

Lo que atraviesa la frontera es `ReaderContentPreferences`, un contrato publicado de `User`
que responde **una lista de códigos y nada más**. En particular no existe la pregunta al
revés —cuánta gente excluye una etiqueta—, que es `RN-7` hecha imposible en vez de prohibida.

Dos decisiones que la ficha no traía:

- **Cada contexto tiene su propia enumeración de etiquetas**, y no se comparte: en `Work` los
  cinco valores dicen qué contiene una obra y en `User` dicen qué no quiere ver alguien, que
  son preguntas distintas. Lo que no puede pasar es que **diverjan**, porque una divergencia
  no rompe nada visiblemente —deja una etiqueta que se puede declarar y no se puede excluir—,
  así que hay una prueba que las mira a la vez.
- **`ContentPreferencesChanged` no se publica.** Nadie lo escucha, y un hecho que nadie
  escucha es un contrato que hay que mantener a cambio de nada. Entra el día que algo tenga
  que reaccionar.

~~**Faltan** las tres pantallas que no existen —muro, recomendaciones y perfil de un autor—~~
— **parcialmente caducado** (revisado el 2026-09-26). Las recomendaciones existen
([`FEAT-COM-017`](../community/FEAT-COM-017-home-work-recommendations.md)) y aplican el filtro.

Quedan dos, y una de ellas probablemente no aplica: el **perfil de autor**, que sí lista obras
y debería heredarlo, y el **muro**, donde no está claro que tenga sentido — una publicación no
lleva etiquetas de contenido, así que no hay nada por lo que filtrarla.

Falta también el aviso con confirmación del **enlace directo** (`RN-5`), que espera a que
existan los enlaces públicos.
