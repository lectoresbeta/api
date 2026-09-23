# 0012 — La versión de la API va en la ruta, escrita entera en cada ruta

- **Estado:** Aceptada
- **Fecha:** 2026-09-23
- **Afecta a:** todos los contextos que exponen HTTP, `openapi/`, `config/routes.yaml`

## Contexto

[`docs/api/conventions/versioning.md`](../api/conventions/versioning.md) proponía el prefijo
`/api/v1` y lo dejaba **«pendiente de confirmar antes del primer endpoint»**. Ese momento ha
llegado: la primera rodaja vertical necesita rutas reales.

De hecho la decisión ya estaba tomada a medias y sin escribir: `openapi/openapi.yaml` declara
desde el principio `servers: https://api.lectoresbeta.com/api/v1`. Lo que faltaba no era
decidir, era cerrarlo antes de que el contrato se publique: **una vez hay clientes, cambiar de
estrategia obliga a mantener las dos**.

Quedaban dos cosas por resolver, no una:

1. si la versión va en la ruta o en una cabecera;
2. **dónde se escribe el prefijo**, que con la convención de enrutado de
   [`0010`](0010-routes-declared-in-yaml-per-context.md) y
   [`0011`](0011-route-files-live-inside-their-context.md) ya no es obvio.

## Decisión

**La versión va en la ruta, como prefijo `/api/v1`, y se escribe entera en cada ruta del
fichero de su contexto.**

```yaml
registerUser:
    path: /api/v1/auth/register
    controller: LectoresBeta\User\Account\Infrastructure\Controller\RegisterUserController::__invoke
    methods: [POST]
```

**No** se declara como `prefix` en el `import` de `config/routes.yaml`.

Quedan **fuera de la versión** las rutas que no forman parte del contrato de producto:
`/health` y `/health/live`, porque las consultan
sondas de infraestructura que no versionan nada y que deben seguir funcionando cuando la v1 se
retire.

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| Versión por cabecera (`Accept: application/vnd.lectoresbeta.v1+json`) | Granular, la URL identifica el recurso y no su representación | Difícil de probar a mano, difícil de cachear, invisible en un log | El coste diario cae sobre todo el equipo; la granularidad no se necesita mientras haya una sola versión |
| Sin versionar hasta que haga falta | Nada que escribir hoy | Añadir el prefijo después es un cambio incompatible para todos los clientes | El momento barato de decidirlo es antes del primer endpoint, y es ahora |
| Prefijo en el `import` de `config/routes.yaml` | `/api/v1` se escribe una vez; una v2 es un segundo bloque de importación | El fichero del contexto deja de decir cuál es la URL real | Contradice [`0011`](0011-route-files-live-inside-their-context.md): el fichero del contexto **es** el mapa de lo que expone. Media verdad en el mapa es peor que repetir seis caracteres |

La tercera es la que costó decidir. El argumento que la descarta es que la convención de
enrutado existe para que «¿qué expone este contexto?» se responda **leyendo un fichero**, y un
prefijo invisible obliga a leer dos y a recordar que existe.

## Consecuencias

**Positivas**

- La ruta del fichero de rutas, la del `openapi/` y la que viaja por el cable son **la misma
  cadena**. Se puede comparar, y `RoutingConventionTest` lo comprueba.
- Un `curl` de un log es reproducible tal cual.
- Convivir con una v2 es enrutar `/api/v2/...` a otros controladores, sin tocar la v1.

**Negativas**

- `/api/v1` se repite en cada ruta. Es ruido, y es el precio de que el fichero no mienta.
- Una v2 **completa** obligaría a editar todas las rutas. No se espera: lo normal es versionar
  operaciones sueltas y dejar el resto sirviendo ambas.

**Qué coste tendría revertirla**

Alto en cuanto haya clientes; nulo hoy. De ahí que se cierre ahora.

## Cumplimiento

`tests/Unit/Architecture/RoutingConventionTest.php` ya comprueba que cada nombre de ruta es un
`operationId` de `openapi/`. Se le añade la comprobación del prefijo: **toda ruta empieza por
`/api/v1/`, salvo las explícitamente exentas**, que se enumeran en el propio test para que
añadir una excepción sea un cambio visible y no un descuido.
