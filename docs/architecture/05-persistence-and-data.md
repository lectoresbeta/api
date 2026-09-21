# Persistencia y datos

> Estado: `DRAFT`. El modelo físico se detallará por contexto conforme se especifiquen
> las funcionalidades.

## Principios

- PostgreSQL es la base de datos relacional del proyecto.
- Doctrine es la implementación de persistencia, **confinada a `Infrastructure`**.
- El modelo de dominio no se deforma para encajar en la base de datos.
- Todo cambio de esquema va en una migración de Doctrine, commiteada junto al código.

## Mapeo

- Preferencia por **mapeo XML en `Infrastructure`**, no atributos en las entidades de
  `Domain`. Esto mantiene el dominio libre de Doctrine, tal como exige `AGENTS.md`.
- `EntityManagerInterface`, `QueryBuilder` y las colecciones de Doctrine no salen de
  `Infrastructure`.
- Nada de SQL en `Domain` ni en `Application`.

## Separación por contexto

Cada bounded context es propietario de sus tablas. Propuesta: **un esquema de PostgreSQL
por contexto**.

```text
user_ctx.*        work_ctx.*      reading_ctx.*
feedback_ctx.*    community_ctx.* credits_ctx.*
notification_ctx.*
```

Ventajas: hace visible la frontera, facilita auditar quién consulta qué y permitiría extraer
un contexto en el futuro. Alternativa más simple: un único esquema con prefijos de tabla.
**Decisión pendiente** (`P-1`), a cerrar en un ADR antes de la primera migración.

Regla que no depende de esa decisión: **ningún contexto consulta las tablas de otro**, ni
con un `JOIN`, ni con una vista, ni con una clave foránea que lo tiente a hacerlo.

## Claves foráneas entre contextos

No se crean. Un `user_id` en `credits_ctx.credit_account` es un valor opaco, no una
referencia relacional a `user_ctx.users`.

Consecuencia: la integridad referencial entre contextos es **eventual** y se mantiene
reaccionando a eventos (por ejemplo, `UserDeleted`), no con `ON DELETE CASCADE`.

Dentro de un mismo agregado sí se usan claves foráneas y restricciones: refuerzan
invariantes que el dominio ya protege.

## Identificadores

- **UUID v7** como identificador público de todos los agregados.
- Ordenables temporalmente, evitan colisiones y no filtran el volumen de datos como haría
  un autoincremental.
- Se exponen en la API tal cual. No se exponen identificadores internos.

## Consideraciones específicas

| Dato | Consideración |
|---|---|
| Contenido de las obras | Puede ser muy extenso (hasta ~75.000 palabras). Decidir si se almacena en columna `text`, en almacenamiento externo, o ambos. Ver `P-2`. |
| Registro de autoría | Inmutable. Solo inserción, nunca actualización ni borrado. |
| Movimientos de créditos | Inmutables. El saldo es su consecuencia, no un dato editable de forma independiente. |
| Eventos procesados | Tabla de deduplicación en `Credits` con el `eventId` y su marca temporal. Requiere política de purga. |
| Ficheros subidos | No se guardan en base de datos. Almacenamiento externo mediante el puerto `FileStorage`. |
| Rankings | Read models. Pueden vivir en tablas materializadas y recalcularse. |
| Búsqueda de obras y autores | Empezar con PostgreSQL (`tsvector`, índices GIN). Un motor externo solo si se demuestra necesario (`BC-2`). |

## Índices

Se añaden **de forma deliberada y a partir de patrones de acceso reales** documentados en
las fichas de funcionalidad, no preventivamente.

Patrones ya identificados:

- obras por autor; obras por temática y valoración (catálogo);
- accesos de lector beta por obra y por usuario (autorización en cada lectura);
- feedback por obra y por autor del comentario;
- publicaciones del muro por fecha, tipo y autor;
- movimientos de créditos por usuario y fecha.

## Migraciones

- Reversibles siempre que sea razonable.
- Sin cambios destructivos innecesarios.
- Las migraciones de datos grandes o arriesgadas se documentan en `docs/`.
- El esquema de producción nunca se modifica a mano.

## Retención y borrado

Pendiente de decisión de producto (`V-4`, `J-7`). Cuestiones a resolver antes de implementar
el borrado de cuenta:

- ¿Se borran las obras del usuario o se anonimizan?
- ¿Qué ocurre con el feedback que dejó en obras ajenas?
- ¿Se conserva el registro de autoría?
- ¿Qué pasa con su saldo y su historial de créditos?
- ¿Cuál es el plazo legal de conservación aplicable?

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| P-1 | ¿Un esquema por contexto o uno solo con prefijos? | Define la primera migración y las herramientas |
| P-2 | ¿Dónde se guarda el contenido de las obras? | Rendimiento, coste, cifrado |
| P-3 | ¿Se versiona el contenido al editar? | Afecta al registro de autoría (`D-4`) |
| P-4 | ¿Se cifra el contenido de las obras en reposo? | El documento de partida habla de "cifrar el contenido" para el registro de autoría; hay que distinguir **huella criptográfica** de **cifrado** |
