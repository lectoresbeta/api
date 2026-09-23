# 0009 — Un esquema de PostgreSQL por bounded context

- **Estado:** Aceptada
- **Fecha:** 2026-09-24
- **Afecta a:** todos los contextos
- **Resuelve:** `P-1`

## Contexto

La primera migración obliga a decidir algo que después es caro de cambiar: **dónde viven las
tablas de cada contexto**.

`AGENTS.md` prohíbe que un contexto consulte las tablas de otro, y
[`decision:0002`](0002-credits-as-isolated-bounded-context.md) lo refuerza para `Credits`. Pero
una prohibición escrita no impide nada: con todas las tablas en `public`, escribir

```sql
SELECT ... FROM correction c JOIN "user" u ON u.id = c.reader_id
```

es tan fácil como escribirlo bien, y nadie se entera hasta la revisión —si la hay.

## Decisión

**Ocho esquemas, uno por contexto**, con el sufijo `_ctx`:

```text
user_ctx      work_ctx      reading_ctx     feedback_ctx
community_ctx credits_ctx   moderation_ctx  notification_ctx
```

Y tres reglas que dependen de ello:

1. **Ninguna clave foránea cruza un esquema.** Un `user_id` en `credits_ctx.credit_account` es
   un valor opaco, no una referencia relacional.
2. La integridad entre contextos es **eventual**: se mantiene reaccionando a eventos
   —`UserDeleted` y los demás—, nunca con `ON DELETE CASCADE`.
3. Dentro de un mismo contexto sí hay claves foráneas y restricciones donde refuerzan una
   invariante que el dominio ya protege.

## Por qué

El esquema convierte la frontera en algo **visible en cada consulta**. Un `JOIN` entre
contextos ya no se escribe sin querer: hay que teclear `credits_ctx.` delante, y en ese
momento se ve lo que se está haciendo.

Además:

- se puede auditar con una consulta quién lee qué;
- se pueden dar permisos distintos por esquema el día que haga falta;
- extraer un contexto a su propio servicio deja de ser una arqueología de tablas.

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| **Un esquema con prefijos** (`credits_credit_account`) | Más simple; funciona en cualquier gestor | El prefijo es convención, no frontera: nada impide el `JOIN` | El prefijo se olvida; el esquema no |
| **Una base de datos por contexto** | Aislamiento real | Ocho conexiones, ocho backups, y ninguna transacción dentro de un contexto que cruce tablas | Coste operativo desproporcionado para el tamaño actual |
| **Sin separación** | Lo más rápido de montar | Es exactamente lo que `AGENTS.md` prohíbe | — |

## Consecuencias

**Positivas**

- La regla que más importa —ningún contexto lee las tablas de otro— pasa a ser visible.
- Las migraciones dicen a qué contexto pertenece cada cambio sin comentarios.

**Negativas**

- **No hay integridad referencial entre contextos.** Un `author_id` puede apuntar a una cuenta
  que ya no existe, y el código tiene que aguantarlo. Es el precio de la independencia, y es el
  mismo precio que se paga en cualquier sistema distribuido; aquí solo se paga antes.
- Las consultas que de verdad necesiten datos de dos contextos hacen falta **read models**
  alimentados por eventos. Ya hay tres: `catalogue_entry`, `chapter_price` y `author_stats`.
- Doctrine necesita el atributo `schema` en cada mapeo. Se olvida con facilidad, y la tabla
  aparece en `public` sin avisar.

**Qué coste tendría revertirla**

Medio. Mover tablas entre esquemas es un `ALTER TABLE ... SET SCHEMA` por tabla, pero hay que
revisar cada mapeo y cada consulta nativa.

## Decisiones menores que vienen con ella

**Los identificadores se guardan como `UUID` y se exponen como value objects.** La propiedad de
la entidad es un `string`; el dominio los entrega tipados (`UserId`, `WorkId`). La alternativa
—un tipo de Doctrine por identificador— serían unas cuarenta clases de ceremonia sin
comportamiento. La API del dominio no ve nunca el escalar.

**UUID v7 y no v4.** Los primeros 48 bits son una marca de tiempo, así que ordenan por
creación: las escrituras no se dispersan por todo el índice y cualquier listado tiene un
desempate estable sin columna extra.

**La tabla de cuentas es `user_ctx.account`, no `user_ctx.user`.** `user` es palabra reservada
en PostgreSQL; Doctrine la entrecomilla en el mapeo pero la lee sin comillas de la base de
datos, así que nunca coinciden: `schema:validate` la da por ausente para siempre y cada
`migrations:diff` propone borrarla y recrearla. Dentro de un esquema que ya se llama
`user_ctx`, `account` se lee además mejor.

**Los índices únicos parciales son la garantía, no una optimización.** Un acceso de lector beta
vigente por pareja, una corrección por lector y capítulo, una solicitud de cambio de correo
abierta por cuenta: bajo concurrencia, la comprobación en código no garantiza nada. El `WHERE`
es imprescindible porque la regla vale **mientras la fila esté viva**; un índice único normal
impediría volver a conceder un acceso revocado.

Su predicado se escribe **tal como lo normaliza PostgreSQL**, con sus paréntesis y sus
`::text`. Es feo y acopla el mapeo a cómo deparsea este PostgreSQL, pero Doctrine compara el
predicado del mapeo con el que lee de la base de datos: con cualquier otra grafía,
`schema:validate` reporta una diferencia para siempre y `migrations:diff` propone recrear el
índice en cada ejecución.
