# Enrutado

**Las rutas se declaran en YAML, nunca con atributos ni anotaciones sobre el controlador.**
El porqué está en [`decision:0010`](../../decisions/0010-routes-declared-in-yaml-per-context.md);
aquí está el cómo.

## Dónde va cada ruta

Un fichero por bounded context:

```text
config/routes.yaml          vacío, solo punto de entrada de Symfony
config/routes/user.yaml
config/routes/work.yaml
config/routes/reading.yaml
config/routes/feedback.yaml
config/routes/community.yaml
config/routes/credits.yaml
config/routes/moderation.yaml
config/routes/notification.yaml
config/routes/shared.yaml
```

Symfony los importa todos solos: no hay que registrar nada al crear uno.

Cada contexto tiene su fichero **aunque todavía no exponga nada**. Un fichero vacío con su
cabecera cuesta cero y hace que listar el directorio sea el mapa de la API; además, quien
añada el primer endpoint de un contexto no tiene que averiguar dónde va.

## Formato

```yaml
registerUser:
    path: /api/v1/auth/register
    controller: LectoresBeta\User\Account\Infrastructure\Controller\RegisterUserController::__invoke
    methods: [POST]
```

| Clave | Regla |
|---|---|
| Nombre de la ruta | **Es el `operationId` de OpenAPI.** Ver abajo |
| `path` | La ruta pública, con el prefijo de versión salvo en las excepciones documentadas |
| `controller` | FQCN y método. Un controlador invocable usa `::__invoke` explícito |
| `methods` | Siempre explícito, aunque sea uno solo |

## El nombre de la ruta es el `operationId`

No es cosmético. `AGENTS.md` exige que la implementación y la especificación OpenAPI no
diverjan, y sin una regla como esta eso es un buen propósito: nadie compara a mano una lista
de rutas con un YAML de mil líneas.

Con la regla, la comparación es mecánica, y
[`RoutingConventionTest`](../../../tests/Unit/Architecture/RoutingConventionTest.php) la hace
en cada ejecución: **toda ruta implementada tiene que aparecer como `operationId` en
`openapi/`**. Lo contrario no se exige —hay operaciones documentadas que aún no existen—,
pero un endpoint sin documentar rompe la suite.

## Qué sí va en el controlador

`#[AsController]` sobre la clase. Es configuración de servicio —le pone la etiqueta
`controller.service_arguments`—, no enrutado, y sin ella el controlador no recibe sus
argumentos.

## Excepciones al prefijo de versión

Solo las que estén documentadas. Hoy hay una: las sondas de salud (`/health` y
`/health/live`) viven fuera de `/api/v1`, porque la dirección de una sonda acaba escrita en
orquestadores y sistemas de monitorización que actualiza gente que no lee las notas de
versión. Ver
[`07-observability-and-operations.md`](../../architecture/07-observability-and-operations.md).

Cuando una operación se salga del prefijo, declara sus propios `servers` en OpenAPI, para que
el contrato siga siendo cierto.

## Qué se comprueba solo

`RoutingConventionTest` falla si:

- algún fichero de `src/` usa el atributo `#[Route]`;
- `config/routes.yaml` declara alguna ruta;
- un bounded context se ha quedado sin fichero de rutas;
- una ruta apunta a una clase o a un método que no existe;
- una ruta no tiene su `operationId` en `openapi/`.

Una convención que solo vive en un documento se incumple el día que alguien tiene prisa.
