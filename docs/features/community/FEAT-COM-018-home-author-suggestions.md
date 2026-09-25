---
id: FEAT-COM-018
title: Home — sugerencias de autores en el muro
context: Community
concept: Subscription
actors: [User]
spec_status: APPROVED
impl_status: PARTIAL
priority: P1
sources:
  - figma:1820-15883
  - docs/ui/home.md
endpoints: [GET /home/author-suggestions]
events: [AuthorSubscribed]
depends_on: [FEAT-COM-016, FEAT-COM-010]
updated: 2026-09-25
---

# FEAT-COM-018 — Home: sugerencias de autores en el muro

## Resumen

Bloque que aparece dentro del muro cuando el usuario **no sigue a ningún autor**:

> **Todavía no sigues a ningún autor**
> Empieza a seguir a autores de tu interés para ver sus publicaciones

Seguido de tarjetas de autor con botón «Seguir» y el enlace «Explorar más perfiles →».

## Por qué existe

Cierra el cabo suelto del onboarding. La nota de diseño lo dice con todas las letras:

> *«(Se salta en el onboarding de registro la parte de seguir a autores) → CTA en el "muro
> social" con sugerencias de autores a los que seguir.»*

El paso 3 del onboarding es opcional y además **se omite automáticamente** cuando no hay
autores suficientes (`FEAT-COM-016`). Sin este bloque, quien lo saltara se quedaría con un
muro pobre y sin una vía evidente de arreglarlo.

## Qué compone el muro de quien no sigue a nadie

**Decidido** (`H-8`). Cuatro fuentes, y ninguna es «nada»:

| Fuente | Qué aporta |
|---|---|
| **Publicaciones públicas** | Actividad real de la plataforma, con audiencia `EVERYONE` |
| **Sugerencias de autores** | A quién seguir, que es lo que resuelve el problema de raíz |
| **Publicaciones de la plataforma** | Anuncios, novedades, contenido editorial propio |
| **Sugerencias de corrección** | Capítulos que este usuario podría corregir ahora |

La cuarta es la que más valor tiene y la que un muro social no tendría: **le ofrece trabajo**,
que es la única forma de ganar créditos. Sale del mismo orden del catálogo
([`decision:0008`](../../decisions/0008-catalogue-ordering.md)), así que no hay nada nuevo que
calcular.

### La cuenta de la plataforma es un concepto nuevo

«Publicaciones de la plataforma» implica que **existe un emisor institucional** que publica en
el muro. No es un usuario con nombre de persona: es la plataforma hablando.

Eso plantea preguntas que no estaban en ninguna ficha (`H-10`): quién puede publicar en su
nombre —previsiblemente un `Admin`—, si esas publicaciones admiten comentarios y valoraciones,
y si se pueden silenciar.

Conviene tratarlas como **un tipo de publicación propio** y no como una cuenta normal con un
avatar bonito: de lo contrario alguien acabará pudiendo seguirla, bloquearla o denunciarla, y
ninguna de las tres cosas tiene sentido.

## Reglas de negocio

- `RN-1` El bloque aparece **solo si el usuario no sigue a ningún autor**.
- `RN-2` Es **la misma funcionalidad** que el paso 3 del onboarding: mismas tarjetas, mismo
  criterio de sugerencia, misma acción de seguir, misma cadena de relleno. Solo cambia dónde
  se pinta.
- `RN-3` En consecuencia se sirve desde el mismo caso de uso que `FEAT-COM-016`. **No se
  duplica la lógica de sugerencia.**
- `RN-4` Seguir a un autor desde aquí es exactamente `FEAT-COM-010`.
- `RN-5` Si tampoco hay autores sugeribles, el bloque no se muestra: no se sustituye por un
  mensaje vacío que no ofrece salida.
- `RN-6` Seguir a alguien hace desaparecer el bloque, porque deja de cumplirse `RN-1`.

`RN-3` es la razón de que esta ficha sea corta: si se implementa bien, casi todo es
reutilización.

## Diferencias con el paso 3 del onboarding

| | Onboarding (`FEAT-COM-016`) | Home (esta ficha) |
|---|---|---|
| Cuándo | Una vez, al registrarse | Siempre que no siga a nadie |
| Sitio | Pantalla completa | Bloque dentro del muro |
| Navegación | «Atrás», «Saltar», «Siguiente» | «Explorar más perfiles →» |
| Si no hay candidatos | Se omite el paso | No se muestra el bloque |

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Sugerencias para la Home | `GET /home/author-suggestions` | `listHomeAuthorSuggestions` |
| Seguir a un autor | `PUT /users/{userId}/subscription` | `subscribeToAuthor` |

Podría reutilizarse `GET /onboarding/author-suggestions`, pero conviene separar las rutas: el
contexto de uso es distinto y es previsible que el criterio o el tamaño de la lista diverjan.
Ambas delegan en el mismo caso de uso.

## Criterios de aceptación

- [x] El bloque aparece cuando el usuario no sigue a ningún autor.
- [x] El bloque desaparece en cuanto sigue a uno.
- [x] Las sugerencias coinciden con las del onboarding para el mismo usuario y momento.
- [x] La lógica de sugerencia está implementada una sola vez, no duplicada.
- [x] Sin candidatos, el bloque no se muestra y el muro se pinta sin él.
- [x] Seguir desde aquí produce la misma suscripción que desde el perfil del autor.
- [x] El bloque no se muestra a quien ya sigue a alguien, aunque su muro esté vacío.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| ~~H-8~~ | ¿Qué compone el muro de quien no sigue a nadie? | Define si el muro es cronológico por seguidos o algorítmico |
| ~~S-2~~ | ¿Cuántas sugerencias se muestran aquí? | Resuelta: cuatro, frente a las diez del onboarding |
| S-3 | ¿A dónde lleva «Explorar más perfiles»? | Pantalla de descubrimiento sin diseñar |
| OB-6 | Criterio de orden de las sugerencias | Compartido con `FEAT-COM-016` |

## Estado

**Especificación:** `APPROVED` (2026-09-24). `H-8` resuelta: cuatro fuentes, incluidas sugerencias de
corrección. Queda `H-10`, la cuenta institucional, que se registra aparte.

**Implementación:** `PARTIAL` (2026-09-25). El bloque, sobre **el mismo caso de uso** que el
paso 3: lo único propio es la ruta, cuántas tarjetas caben y la condición de no seguir a nadie.
Hay una prueba dedicada a que las dos pantallas respondan lo mismo para la misma persona en el
mismo momento, que es la forma de notar el día que alguien duplique el criterio.

**Falta** lo que la ficha llama las otras dos fuentes del muro de quien no sigue a nadie: las
**publicaciones de la plataforma** —que necesitan la cuenta institucional, `H-10`, y son
`FEAT-COM-038`— y las **sugerencias de corrección**, que son la fuente con más valor y la que
un muro social no tendría, porque ofrece trabajo. Hoy el muro de esa persona trae lo público,
que es la primera fuente, y este bloque, que es la segunda.
