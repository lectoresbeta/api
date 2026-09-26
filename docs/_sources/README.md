# Material de origen

Documentos de partida del proyecto. Se conservan como referencia histórica y para
trazabilidad, pero **no son la fuente de verdad**: lo es la documentación en markdown.

| Fichero | Contenido | Estado |
|---|---|---|
| `use-cases.pdf` | Casos de uso, versión 3.0. Funcionalidades por rol | Volcado al registro maestro |
| `credit-system.pdf` | Sistema de créditos: principios, acciones, cuantificación | Volcado a `bounded-contexts/credits.md` |

## Cómo citarlos

En el campo `sources` del front matter de una ficha:

```yaml
sources:
  - _sources/use-cases.pdf#p1
  - _sources/credit-system.pdf#p2
```

## Qué se ha extraído

### `use-cases.pdf` (3 páginas)

Estructura por rol: Escritor, Lector, Todos los usuarios, Registro y Login. Ha generado las
85 funcionalidades del registro maestro.

Glosario incluido en el documento: **LB** = Lectores Beta, **writing buddy** = compañeros de
escritura que intercambian feedback sobre sus obras, **MD** = mensaje directo.

Preguntas que el documento deja abiertas explícitamente:

- en qué situaciones se crea el registro cifrado de la obra: ¿en cada edición, al publicar, o
  cuando lo decide el autor? (`W-1`);
- si el ranking de lectores se filtra por periodo de tiempo (`CM-5`).

### `credit-system.pdf` (4 páginas)

Principios (justo, equilibrado, dinámico, sostenible), acciones que mueven créditos, tabla de
cuantificación general, tabla de clasificación por extensión, la alternativa de cálculo
continuo y el coste por preguntas adicionales del cuestionario.

La página 3 contiene un gráfico de la curva de cálculo continuo que no se ha podido extraer
como texto. Si esa alternativa se retoma (`FEAT-CRD-010`), conviene recuperar la fórmula del
documento original.

Erratas detectadas y su interpretación:

| En el documento | Interpretación | Dónde se documenta |
|---|---|---|
| Tramo "20.0001 – 50.000" palabras | 20.001 – 50.000, continuidad del tramo anterior | `bounded-contexts/credits.md` |

Lagunas del documento:

- qué ocurre por encima de 75.000 palabras (`C-6`);
- qué ocurre si el autor no tiene saldo suficiente (`C-1`);
- la fórmula concreta del cálculo continuo.

## Si aparece material nuevo

Se añade a esta carpeta, se documenta en esta tabla y se vuelca al markdown correspondiente.
El PDF nunca sustituye a la documentación: la alimenta.
