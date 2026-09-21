# Paginación, filtrado y ordenación

## Paginación

Toda colección que pueda crecer se pagina. No hay endpoints que devuelvan listas completas.

**Propuesta: paginación por cursor**, salvo donde se justifique otra cosa.

```text
GET /works?limit=20&cursor=<opaco>
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

**Excepción**: los rankings usan paginación por página, porque la posición es parte del
significado del dato.

```text
GET /rankings/writers?page=1&perPage=20&period=MONTH&genre=...
```

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
