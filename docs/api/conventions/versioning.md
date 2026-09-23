# Versionado

## Estrategia

**Prefijo de versión en la ruta**, decidido en
[`decision:0012`](../../decisions/0012-api-version-prefix-in-the-path.md).

```text
/api/v1/works
```

El prefijo se **escribe entero en cada ruta** del fichero de su contexto. No se declara como
`prefix` en el `import` de `config/routes.yaml`: el fichero de rutas de un contexto es el mapa
de lo que ese contexto expone ([`decision:0011`](../../decisions/0011-route-files-live-inside-their-context.md)),
y un prefijo invisible lo convierte en media verdad.

```yaml
registerUser:
    path: /api/v1/auth/register
    controller: LectoresBeta\User\Account\Infrastructure\Controller\RegisterUserController::__invoke
    methods: [POST]
```

### Qué queda fuera de la versión

`/health` y `/health/live`. Las consultan sondas de infraestructura que no versionan nada y
que deben seguir respondiendo cuando la v1 se retire.

Descartado: versionado por cabecera (`Accept: application/vnd.lectoresbeta.v1+json`). Más
granular, pero invisible en un log y difícil de probar a mano.

## Qué es un cambio compatible

Se puede hacer sin nueva versión:

- añadir un endpoint;
- añadir un campo **opcional** a una petición;
- añadir un campo a una respuesta;
- añadir un valor a un enum **de salida**, si los clientes toleran valores desconocidos;
- añadir un filtro opcional;
- relajar una validación.

## Qué es un cambio incompatible

Requiere nueva versión:

- eliminar o renombrar un campo;
- cambiar el tipo de un campo;
- convertir un campo opcional en obligatorio;
- eliminar o renombrar un endpoint;
- cambiar el código HTTP de una respuesta habitual;
- añadir un valor a un enum **de entrada** que el cliente deba manejar;
- endurecer una validación;
- cambiar el significado de un campo manteniendo su nombre.

El último es el más peligroso: no rompe la deserialización, rompe el comportamiento, y no
se detecta hasta que ya hay datos incorrectos.

## Retirada de una versión

Cuando exista una v2 habrá que definir: cuánto tiempo se mantiene la v1, cómo se avisa, y
qué responde la v1 tras su retirada. Se documentará cuando ocurra.

## Versionado de eventos

Los eventos de integración se versionan **por separado** de la API HTTP. Son dos contratos
independientes con consumidores distintos. Ver [`../../events/README.md`](../../events/README.md).
