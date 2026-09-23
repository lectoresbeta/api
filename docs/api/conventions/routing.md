# Enrutado

**Las rutas se declaran en YAML, nunca con atributos ni anotaciones sobre el controlador.**
El porqué está en [`decision:0010`](../../decisions/0010-routes-declared-in-yaml-per-context.md)
y [`decision:0011`](../../decisions/0011-route-files-live-inside-their-context.md); aquí está
el cómo.

## Dónde va cada ruta

Un fichero por bounded context, **dentro del propio contexto**:

```text
config/routes.yaml                        índice: importa, no declara
src/User/Infrastructure/routes.yaml
src/Work/Infrastructure/routes.yaml
src/Reading/Infrastructure/routes.yaml
src/Feedback/Infrastructure/routes.yaml
src/Community/Infrastructure/routes.yaml
src/Credits/Infrastructure/routes.yaml
src/Moderation/Infrastructure/routes.yaml
src/Notification/Infrastructure/routes.yaml
src/Shared/Infrastructure/routes.yaml
```

Así un contexto se lee entero sin salir de su carpeta, igual que ocurre con el mapeo de
Doctrine. Si algún día hay que extraer uno, sus rutas viajan con él.

Cada contexto tiene su fichero **aunque todavía no exponga nada**: quien añada el primer
endpoint no tiene que averiguar dónde va.

### Al crear un contexto, dos pasos

1. `src/<Contexto>/Infrastructure/routes.yaml`, aunque quede vacío.
2. Una entrada en `config/routes.yaml` que lo importe.

Los imports están escritos uno a uno **y no con un glob**, a propósito: un glob no se queja
cuando un fichero se renombra o desaparece —las rutas dejan de existir sin ningún error— y
eso se descubre en producción. El olvido del paso 2 lo detecta el test.

### Esa carpeta es solo para el YAML

`src/<Contexto>/Infrastructure/` es el único sitio del árbol donde una carpeta con nombre de
capa vive al nivel del concepto de negocio, y está ahí para un fichero de configuración.
**No contiene código.** El de infraestructura pertenece a su concepto:
`src/<Contexto>/<Concepto>/Infrastructure/`.

Si esa carpeta pudiera contener código, el segundo nivel dejaría de ser el concepto —que es
la regla que sostiene toda la estructura— y nadie sabría si `src/Work/Infrastructure/` es
configuración o una capa más. Hay un test que lo impide.

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
- `config/routes.yaml` declara una ruta en vez de importarla;
- un bounded context se ha quedado sin fichero de rutas;
- **un fichero de rutas existe y nadie lo importa** —el fallo silencioso que justifica no
  usar un glob—;
- `src/<Contexto>/Infrastructure/` contiene algo que no es `routes.yaml`;
- una ruta apunta a una clase o a un método que no existe;
- una ruta no tiene su `operationId` en `openapi/`.

Una convención que solo vive en un documento se incumple el día que alguien tiene prisa.
