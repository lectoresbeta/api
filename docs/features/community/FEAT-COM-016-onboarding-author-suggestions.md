---
id: FEAT-COM-016
title: Onboarding paso 3 — sugerencias de autores a seguir
context: Community
concept: Subscription
actors: [User]
spec_status: APPROVED
impl_status: TODO
priority: P1
sources:
  - figma:1800-13778 (1470:9640)
  - docs/ui/account-creation.md
endpoints: [GET /onboarding/author-suggestions, POST /authors/{userId}/subscription]
events: [AuthorSubscribed, OnboardingCompleted]
depends_on: [FEAT-USR-023, FEAT-COM-010]
updated: 2026-09-24
---

# FEAT-COM-016 — Onboarding paso 3: sugerencias de autores a seguir

## Resumen

Último paso del onboarding. Se proponen autores a los que seguir «en base a los intereses
que has elegido». El usuario puede seguir a los que quiera, o ninguno: el propio subtítulo
dice «Puedes hacerlo más tarde».

Es el único paso opcional del onboarding, y el único que **puede no llegar a mostrarse**:
en una plataforma recién lanzada no habrá autores que sugerir, y ese es justamente el
momento en que todos los usuarios pasan por aquí.

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
- `RN-7` Solo se sugieren cuentas **activadas** (`AccountStatus: ACTIVE`) y no eliminadas.
  Una cuenta sin activar no puede publicar, así que sugerir seguirla no tendría sentido.
- `RN-8` Al terminar este paso el onboarding pasa a `COMPLETED` y se publica
  `OnboardingCompleted`.
- `RN-9` Si no hay sugerencias suficientes, **el paso se omite entero**. Ver la sección
  siguiente.
- `RN-10` La decisión de mostrar u omitir el paso **la toma el servidor**, no el cliente. El
  frontend se limita a obedecer el campo `shouldDisplay` de la respuesta.

`RN-10` evita que la regla se duplique: si el cliente decidiera «si vienen menos de tres, no
lo muestro», habría dos sitios donde cambiarla y uno se quedaría atrás.

## Datos de cada sugerencia

El diseño muestra por tarjeta:

| Dato | Origen | Nota |
|---|---|---|
| Avatar | `User` | |
| Nombre visible | `User` | El `name` del paso 1 del onboarding, que es público (`FEAT-USR-022`) |
| Número de seguidores | `Community` | Agregado |
| Número de publicaciones | `Community` | Agregado |
| Estado de seguimiento | `Community` | Para pintar «Seguir» o «Siguiendo» |

Los dos contadores son agregados sobre datos que crecen. Calcularlos con un `COUNT` por cada
tarjeta en cada carga del onboarding no escala: **corresponden a un read model** mantenido
por eventos.

## Cuando no hay autores suficientes

El caso normal durante los primeros meses de vida de la plataforma.

### Umbral

`MIN_SUGGESTIONS` = **3**. Por debajo de tres tarjetas la pantalla no aporta valor: no se
percibe como una recomendación, sino como un catálogo vacío.

Es un parámetro de configuración, no una constante repartida por el código.

### Cadena de relleno

El sistema construye la lista en tres tramos, en este orden, hasta llegar al máximo:

| # | Origen | Criterio |
|---|---|---|
| 1 | Autores que publican en los géneros elegidos | Ordenados por número de obras publicadas en esos géneros y, a igualdad, por número de seguidores |
| 2 | Si no se alcanza `MIN_SUGGESTIONS`: autores más seguidos de la plataforma, sin filtrar por género | Ordenados por número de seguidores. Se marcan con `matchedGenres: []` |
| 3 | Si aun así no se alcanza `MIN_SUGGESTIONS` | **El paso se omite** |

El tramo 2 es deliberado: es preferible proponer autores populares aunque no encajen con los
géneros que mostrar una pantalla casi vacía. La respuesta indica qué géneros han motivado
cada sugerencia, de modo que la interfaz puede matizar el texto cuando no hay coincidencia.

### Omitir el paso

Si tras la cadena de relleno hay menos de `MIN_SUGGESTIONS` candidatos:

1. `GET /onboarding/author-suggestions` devuelve `shouldDisplay: false`,
   `reason: NOT_ENOUGH_AUTHORS` y una lista vacía.
2. El frontend **no muestra el paso 3** y completa el onboarding directamente.
3. El estado pasa a `COMPLETED` y se publica `OnboardingCompleted` igual que si el usuario
   hubiera pasado por la pantalla.
4. El usuario llega al Home, que se presenta en modo vacío —la propia nota del diseño lo
   contempla: *«si se salta el seguir usuarios sería en modo empty screen»*.

El stepper debe reflejar dos pasos en lugar de tres cuando esto ocurre. Es un detalle de
interfaz, pero la información la da el backend: el frontend no puede saberlo antes.

### Respuesta

```json
{
  "shouldDisplay": true,
  "reason": null,
  "suggestions": [
    {
      "userId": "0192f8a1-...",
      "displayName": "Juanjo Estévez",
      "avatarUrl": "...",
      "followerCount": 2000,
      "publicationCount": 48,
      "matchedGenres": ["FANTASY", "ADVENTURE"],
      "following": false
    }
  ]
}
```

Con `shouldDisplay: false`, `reason` toma uno de estos valores:

| `reason` | Cuándo |
|---|---|
| `NOT_ENOUGH_AUTHORS` | No hay candidatos suficientes en toda la plataforma |
| `ALREADY_FOLLOWING_ALL` | El usuario ya sigue a todos los candidatos posibles |

## Flujo principal

1. El sistema calcula las sugerencias a partir de los géneros del usuario.
2. Si no alcanza `MIN_SUGGESTIONS`, devuelve `shouldDisplay: false` y el paso se omite.
3. El usuario ve la lista y pulsa «Seguir» en los que le interesen.
4. Cada pulsación crea la suscripción de inmediato, sin esperar al final del paso.
5. El usuario pulsa «Siguiente», o «Saltar» si prefiere no seguir a nadie.
6. El onboarding pasa a `COMPLETED`.

## Flujos alternativos y errores

| Caso | Comportamiento | Respuesta |
|---|---|---|
| No hay autores para esos géneros, pero sí en la plataforma | Se rellena con los más seguidos, marcados con `matchedGenres: []` | `200` |
| No hay candidatos suficientes en toda la plataforma | `shouldDisplay: false`, `reason: NOT_ENOUGH_AUTHORS`. El paso se omite | `200` |
| El usuario ya sigue a todos los candidatos | `shouldDisplay: false`, `reason: ALREADY_FOLLOWING_ALL` | `200` |
| El usuario no sigue a nadie | El onboarding se completa igual (`RN-2`) | `200` |
| Seguir a alguien a quien ya sigue | Idempotente | `200` |
| Géneros sin elegir | Se rechaza | `409` con `code: ONBOARDING_STEP_OUT_OF_ORDER` |

**Ningún caso de lista corta o vacía es un error.** Todos devuelven `200`: la ausencia de
autores es un estado normal de la plataforma, no un fallo.

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
- [ ] Con cero autores en la plataforma, la respuesta es `200` con `shouldDisplay: false` y `reason: NOT_ENOUGH_AUTHORS`.
- [ ] Con dos autores en la plataforma, el paso se omite: dos está por debajo de `MIN_SUGGESTIONS`.
- [ ] Con cinco autores pero ninguno de los géneros elegidos, se devuelven los más seguidos con `matchedGenres` vacío.
- [ ] Cuando el paso se omite, el onboarding queda igualmente en `COMPLETED` y se publica `OnboardingCompleted`.
- [ ] Un usuario que ya sigue a todos los candidatos recibe `shouldDisplay: false` con `ALREADY_FOLLOWING_ALL`.
- [ ] No se sugieren cuentas sin activar.
- [ ] `MIN_SUGGESTIONS` es configurable, no una constante repartida por el código.
- [ ] «Saltar» completa el onboarding sin seguir a nadie.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| OB-6 | ¿Qué se muestra si no hay autores suficientes? | **Resuelto:** cadena de relleno y omisión del paso. Ver arriba |
| OB-4 | ¿Falta un botón explícito de «Saltar»? | **Resuelto:** se añade |
| OB-2 | ¿Qué nombre se muestra en la tarjeta? | **Resuelto:** el `name` del onboarding, que es público |
| C-1 | ¿Cuántas sugerencias se devuelven como máximo? | Propuesta: 10, con scroll. Sin confirmar |
| OB-12 | ¿A dónde lleva «Siguiente»: al Home o a un segundo onboarding de perfil? | La nota del propio Figma lo deja abierto |
| S-1 | ¿Qué es una «publicación» en el contador: posts del muro, obras publicadas, o ambos? | Cambia el significado del dato |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `TODO`.
