---
id: FEAT-WRK-004
title: Ver una obra y el contenido de sus capítulos
context: Work
concept: Manuscript
actors: [Writer, BetaReader, User]
spec_status: APPROVED
impl_status: PARTIAL
priority: P0
sources:
  - _sources/use-cases.pdf#p1
  - docs/decisions/0003-write-operations-require-activated-account.md
endpoints: [GET /works/{workId}, GET /chapters/{chapterId}]
events: []
depends_on: [FEAT-WRK-001, FEAT-WRK-016]
updated: 2026-09-23
---

# FEAT-WRK-004 — Ver una obra y el contenido de sus capítulos

## Resumen

Leer es la otra mitad del producto: sin ella, todo lo que se publica se publica hacia nadie.

Y es **la funcionalidad con más riesgo de todo el backend**. Lectores Beta custodia obra
literaria inédita; una fuga aquí no es un fallo técnico menor, es el peor fallo posible del
producto. Por eso esta ficha dedica más espacio a quién **no** puede leer que a quién sí.

## Dos operaciones, y por qué son dos

| Operación | Devuelve |
|---|---|
| `GET /works/{workId}` | Metadatos y el **índice** de capítulos: título, posición, palabras |
| `GET /chapters/{chapterId}` | **El texto** de un capítulo |

Separarlas no es economía de bytes. El índice de una novela es información inocua y el texto
no lo es, así que tienen **audiencias distintas y reglas distintas**, y mezclarlos obligaría a
la regla más estricta a gobernar las dos. Además, una novela de cuarenta capítulos no se sirve
entera en una respuesta.

## Quién puede leer

La matriz completa. Las filas son el estado de la obra; las columnas, quién pregunta.

| | Autor | Otro usuario, `PUBLIC` | Otro usuario, `ON_REQUEST` / `PRIVATE` | Sin sesión |
|---|---|---|---|---|
| `DRAFT` | **Sí** | No | No | No |
| `PUBLISHED` | Sí | **Sí** | Solo con acceso concedido | No |
| `IN_CORRECTION` | Sí | **Sí** | Solo con acceso concedido | No |
| Bloqueada por reclamación | Sí, marcada | **No** | **No** | No |

## Reglas de negocio

- `RN-1` Una obra en `DRAFT` **no existe para nadie más que su autor**. No se confirma
  siquiera que exista.
- `RN-2` Quien no puede leer una obra recibe **`404`, nunca `403`**. Confirmar la existencia
  de obra inédita ya es una fuga ([`errors.md`](../../api/conventions/errors.md)).
- `RN-3` Una obra bloqueada por una reclamación la ve **solo su autor**, marcada como
  bloqueada ([`FEAT-MOD-003`](../moderation/FEAT-MOD-003-block-work.md)).
- `RN-4` Un capítulo oculto (`ChapterVisibility: HIDDEN`) lo ve **solo el autor**, y tampoco
  aparece en el índice de los demás.
- `RN-5` Una obra marcada `ADULTS_ONLY` **no se sirve a quien no alcanza la mayoría de edad**,
  y ese filtrado **no es una preferencia**: no se puede desactivar
  ([`FEAT-USR-043`](../user/FEAT-USR-043-content-preferences.md) `RN-4`).
- `RN-6` La edad se deriva de la fecha de nacimiento del paso 1 del onboarding
  ([`FEAT-USR-022`](../user/FEAT-USR-022-onboarding-profile-data.md)). **Una cuenta que
  todavía no la ha declarado se trata como menor**: ante la duda, no se enseña.
- `RN-7` **Leer no exige tener la cuenta activada.** Una cuenta en `PENDING_ACTIVATION` puede
  leer el catálogo y las obras; lo que no puede es escribir
  ([`FEAT-USR-025`](../user/FEAT-USR-025-block-writes-until-activation.md)).
- `RN-8` Leer **no cuesta créditos** y no mueve ninguno.
- `RN-9` La respuesta devuelve el HTML **ya saneado que está almacenado**. No se sanea al
  servir: eso ocurrió al escribir ([`FEAT-WRK-001`](FEAT-WRK-001-create-work-with-editor.md)).
- `RN-10` La respuesta **nunca incluye la identidad del autor más allá de su `UserId` y su
  nombre público**. Ni su correo, ni su fecha de nacimiento.

`RN-2` es la regla que conviene no relajar nunca. Es tentador devolver `403` porque «es más
informativo»: lo es, y lo que informa es de que esa obra existe.

`RN-6` es el tipo de decisión que se toma una vez y se agradece siempre. La alternativa
—tratar como adulto a quien no ha dicho su edad— convierte un paso opcional del onboarding en
una forma de saltarse el filtro.

## La edad viene de otro contexto

`Work` no conoce a las personas: la fecha de nacimiento es de `User` y no debe salir de ahí.
Tampoco puede viajar en el token, que no lleva datos personales
([`decision:0007`](../../decisions/0007-jwt-sessions.md) `RN-4`).

Se resuelve con un **contrato publicado**
([`decision:0014`](../../decisions/0014-published-contracts-between-contexts.md)): `User`
expone «¿esta persona alcanza la mayoría de edad?» y devuelve **un booleano**. No la fecha, no
la edad: la pregunta exacta, y nada con lo que se pueda hacer otra cosa.

Es un contrato que **pregunta y no ordena**, como exige esa decisión.

## Flujos alternativos y errores

| Caso | Respuesta |
|---|---|
| Obra inexistente | `404` con `WORK_NOT_FOUND` |
| Obra en `DRAFT` de otra persona | `404`, **idéntico al anterior** |
| Obra con modalidad restringida y sin acceso | `404`, idéntico |
| Obra bloqueada, lector ajeno | `404`, idéntico |
| Obra `ADULTS_ONLY`, lector menor o sin edad declarada | `404`, idéntico |
| Capítulo oculto, lector ajeno | `404` con `CHAPTER_NOT_FOUND` |
| Sin sesión | `401` |

Que casi todo sea la misma respuesta es el objetivo, no una simplificación: **cualquier
diferencia observable es información sobre obra inédita**.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Ver una obra | `GET /api/v1/works/{workId}` | `getWork` |
| Leer un capítulo | `GET /api/v1/chapters/{chapterId}` | `getChapter` |

## Modelo de datos afectado

Ninguno. Es una consulta.

## Criterios de aceptación

- [ ] El autor lee su propia obra en `DRAFT`.
- [ ] Otra persona recibe `404` sobre una obra en `DRAFT`, **con el mismo cuerpo** que sobre
      una obra inexistente.
- [ ] Una obra `PUBLISHED` con modalidad `PUBLIC` la lee cualquier usuario autenticado.
- [x] Una obra con modalidad `ON_REQUEST` no la lee quien no tiene acceso concedido, **y sí quien lo tiene**.
- [ ] Una cuenta sin activar puede leer.
- [ ] Sin sesión, `401`.
- [ ] Un capítulo oculto no aparece en el índice de quien no es el autor.
- [ ] Una obra `ADULTS_ONLY` no se sirve a quien no ha declarado su fecha de nacimiento.
- [ ] La respuesta no contiene el correo ni la fecha de nacimiento del autor.
- [ ] El HTML devuelto es exactamente el almacenado, sin volver a sanear.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| L-8 | ¿El catálogo y la lectura son públicos sin sesión? | Hoy exigen sesión. Abrirlo después es compatible; cerrarlo, no |
| OB-15 | ¿Cuál es la edad mínima para registrarse? | Distinta de la mayoría de edad, y varía por jurisdicción |
| W-21 | ¿Se registra quién ha leído qué? | Haría falta para métricas y para el muro. No se hace todavía |

## Estado

**Especificación:** `APPROVED` (2026-09-23). El mecanismo de acceso de lectores beta
(`ON_REQUEST`, `PRIVATE`) pertenece a `Reading`, que aún no existe: esta ficha fija que sin
acceso concedido la respuesta es `404`, que es el lado seguro y el que no habrá que cambiar
cuando ese contexto llegue.

**Implementación:** `PARTIAL`.

Hecho: `GET /api/v1/works/{workId}` y `GET /api/v1/chapters/{chapterId}`, con la política de
lectura en un solo sitio (`WorkReadPolicy`) y el contrato de edad publicado por `User`.
Cubierto por `tests/Unit/Work/WorkReadPolicyTest.php` —la regla entera, sin base de datos— y
`tests/Functional/Work/ReadWorkTest.php`, que comprueba que un borrador ajeno y una obra
inexistente responden **byte a byte igual**.

**`ON_REQUEST` y `PRIVATE` ya funcionan** (2026-09-24). Ocurrió lo que esta ficha anticipó:
no hubo que corregir nada, solo **añadir** la puerta que faltaba. `WorkReadPolicy` recibe ahora
un quinto argumento —si quien lee es lector beta de la obra— y lo responde `Reading` por su
contrato publicado, como un booleano.

Hasta entonces, conceder acceso a alguien le dejaba corregir una obra que no podía leer. La
puerta llega con [`FEAT-RDG-001`](../reading/FEAT-RDG-001-become-beta-reader-by-correcting.md),
que es la primera vía por la que alguien obtiene acceso.

**Falta, y depende de contextos que no existen:**

- las preferencias de contenido sensible del lector
  ([`FEAT-USR-043`](../user/FEAT-USR-043-content-preferences.md)) **al abrir la obra**. Ya no
  es que no existan: existen y se aplican en el catálogo y en las recomendaciones (revisado el
  2026-09-26). Lo que falta es aquí, en el momento de leer, que es donde hace falta
  contrastarlas con `RN-5` —un enlace directo a algo que alguien pidió no ver— y no solo
  esconder la obra de una lista;
- `L-8`: hoy leer exige sesión. Abrirlo después es compatible; cerrarlo no lo sería.
