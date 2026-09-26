# 0011 — Los ficheros de rutas viven dentro de su bounded context

- **Estado:** Aceptada
- **Fecha:** 2026-09-24
- **Afecta a:** todos los contextos
- **Modifica a:** [0010](0010-routes-declared-in-yaml-per-context.md), solo en la ubicación

## Contexto

[`decision:0010`](0010-routes-declared-in-yaml-per-context.md) estableció que las rutas se
declaran en YAML, un fichero por bounded context, y las puso en `config/routes/`. Dejó
explícitamente descartada la alternativa —ponerlas dentro de `src/<Contexto>/`— «por poco».

Esta decisión invierte esa elección. **Lo demás de 0010 sigue vigente**: el YAML en vez de
atributos, un fichero por contexto y el nombre de la ruta igual al `operationId`.

## Decisión

```text
src/User/Infrastructure/routes.yaml
src/Work/Infrastructure/routes.yaml
…
src/Shared/Infrastructure/routes.yaml
```

`config/routes.yaml` pasa a ser **un índice**: importa los nueve ficheros, uno a uno, y no
declara ninguna ruta.

## Por qué

**Un contexto se lee entero sin salir de su carpeta.** Es el mismo criterio que ya se aplicó
al mapeo de Doctrine, que vive en el `Infrastructure` de cada concepto y no en `config/`: la
configuración de infraestructura acompaña a lo que configura.

Y el argumento que ya sostuvo
[`decision:0009`](0009-one-postgresql-schema-per-bounded-context.md): si algún día hay que
extraer un contexto, **sus rutas viajan con él**. Con los ficheros en `config/` habría que
acordarse de ir a buscarlos.

Que el fichero esté en `src/` no lo convierte en código. Es configuración de infraestructura,
que es exactamente la capa donde está.

## Los imports son explícitos, no un glob

`config/routes.yaml` podría haber importado `../src/*/Infrastructure/routes.yaml` con
`type: glob` y ahorrarse nueve entradas. No lo hace, por un modo de fallo concreto:

**un glob no se queja cuando un fichero se renombra o desaparece.** Las rutas simplemente
dejan de existir, sin ningún error, y eso se descubre en producción. Con imports explícitos,
un fichero que falta rompe el arranque.

El precio es acordarse de añadir una línea al crear un contexto. Lo cubre
`RoutingConventionTest`, que falla si un contexto tiene fichero de rutas y nadie lo importa.

## Consecuencias

**Positivas**

- `src/<Contexto>/` contiene todo lo del contexto, rutas incluidas.
- `config/routes.yaml` es el censo de contextos que exponen API.
- Extraer un contexto deja de requerir arqueología en `config/`.

**Negativas**

- **`src/<Contexto>/Infrastructure/` es una carpeta con nombre de capa al nivel del concepto
  de negocio**, que es justo el nivel donde la estructura del proyecto dice que va el
  concepto. Invita a pensar que ahí se puede poner código de infraestructura «del contexto»,
  y no se puede: rompería la regla que sostiene todo el árbol.

  Por eso hay un test que comprueba que esa carpeta **no contiene más que `routes.yaml`**. Es
  la mitigación de una decisión que, sin ella, envejecería mal.
- `config/` deja de contener toda la configuración de enrutado. Quien venga de un Symfony
  convencional lo buscará ahí primero; el comentario de `config/routes.yaml` lo lleva al
  sitio.

**Qué coste tendría revertirla**

Trivial hoy —nueve ficheros y un índice—, y crece despacio: mover un fichero y cambiar una
línea por contexto.
