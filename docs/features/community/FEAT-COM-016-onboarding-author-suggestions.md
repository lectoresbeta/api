---
id: FEAT-COM-016
title: Onboarding paso 3 — sugerencias de autores a seguir
context: Community
concept: Subscription
actors: [User]
spec_status: DRAFT
impl_status: TODO
priority: P1
sources:
  - figma:1800-13778 (1470:9640)
  - docs/ui/account-creation.md
endpoints: [GET /onboarding/author-suggestions, POST /authors/{userId}/subscription]
events: [AuthorSubscribed, OnboardingCompleted]
depends_on: [FEAT-USR-023, FEAT-COM-010]
updated: 2026-09-21
---

# FEAT-COM-016 — Onboarding paso 3: sugerencias de autores a seguir

## Resumen

Último paso del onboarding. Se proponen autores a los que seguir «en base a los intereses
que has elegido». El usuario puede seguir a los que quiera, o ninguno: el propio subtítulo
dice «Puedes hacerlo más tarde».

Es el único paso opcional del onboarding.

## Actores y autorización

| Actor | Puede | Condición |
|---|---|---|
| `User` | Ver sugerencias y seguir autores | Sesión iniciada y géneros ya elegidos |

## Reglas de negocio

- `RN-1` Las sugerencias se calculan a partir de los géneros elegidos en `FEAT-USR-023`.
- `RN-2` El paso es opcional: se puede completar el onboarding sin seguir a nadie.
- `RN-3` Un usuario no aparece entre sus propias sugerencias.
- `RN-4` No se sugiere a un autor al que ya sigue.
- `RN-5` Seguir desde aquí es exactamente la misma acción que `FEAT-COM-010`. No hay una
  suscripción «de onboarding» distinta.
- `RN-6` Seguir y dejar de seguir son reversibles en la misma pantalla: el botón alterna
  entre «Seguir» y «Siguiendo».
- `RN-7` Solo se sugieren cuentas activas y no eliminadas.
- `RN-8` Al terminar este paso el onboarding pasa a `COMPLETED` y se publica
  `OnboardingCompleted`.

## Datos de cada sugerencia

El diseño muestra por tarjeta:

| Dato | Origen | Nota |
|---|---|---|
| Avatar | `User` | |
| Nombre visible | `User` | El nombre **público**, no el privado del paso 1 (`OB-2`) |
| Número de seguidores | `Community` | Agregado |
| Número de publicaciones | `Community` | Agregado |
| Estado de seguimiento | `Community` | Para pintar «Seguir» o «Siguiendo» |

Los dos contadores son agregados sobre datos que crecen. Calcularlos con un `COUNT` por cada
tarjeta en cada carga del onboarding no escala: **corresponden a un read model** mantenido
por eventos.

## Flujo principal

1. El sistema calcula las sugerencias a partir de los géneros del usuario.
2. El usuario ve la lista y pulsa «Seguir» en los que le interesen.
3. Cada pulsación crea la suscripción de inmediato, sin esperar al final del paso.
4. El usuario pulsa «Siguiente».
5. El onboarding pasa a `COMPLETED`.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| No hay autores para esos géneros | **Sin definir** (`OB-6`). Hace falta un plan alternativo: autores más seguidos, o estado vacío | Pendiente |
| El usuario no sigue a nadie | El onboarding se completa igual (`RN-2`) | `200` |
| Seguir a alguien a quien ya sigue | Idempotente | `200` |
| Géneros sin elegir | Se rechaza | `409` con `code: ONBOARDING_STEP_OUT_OF_ORDER` |

El caso del catálogo vacío no es hipotético: **en una plataforma recién lanzada no habrá
autores que sugerir**, y ese es precisamente el momento en que todos los usuarios pasan por
esta pantalla.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Obtener sugerencias | `GET /onboarding/author-suggestions` | `listAuthorSuggestions` |
| Seguir a un autor | `POST /authors/{userId}/subscription` | `followAuthor` |
| Dejar de seguir | `DELETE /authors/{userId}/subscription` | `unfollowAuthor` |
| Completar onboarding | `POST /me/onboarding/complete` | `completeOnboarding` |

## Eventos

**Publica**

| Evento | Cuándo | Consumidores |
|---|---|---|
| `AuthorSubscribed` | El usuario sigue a un autor | `Notification` |
| `OnboardingCompleted` | Termina el paso 3 | `Notification`, read models |

**Consume**

| Evento | Origen | Efecto |
|---|---|---|
| `LiteraryPreferencesUpdated` | `User` | Permite calcular las sugerencias sin consultar a `User` |

`Community` no consulta la tabla de géneros de `User`: recibe el hecho y mantiene su propia
proyección. Es la frontera entre contextos.

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `author_subscription` | Nueva suscripción |
| `author_stats` (read model) | Contadores de seguidores y publicaciones |
| `author_genre` (read model) | Proyección de qué géneros escribe cada autor |

## Diseño (Figma)

`1470:9640`.

Ver [`../../ui/account-creation.md`](../../ui/account-creation.md).

## Criterios de aceptación

- [ ] Las sugerencias corresponden a los géneros elegidos por el usuario.
- [ ] El propio usuario nunca aparece entre sus sugerencias.
- [ ] Un autor ya seguido no aparece entre las sugerencias.
- [ ] Seguir a un autor desde aquí produce la misma suscripción que desde su perfil.
- [ ] Seguir dos veces al mismo autor no crea dos suscripciones.
- [ ] Se puede completar el onboarding sin seguir a nadie.
- [ ] Completar el paso publica `OnboardingCompleted` y deja el estado en `COMPLETED`.
- [ ] Los contadores no se calculan con un `COUNT` por tarjeta.
- [ ] Con cero autores sugeribles, la respuesta es válida y la pantalla puede seguir adelante.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| **OB-6** | ¿Cómo se ordenan las sugerencias y cuántas se devuelven? ¿Qué se muestra si no hay ninguna? | **Bloqueante** para implementar: sin criterio no hay consulta |
| OB-4 | ¿Falta un botón explícito de «Saltar»? | El diseño solo tiene «Atrás» y «Siguiente» |
| OB-12 | ¿A dónde lleva «Siguiente»: al Home o a un segundo onboarding de perfil? | La nota del propio Figma lo deja abierto |
| OB-2 | ¿Qué nombre se muestra en la tarjeta? | El del paso 1 es privado |
| S-1 | ¿Qué es una «publicación» en el contador: posts del muro, obras publicadas, o ambos? | Cambia el significado del dato |

## Estado

**Especificación:** `DRAFT`. `OB-6` es bloqueante: hay que definir el criterio de sugerencia
y el comportamiento con catálogo vacío, que será el caso habitual al principio.

**Implementación:** `TODO`.
