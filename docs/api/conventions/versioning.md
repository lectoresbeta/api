# Versionado

## Estrategia

**Propuesta: prefijo de versión en la ruta.**

```text
/api/v1/works
```

A favor: explícito, fácil de enrutar, fácil de convivir con dos versiones. En contra: obliga
a versionar la API entera aunque cambie un solo endpoint.

Alternativa considerada: versionado por cabecera (`Accept: application/vnd.lectoresbeta.v1+json`).
Más granular, pero más difícil de probar y de cachear.

**Pendiente de confirmar** antes del primer endpoint. Una vez publicada la API, cambiar de
estrategia es caro.

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
