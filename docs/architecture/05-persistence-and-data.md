# Persistencia y datos

> Estado: `IMPLEMENTADO`. El esquema completo existe: ocho esquemas de PostgreSQL y 66
> tablas, en `migrations/`. Lo que aquí se describe es lo que hay, no lo que se pretende.

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

**Un esquema de PostgreSQL por contexto** ([`decision:0009`](../decisions/0009-one-postgresql-schema-per-bounded-context.md),
que cierra `P-1`):

| Esquema | Tablas | Qué guarda |
|---|---|---|
| `user_ctx` | 16 | Cuentas, credenciales, alias de nombre, preferencias, legal, invitaciones |
| `work_ctx` | 9 | Obras, capítulos, cuestionarios, autoría, enlaces públicos y el catálogo |
| `reading_ctx` | 6 | Accesos de lector beta, solicitudes, invitaciones, grupos, writing buddies |
| `feedback_ctx` | 5 | Correcciones, respuestas, evaluaciones y valoraciones de obra |
| `community_ctx` | 13 | Muro, interacción, suscripciones, bloqueos, mensajes y proyecciones |
| `credits_ctx` | 6 | Saldo, movimientos, precios, descubierto y deduplicación de eventos |
| `moderation_ctx` | 8 | Reclamaciones, decisiones, sanciones, roles y auditoría |
| `notification_ctx` | 3 | Avisos, entregas y la proyección de preferencias |

El esquema convierte la frontera en algo visible en cada consulta: un `JOIN` entre contextos
ya no se escribe sin querer, porque hay que teclear `credits_ctx.` delante.

Regla que sigue siendo la importante: **ningún contexto consulta las tablas de otro**, ni
con un `JOIN`, ni con una vista, ni con una clave foránea que lo tiente a hacerlo.

## Claves foráneas entre contextos

No se crean. Un `user_id` en `credits_ctx.credit_account` es un valor opaco, no una
referencia relacional a `user_ctx.account`.

Consecuencia: la integridad referencial entre contextos es **eventual** y se mantiene
reaccionando a eventos (por ejemplo, `UserDeleted`), no con `ON DELETE CASCADE`.

Dentro de un mismo agregado sí se usan claves foráneas y restricciones: refuerzan
invariantes que el dominio ya protege.

## Identificadores

- **UUID v7** como identificador público de todos los agregados.
- Ordenables temporalmente, evitan colisiones y no filtran el volumen de datos como haría
  un autoincremental. Que ordenen importa más de lo que parece: las escrituras no se
  dispersan por todo el índice y cualquier listado tiene un desempate estable sin columna
  extra.
- Se exponen en la API tal cual. No se exponen identificadores internos.
- La generación está escrita a mano en `Shared\Domain\ValueObject\Uuid`, sin librería: esa
  clase vive en `Domain`, y `Domain` no depende de nada fuera de PHP. Deptrac lo comprueba.
- En la base de datos son columnas `UUID`. En la entidad, un `string` privado que el dominio
  entrega tipado (`UserId`, `WorkId`, `ChapterId`…). La alternativa —un tipo de Doctrine por
  identificador— serían unas cuarenta clases sin comportamiento; la API del dominio no ve
  nunca el escalar.

## Consideraciones específicas

| Dato | Consideración |
|---|---|
| Contenido de las obras | Columna `text` en `work_ctx.chapter`. Un capítulo se lee entero y así queda cubierto por el mismo backup y la misma transacción que todo lo demás. `P-2` puede moverlo fuera más adelante sin tocar el modelo de dominio. |
| Registro de autoría | Inmutable. Solo inserción, nunca actualización ni borrado. |
| Movimientos de créditos | Inmutables. El saldo es su consecuencia, no un dato editable de forma independiente. |
| Eventos procesados | `credits_ctx.processed_event`, con el `eventId` **como clave primaria**: la garantía no es la comprobación previa sino el índice. Tiene purga por fecha. |
| Ficheros subidos | No se guardan en base de datos. Almacenamiento externo mediante el puerto `FileStorage`. |
| Rankings | Read models: `community_ctx.author_stats` y `author_genre`, alimentados por eventos y reconstruibles desde cero. |
| Búsqueda de obras y autores | Empezar con PostgreSQL (`tsvector`, índices GIN). Un motor externo solo si se demuestra necesario (`BC-2`). |

## Índices

Se añaden **de forma deliberada y a partir de patrones de acceso reales** documentados en
las fichas de funcionalidad, no preventivamente.

### Los índices que no son una optimización

Seis índices únicos **parciales** son la única garantía real de una regla de negocio bajo
concurrencia. En código, dos peticiones simultáneas pasan las dos la comprobación:

| Índice | Regla que garantiza |
|---|---|
| `uniq_correction_reader_chapter` | Una corrección por lector y capítulo (`FEAT-FBK-003` `RN-2`) |
| `uniq_beta_reader_access_live` | Un acceso vigente por pareja (lector, obra) |
| `uniq_access_request_pending` | Una solicitud pendiente por pareja |
| `uniq_access_invitation_pending` | Una invitación pendiente por pareja |
| `uniq_writing_buddy_live` | Un vínculo vigente por pareja |
| `uniq_email_change_pending` | Una solicitud de cambio de correo abierta por cuenta |

Son **parciales** porque la regla vale mientras la fila esté viva. Un índice único normal
impediría volver a conceder un acceso revocado o volver a pedir un cambio de correo.

Dos más, `idx_catalogue_genres` e `idx_catalogue_warnings`, son **GIN sobre JSONB**: el
catálogo filtra por «cualquiera de estos géneros» y «ninguna de estas etiquetas»
([`decision:0008`](../decisions/0008-catalogue-ordering.md)), que es justo lo que responde GIN.

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
| ~~P-1~~ | ¿Un esquema por contexto o uno solo con prefijos? | **Resuelta:** uno por contexto ([`decision:0009`](../decisions/0009-one-postgresql-schema-per-bounded-context.md)) |
| P-2 | ¿Dónde se guarda el contenido de las obras? | Hoy en columna `text`. Rendimiento, coste, cifrado |
| P-3 | ¿Se versiona el contenido al editar? | Afecta al registro de autoría (`D-4`) |
| P-4 | ¿Se cifra el contenido de las obras en reposo? | El documento de partida habla de "cifrar el contenido" para el registro de autoría; hay que distinguir **huella criptográfica** de **cifrado** |
