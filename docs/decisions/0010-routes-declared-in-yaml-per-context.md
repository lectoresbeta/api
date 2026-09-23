# 0010 — Las rutas se declaran en YAML, un fichero por bounded context

- **Estado:** Aceptada
- **Fecha:** 2026-09-24
- **Afecta a:** todos los contextos

## Contexto

Symfony ofrece dos formas de declarar una ruta: un atributo sobre el método del controlador,
o un fichero de configuración. El esqueleto por defecto usa atributos, y así nació el primer
endpoint del proyecto.

La pregunta que hay que poder responder de un vistazo es **«¿qué expone este contexto?»**.
Con atributos, la respuesta está repartida entre todos los controladores y se obtiene con un
`grep` o con `debug:router`, que exige tener el entorno levantado.

## Decisión

**Las rutas se declaran en YAML y nunca con atributos.** Un fichero por bounded context:

```text
config/routes.yaml          vacío; solo existe como punto de entrada de Symfony
config/routes/user.yaml
config/routes/work.yaml
…
config/routes/shared.yaml
```

Cada contexto tiene su fichero **aunque todavía no exponga nada**. Listar ese directorio es
como se averigua de qué está hecha la API.

Tres reglas que vienen con ella:

1. **El nombre de la ruta es el `operationId` de OpenAPI.** Es lo que convierte «la
   implementación y la especificación no deben divergir» en algo comprobable.
2. Una ruta de un contexto va en el fichero de ese contexto. Nunca en `config/routes.yaml`,
   nunca en el de otro.
3. `#[AsController]` sobre la clase sigue estando bien: es configuración de servicio, no
   enrutado.

`tests/Unit/Architecture/RoutingConventionTest.php` comprueba las tres en cada ejecución. Una
convención que solo vive en un documento se incumple el día que alguien tiene prisa.

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| **Atributos en el controlador** | Ruta y método juntos; es el defecto de Symfony | El mapa de la API queda repartido entre decenas de ficheros; un contexto no se puede leer de un vistazo | Es justo lo que esta decisión quiere evitar |
| **Un solo `routes.yaml`** | Un único sitio donde mirar | Crece sin control y deja de estar claro de quién es cada ruta; todos los cambios de API tocan el mismo fichero | El conflicto de *merge* garantizado es el síntoma, no la causa |
| **YAML por concepto** (`user/account.yaml`) | Grano aún más fino | Cuarenta ficheros para responder a la misma pregunta | El contexto es la frontera que importa; el concepto no expone API por separado |
| **Rutas dentro de `src/<Contexto>/`** | El contexto viaja entero si algún día se extrae | `src/` deja de ser solo código, y el segundo nivel deja de ser el concepto de negocio | Se descartó por poco: `config/` es donde Symfony busca configuración, y `config/routes/*.yaml` se importa solo |

## Consecuencias

**Positivas**

- «¿Qué expone `Credits`?» se responde abriendo un fichero, sin levantar nada.
- El nombre de la ruta ata la implementación al contrato publicado, y un test lo comprueba.
- Los cambios de API de dos contextos distintos ya no tocan el mismo fichero.

**Negativas**

- **La ruta y el método que la sirve están separados.** Hay que mirar dos ficheros para saber
  qué URL atiende un controlador. Se mitiga citando el `operationId` en el docblock del
  método, pero es una molestia real y diaria.
- Una ruta puede quedar apuntando a un método que se ha renombrado. El test lo detecta, pero
  el IDE no ayuda como lo haría con un atributo.
- Symfony documenta los atributos como el camino recomendado, así que cualquier ejemplo de
  internet habrá que traducirlo.

**Qué coste tendría revertirla**

Bajo mientras haya pocos endpoints; crece con cada uno. Es el motivo de tomarla ahora, con un
solo controlador escrito.
