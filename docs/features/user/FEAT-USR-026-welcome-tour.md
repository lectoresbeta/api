---
id: FEAT-USR-026
title: Tour de bienvenida de la Home
context: User
concept: Onboarding
actors: [User]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - figma:1800-14717 (Home_Tour)
  - docs/ui/home.md
endpoints:
  - getTourState
  - completeTour
events: []
depends_on: [FEAT-COM-017]
updated: 2026-09-25
---

# FEAT-USR-026 — Tour de bienvenida de la Home

## Resumen

Cuatro globos sobre un fondo atenuado que presentan las piezas clave de la aplicación la
primera vez que el usuario llega a la Home.

El backend aporta poco pero imprescindible: **recordar si ya lo vio**. Sin eso, el tour
reaparece en cada visita y se convierte en un incordio.

## Pasos

| Paso | Ancla | Título | Texto |
|---|---|---|---|
| 1/4 | Avatar de la cabecera | Personaliza tu experiencia | «Edita tu perfil, ajusta tus preferencias y gestiona tu cuenta» |
| 2/4 | «Leer» del menú lateral | Descubre nuevas historias | «Lee obras inéditas y deja tu feedback para ayudar a otros escritores a mejorar. Cada comentario que hagas te permitirá ganar créditos para recibir feedback en tus obras.» |
| 3/4 | «Escribir» del menú lateral | Comparte tus historias | «Publica tus escritos para recibir feedback de otros escritores y mejorar tus obras.» |
| 4/4 | Bloque de créditos | Gana y usa tus créditos | «Los créditos te permiten recibir feedback en tus escritos. Gana créditos comentando obras de otros autores y úsalos para obtener sugerencias sobre tus textos.» |

Cada globo tiene «×» para cerrar y contador `n/4`. El botón es «Siguiente» salvo en el
último, que dice «Entendido».

## Reglas de negocio

- `RN-1` El tour se muestra una sola vez por usuario.
- `RN-2` Cerrarlo con «×» en cualquier paso cuenta como visto: no reaparece.
- `RN-3` El estado se guarda **en el servidor**, no en el navegador. Si se guardara solo en
  local, el tour reaparecería en cada dispositivo y desaparecería al limpiar el navegador.
- `RN-4` Se registra en qué paso se abandonó. Es la única métrica que dice si el tour
  funciona o si la gente lo cierra en el primer globo.
- `RN-5` El tour **no bloquea nada**: la Home es utilizable con él abierto.
- `RN-6` Funciona con la cuenta sin activar: no es una operación de escritura de contenido
  (excepción equivalente a la del onboarding, `FEAT-USR-025` `RN-5`).

`RN-3` parece un detalle y no lo es: es la diferencia entre una decisión de producto
observable y una preferencia invisible del navegador.

## Flujo principal

1. El usuario llega a la Home por primera vez.
2. El contexto de sesión indica que el tour está pendiente.
3. Se muestran los cuatro pasos en orden.
4. Al pulsar «Entendido», o «×» en cualquier paso, se registra como completado con el paso
   alcanzado.
5. No vuelve a mostrarse.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Consultar estado del tour | `GET /api/v1/me/tour` | `getTourState` |
| Marcar el tour como visto | `POST /api/v1/me/tour/completion` | `completeTour` |

`tourId` es opcional y por defecto es el de la Home, el único que hay hoy. **Va en la petición
y no en la ruta**, para que añadir un tour no sea añadir dos endpoints.

Un `tourId` que no existe, o un paso que no está en el tour, **se rechazan** con `422`
(`UNKNOWN_TOUR`, `UNKNOWN_TOUR_STEP`). No se ignoran: un identificador con una errata crearía
un tour fantasma que nadie ha visto nunca y que se enseñaría para siempre, y un paso fuera de
rango estropearía en silencio la única métrica que justifica guardarlo.

El estado también viaja en el contexto de sesión del layout, para no añadir una petición más
en cada carga.

`POST` admite el paso alcanzado y si se cerró o se terminó.

## Modelo de datos afectado

| Tabla | Contenido |
|---|---|
| `user_tour` | `user_id`, identificador del tour, `completed_at`, `last_step`, `dismissed` |

Ya existía desde el esquema inicial. **Ninguna migración**: esta ficha era la que faltaba por
encima.

**La ausencia de fila significa «pendiente»**, y de ahí que consultar el estado no escriba
nada. Apuntar en la base de datos que alguien todavía no ha hecho nada, en cada carga de la
Home, sería la escritura más cara y menos útil del producto.

Se modela **por tour identificado**, no como un booleano en `user`. Habrá más tours —al
estrenar secciones, al cambiar funcionalidades— y un campo por cada uno acabaría siendo una
columna nueva cada vez.

## Diseño (Figma)

Frames `1916:12837`, `2019:14700`, `2019:15969`, `2019:16708`.

Ver [`../../ui/home.md`](../../ui/home.md).

## Criterios de aceptación

- [x] Un usuario nuevo ve el tour la primera vez que entra en la Home.
- [x] Tras terminarlo, no vuelve a aparecer.
- [x] Cerrarlo en el paso 2 cuenta como visto y no reaparece.
- [x] El estado persiste al cambiar de navegador o de dispositivo.
- [x] Se registra el paso en que se abandonó.
- [x] La Home funciona con el tour abierto.
- [x] Un usuario con la cuenta sin activar ve el tour con normalidad.
- [x] El modelo admite tours futuros sin cambiar el esquema.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| T-1 | ¿Se puede volver a lanzar el tour desde «Ayuda»? | Sigue abierta. El modelo lo admitiría —bastaría con borrar la fila— pero no hay operación para ello, y crearla sin que nadie la pida sería una forma de reabrir el tour por error |
| T-2 | ¿Se puede retroceder de paso? El diseño solo muestra «Siguiente» | Interfaz. Al backend le da igual: `lastStep` es el paso alcanzado, no un puntero |
| T-3 | ¿Qué pasa si se cierra la pestaña a mitad sin pulsar nada? | **Resuelta:** sigue pendiente y se vuelve a mostrar. Es lo que se consigue no escribiendo nada al consultarlo |
| M-4 | El paso 4/4 y el modal de créditos (`FEAT-CRD-014`) cuentan lo mismo. ¿Conviven? | **Resuelta:** el tour cubre la primera vez y el modal se abre bajo demanda. Dos explicaciones seguidas de lo mismo se saltan las dos |
| T-4 | El paso 4/4 dice «úsalos para obtener sugerencias sobre tus textos», pero los créditos se gastan al recibir comentarios | **Resuelta en el lenguaje**: lo que manda es `FEAT-CRD-015`, que declara `chargedOn: FEEDBACK_DELIVERED`. El texto del globo debe decir eso mismo |

## Estado

**Especificación:** `APPROVED` (2026-09-24).

**Implementación:** `DONE` (2026-09-25). La entidad y la tabla existían desde el esquema
inicial; lo que faltaba era el repositorio, los dos casos de uso y los dos endpoints. `T-1`
sigue abierta y no bloquea nada.
