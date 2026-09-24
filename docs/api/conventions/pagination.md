# Paginación, filtrado y ordenación

## Paginación

Toda colección que pueda crecer se pagina. No hay endpoints que devuelvan listas completas.

**Propuesta: paginación por cursor**, salvo donde se justifique otra cosa.

```text
GET /posts?limit=20&cursor=<opaco>
```

```json
{
  "data": [ ],
  "pageInfo": {
    "nextCursor": "eyJpZCI6...",
    "hasNextPage": true
  }
}
```

| Parámetro | Por defecto | Máximo |
|---|---|---|
| `limit` | 20 | 100 |
| `cursor` | — | — |

Motivo: el muro principal, los comentarios y los mensajes directos son flujos donde se
insertan elementos constantemente. Con `offset` se repiten o se saltan elementos al paginar.

**Excepciones.** Dos tipos de colección se paginan **por página numerada**:

1. **Los rankings**, porque la posición es parte del significado del dato.

   ```text
   GET /rankings/writers?page=1&perPage=20&period=MONTH&genre=...
   ```

2. **El catálogo de obras** (`GET /works`), porque la sección «Leer» muestra el total de
   resultados —«948 historias»— y permite saltar a una página concreta
   ([`FEAT-WRK-012`](../../features/work/FEAT-WRK-012-browse-catalogue.md)).

   ```text
   GET /works?page=1&perPage=20&genres[]=fiction&status=IN_CORRECTION&sort=relevance
   ```

   ```json
   {
     "data": [ ],
     "pageInfo": { "page": 1, "perPage": 20, "total": 948, "totalPages": 48 }
   }
   ```

**Una tercera excepción, y de otra naturaleza: la búsqueda de personas no se pagina en
absoluto.**

```text
GET /works/{workId}/invitable-readers?query=ana
```

No es una colección que alguien recorre, es una ayuda a escribir un nombre
([`FEAT-RDG-006`](../../features/reading/FEAT-RDG-006-find-beta-readers.md)): un tope duro de
diez resultados, sin cursor y sin total. La razón no es de rendimiento sino de protección —
con paginación, veinte peticiones devolverían el directorio entero igual, solo que más
despacio, y **un directorio de personas que se puede enumerar es uno que alguien acabará
descargando**. Quien no encuentre a quien busca teclea dos letras más, que es lo que se hace
de verdad con un desplegable de autocompletado.

El criterio que separa los dos primeros casos: **un flujo cronológico se pagina por cursor; un
catálogo estable sobre el que se salta, por página.** El cursor evita repetir o perder
elementos cuando se insertan constantemente por arriba, que es lo que le pasa a un muro y no
a un catálogo.

El precio de la página numerada es el recuento total: `COUNT(*)` con filtros combinados es la
consulta cara de esa pantalla. Si el volumen lo exige, un total aproximado es preferible a
una pantalla lenta.

El cursor es **opaco**: el cliente no lo interpreta ni lo construye.

## Filtrado

Filtros como parámetros de consulta con nombre explícito:

```text
GET /works?genre=fantasy&minRating=4&accessMode=ON_REQUEST
GET /posts?type=LOOKING_FOR_BETA_READERS&authorId=...&from=2026-01-01
```

- Un parámetro, un criterio. Nada de lenguajes de filtrado en una sola cadena.
- Los valores de enum se envían con el identificador en inglés, tal como los define el
  [glosario](../../glossary.md).
- Un filtro no documentado se ignora; no provoca error.

## Ordenación

```text
GET /works?sort=-createdAt
```

- Prefijo `-` para orden descendente.
- Solo se admiten los campos documentados por endpoint.
- Un campo no admitido devuelve `422`, no se ignora en silencio: ordenar por algo distinto
  de lo pedido es un resultado incorrecto.

## Búsqueda por texto

```text
GET /works?q=<texto>
GET /users?q=<texto>
GET /posts?q=<texto>
```

`q` busca en los campos que cada endpoint documente. **Nunca busca dentro del contenido de
obras a las que el usuario no tiene acceso**: sería una vía para extraer contenido inédito
mediante consultas repetidas.
