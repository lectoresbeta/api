---
id: FEAT-WRK-015
title: Mis relatos — listado con filtros y ordenación
context: Work
concept: Catalog
actors: [Writer]
spec_status: APPROVED
impl_status: PARTIAL
priority: P1
sources:
  - conversation:2026-09-22 (pestaña «Mis relatos»)
  - docs/ui/my-works.md
endpoints: [GET /me/works]
events: []
depends_on: [FEAT-WRK-016]
updated: 2026-09-26
---

# FEAT-WRK-015 — Mis relatos: listado con filtros y ordenación

## Resumen

Pestaña del perfil propio que lista las obras del autor, con filtros por estado y cuatro
criterios de ordenación.

## Filtros

| Chip | Devuelve |
|---|---|
| **Todas** | Todas las obras del autor |
| **En corrección** | Estado `IN_CORRECTION` |
| **Visibles** | Estado `PUBLISHED` |
| **En borrador** | Estado `DRAFT` |

Los tres estados son excluyentes, así que los tres filtros **suman el total**: el perfil dice
«4 Relatos» y las pestañas muestran 1 + 2 + 1.

## Ordenación

| Opción | Criterio |
|---|---|
| **Más valorados** | La media de las valoraciones (`WorkRating`). `W-12`, resuelta |
| **Más antiguos** | Fecha de creación, ascendente |
| **Más recientes** | Fecha de creación, descendente |
| **Más leídos** | Número de lecturas |

Los cuatro son **calculables sin ambigüedad**, a diferencia del «Más relevantes» del muro y
de los comentarios. Aquí no hace falta definir ninguna fórmula.

## Datos de cada tarjeta

| Dato | Origen |
|---|---|
| Portada, título, sinopsis, géneros | `Work` |
| Número de fragmentos | `Work` |
| Valoraciones | Agregado, alimentado por `Feedback` |
| Lecturas | Agregado. **Concepto sin definir** (`H-3`) |
| Tiempo de lectura | Derivado del número de palabras |
| **Estado** | `FEAT-WRK-016` |
| **Nota de corrección en curso** | `Credits` / `Feedback`. Ver abajo |

La insignia de estado **solo se muestra al autor**: es información de gestión, no del
catálogo público.

### «Alguien está corrigiendo este texto ahora…»

Nota silenciosa en la tarjeta cuando hay al menos una corrección abierta sobre esa obra.

**Solo aparece aquí. No se notifica nunca por correo** (`C-39`). Es información que el autor
encuentra si entra a mirar, no un aviso que le persigue.

La distinción no es cosmética: notificar por correo que alguien ha empezado a corregirle
crearía una expectativa que puede no cumplirse —el lector abandona— y convertiría cada intento
de corrección en un evento. La nota, en cambio, le da contexto justo cuando está gestionando
sus textos, que es cuando le sirve.

Un efecto secundario útil: un autor con poco saldo que vea la nota puede corregir a otro y
evitar que la corrección le llegue **bloqueada**
([`FEAT-CRD-018`](../credits/FEAT-CRD-018-negative-balance.md)).

## Reglas de negocio

- `RN-1` Devuelve **solo las obras del usuario autenticado**.
- `RN-2` Incluye los borradores, que en ningún otro listado aparecen.
- `RN-3` Nunca devuelve el contenido de las obras, solo su sinopsis.
- `RN-4` Se pagina.
- `RN-5` Funciona con la cuenta sin activar: es solo lectura.
- `RN-6` Los agregados de valoraciones y lecturas se sirven desde una proyección; **no se
  calculan con un `COUNT` por tarjeta**.
- `RN-7` La insignia de estado no se expone en los listados públicos de obras.

`RN-1` parece obvia y es la que hay que probar: un fallo aquí expone los borradores de otro
autor, que es obra inédita que nadie ha decidido enseñar.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Mis obras | `GET /me/works` | `listMyWorks` |

Parámetros: `status` para el filtro y `sort` para el orden, además de la paginación estándar
(`pagination.md`). Un `sort` no admitido devuelve `422`, no se ignora en silencio.

## Criterios de aceptación

- [x] Devuelve solo las obras del usuario autenticado.
- [x] **Nunca devuelve obras de otro autor, ni siquiera sus borradores.**
- [x] El filtro «En borrador» devuelve las obras en `DRAFT`.
- [x] Los tres filtros de estado suman el total de «Todas».
- [x] «Más recientes» y «Más antiguos» ordenan por fecha en sentidos opuestos.
- [ ] «Más leídos» ordena por número de lecturas.
- [x] No se devuelve el contenido de ninguna obra.
- [x] El listado se pagina.
- [x] Un criterio de orden no admitido devuelve `422`.
- [x] Funciona con la cuenta sin activar.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| W-12 | ¿«Más valorados» usa `WorkRating` o los «me gusta»? La tarjeta muestra un corazón | **Resuelta (2026-09-26) por construcción:** no hay «me gusta» sobre una obra en esta plataforma —los apoyos son de publicaciones y comentarios (`FEAT-COM-008`, `FEAT-COM-030`)—, así que la única métrica que existe es `WorkRating`. El corazón de la maqueta no tiene detrás ninguna funcionalidad |
| H-3 | ¿Qué cuenta como «lectura»? | Concepto nuevo con coste de escritura en cada apertura |
| H-2 | ¿Qué es «1 / 22»? El primer número es siempre 1 | Si es progreso, hace falta seguimiento de lectura |
| W-13 | ¿Se conservan filtro y orden al volver a la pestaña? | Detalle de interfaz |
| W-16 | ¿Hay una vista equivalente para el perfil **público** de un autor? | Ahí no deberían aparecer borradores ni insignias de estado |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Depende de `FEAT-WRK-016`, ya aprobada. Lo que queda
—qué métrica usa «más valorados», qué cuenta como lectura— no impide listar las obras propias.

**Implementación:** `PARTIAL` (2026-09-26). El listado, los tres filtros de estado, la
paginación y los dos órdenes por fecha. Una obra **archivada** aparece marcada, que no estaba
en la ficha y hacía falta: si no apareciera, «recuperar» sería una operación sin pantalla
desde la que pedirla.

~~**Faltan los dos órdenes que dependen de agregados que no existen**~~ — **«más valorados»
hecho** (2026-09-26), `sort=rated`. `W-12` se respondió sola: no hay «me gusta» sobre una obra
en esta plataforma, así que la única métrica que existe es `WorkRating`.

`Work` mantiene ahora **su propio agregado** —cuántas personas la han valorado y cuánto suman
sus notas— alimentado por `WorkRated`. Tres decisiones dentro de eso:

- **la suma y el recuento, no la media.** Una media guardada se redondea, y sumarle una nota
  nueva arrastra el redondeo de todas las anteriores;
- **una fila por persona**, `work_reader_rating`. Parece un duplicado de la tabla de
  `Feedback` y no lo es: `WorkRated` **no lleva la nota anterior**, así que sin esa fila una
  valoración cambiada dejaría la suma contando las dos. De paso hace el consumo idempotente
  sin registro de duplicados, porque reaplicar la misma nota no mueve nada;
- **las obras sin valorar van al final**, no mezcladas con las de nota baja. Una obra que
  nadie ha valorado no es una obra mal valorada, y mezclarlas castigaría a quien acaba de
  publicar. A igual media, primero la que tiene más valoraciones.

La tarjeta lleva ahora `ratingCount` y `ratingAverage`, que la ficha ya prometía y no existían.

**«Más leídos» sigue sin estar** (`H-3`): nadie ha definido qué cuenta como lectura, y contar
aperturas de página sería inventarse la métrica en la consulta. Responde `422` con los tres
que sí valen.

**Falta también la nota «alguien está corrigiendo este texto ahora…»**: vive en `Feedback` y
`Work` no puede preguntárselo sin un contrato nuevo. Es una nota silenciosa de la tarjeta, no
una regla, y entra cuando se decida si merece su propio contrato de consulta.

Un detalle que encontró la prueba: **el desempate del orden va en la misma dirección que el
criterio**. Dos obras creadas en el mismo segundo son lo normal al escribir, y un desempate
fijo hacía que «más antiguos» devolviera la más nueva primero — un orden que se contradice a
sí mismo.
