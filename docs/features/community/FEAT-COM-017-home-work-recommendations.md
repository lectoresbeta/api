---
id: FEAT-COM-017
title: Home — carrusel de obras recomendadas
context: Community
concept: Recommendation
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P1
sources:
  - figma:1800-14717
  - docs/ui/home.md
endpoints:
  - listRecommendedWorks
events: []
depends_on: [FEAT-USR-023, FEAT-WRK-012, FEAT-CRD-013]
updated: 2026-09-25
---

# FEAT-COM-017 — Home: carrusel de obras recomendadas

## Resumen

Lo primero que ve el usuario al entrar: un carrusel de obras sugeridas a partir de los
géneros que eligió en el onboarding. Es la vía principal para que empiece a leer y, por
tanto, para que gane créditos y entre en el ciclo de reciprocidad del producto.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Ver sus recomendaciones | Sesión iniciada. Funciona con la cuenta sin activar: es solo lectura |

## Datos de cada tarjeta

| Dato | Origen | Nota |
|---|---|---|
| Título | `Work` | |
| Sinopsis | `Work` | Truncada a 280 caracteres en el borde HTTP, no en la consulta: cuánto cabe es cosa de la pantalla |
| Estado | `Work` | Siempre `IN_CORRECTION` |
| Capítulos | `Work` | |
| Tiempo de lectura | Derivado de `wordCount` | 200 palabras por minuto (`FEAT-WRK-013`). Resuelve `R-1` |
| Géneros | `Work` | |
| Clasificación y advertencias | `Work` | `adultsOnly` y lo que la obra declara contener |
| Créditos | `Credits`, vía el catálogo | La insignia de `FEAT-CRD-013`, ya implementada |
| Correcciones recibidas | `Feedback`, vía el catálogo | |

**«Vistas» no está, y es deliberado** (`H-3`). Sigue sin definirse qué cuenta como una vista,
y cuesta una escritura en cada lectura: inventarla ahora sería peor que no tenerla.

**La portada tampoco**: hoy una obra no tiene portada en el modelo. Cuando la tenga, entra
aquí sin cambiar nada más.

**«1 / 1» se sirve como `chapterCount`** y no como progreso: el seguimiento de lectura no
existe (`H-2`).

## Reglas de negocio

- `RN-1` Las recomendaciones se basan en los géneros elegidos en `FEAT-USR-023`.
- `RN-2` Solo se recomiendan obras **visibles y abiertas a lectores beta**. Una obra `HIDDEN`
  o `PRIVATE` no aparece jamás: sería filtrar contenido inédito.
- `RN-3` No se recomiendan obras propias del usuario.
- `RN-4` No se recomiendan obras de autores con la cuenta sin activar (`FEAT-USR-025`), que
  no pueden recibir comentarios.
- `RN-5` El tiempo de lectura se **deriva** del número de palabras con un ritmo de lectura
  fijo y documentado. No lo introduce el autor.
- `RN-6` La tarjeta **nunca incluye contenido de la obra**, solo su sinopsis.
- `RN-7` `Community` no consulta las tablas de `Work` ni de `Credits`. **Pero tampoco las
  proyecta**: pregunta por el contrato publicado `RecommendedWorks`. Ver más abajo.
- `RN-8` Solo obras **abiertas a corrección** (`IN_CORRECTION`). El carrusel existe para que
  alguien empiece a corregir, y una obra que no lo admite ocupa el sitio más valioso de la
  pantalla sin llevar a ninguna parte.
- `RN-9` **Funciona con la cuenta sin activar.** Es solo lectura, y lo que `FEAT-USR-025`
  restringe es escribir: quien acaba de registrarse tiene que poder ver qué hay antes de
  confirmar su correo.

`RN-2` y `RN-6` son las dos que protegen el activo del producto. Un fallo aquí expone obra
inédita a quien no tiene acceso concedido.

## Cuando no hay recomendaciones

Mismo problema que el paso 3 del onboarding: al principio no habrá catálogo.

Cadena de relleno, igual que en `FEAT-COM-016`:

1. obras de los géneros elegidos, ordenadas por **la fórmula del catálogo**
   ([`decision:0008`](../../decisions/0008-catalogue-ordering.md): capacidad × desatención ×
   frescura). Resuelve `H-9`, y lo hace sin inventar un segundo criterio que pudiera
   contradecir al del catálogo. Su propiedad es justo la que el carrusel necesita: **aparecer
   arriba consume lo que te puso arriba**, así que las correcciones se reparten en vez de
   concentrarse en pocos textos;
2. si no se alcanza el mínimo, obras recientes de cualquier género;
3. si aun así no hay ninguna, **la sección no se muestra** y la Home empieza directamente
   por el muro.

La decisión la toma el servidor, con el mismo patrón `shouldDisplay` que `FEAT-COM-016`: la
regla vive en un solo sitio.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Obras recomendadas | `GET /api/v1/home/recommended-works` | `listRecommendedWorks` |

Respuesta con `shouldDisplay`, `reason` y la lista de tarjetas. `reason` tiene un solo valor,
`NOTHING_TO_CORRECT_YET`: al llegar ahí ya se ha mirado el catálogo entero, así que decir que
el problema son los géneros de esa persona sería culparla de una plataforma vacía.

## Cómo cruza el límite entre contextos

**`RN-7` se cumple por el lado que importa y se incumple por el que no.** La ficha pedía una
proyección de obras dentro de `Community`, alimentada por eventos. No se ha hecho, y conviene
decir por qué.

El reparto que sí se ha hecho:

```text
Community  ──  qué le gusta a esta persona   (proyección propia, FEAT-COM-016)
Work       ──  qué obras merece la pena      (contrato RecommendedWorks)
```

Una proyección de obras aquí habría significado **reescribir en `Community` la regla de
visibilidad más peligrosa del backend** —cinco puertas, clasificación por edad, advertencias
excluidas— y **copiar la fórmula de reparto** de
[`decision:0008`](../../decisions/0008-catalogue-ordering.md) para poder ordenar. Dos copias
de cada una, y la que se queda atrás es la que enseña obra inédita a quien no debe.

Lo que sí se proyecta es lo que de verdad es de aquí: los géneros de cada persona, que ya
llegaban por `LiteraryPreferencesUpdated` desde `FEAT-COM-016`.

**La edad y lo que el lector excluye los pregunta `Community` y los pasa al contrato.** No es
comodidad: si los preguntara la implementación de `RecommendedWorks`, un contrato estaría
llamando al contrato de otro contexto mientras responde, que es lo que
[`decision:0015`](../../decisions/0015-work-and-reading-ask-each-other.md) prohíbe. Es el
mismo patrón que `ReadableChapters` en `FEAT-COM-036`.

## Eventos consumidos

`LiteraryPreferencesUpdated`, de `User`, que ya se consumía desde `FEAT-COM-016`. Ninguno
más: lo que antes iban a ser cinco suscripciones lo resuelve un contrato.

## Criterios de aceptación

- [x] Las obras recomendadas pertenecen a los géneros elegidos por el usuario.
- [x] Nunca se recomienda una obra `HIDDEN` ni una obra `PRIVATE`.
- [x] Nunca se recomiendan obras propias.
- [x] La respuesta no incluye el contenido de ninguna obra, solo su sinopsis.
- [x] Sin obras recomendables, la respuesta es `200` con `shouldDisplay: false`.
- [x] El tiempo de lectura se deriva del número de palabras, no lo fija el autor.
- [x] `Community` no consulta tablas de `Work` ni de `Credits`.
- [x] La Home carga aunque la insignia de créditos no esté disponible.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| H-2 | ¿«1 / 1» es progreso de lectura o solo el total de fragmentos? | Se sirve `chapterCount`. Si es progreso, hace falta seguimiento de lectura, que no existe |
| H-3 | ¿Qué cuenta como «vista» y quién la registra? | **Fuera de esta versión.** Cuesta una escritura en cada lectura y sigue sin semántica |
| H-4 | ¿Cómo funciona «Solicitar otro relato»? ¿Se descarta de forma permanente? | Funcionalidad sin documentar |
| H-9 | ¿Se excluyen obras ya leídas o ya comentadas? | **Resuelta en parte:** se ordena por la fórmula del catálogo, que hunde sola lo ya corregido. Excluirlas del todo exigiría que `Work` supiera quién ha leído qué |
| H-1 | ¿Qué significa la insignia de créditos? | **Resuelta:** `FEAT-CRD-013`, implementada. Es el mínimo de lo que pagan sus capítulos corregibles |
| R-1 | ¿Qué ritmo de lectura se usa para el tiempo estimado? | **Resuelta:** 200 palabras por minuto (`FEAT-WRK-013`) |

## Estado

**Especificación:** `APPROVED` (2026-09-25). Las tres que bloqueaban están resueltas: el orden
es la fórmula del catálogo, la insignia es `FEAT-CRD-013` y las vistas quedan fuera con su
motivo escrito.

**Implementación:** `DONE`.
