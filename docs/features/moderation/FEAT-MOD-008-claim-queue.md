---
id: FEAT-MOD-008
title: Cola de reclamaciones con filtros y prioridad
context: Moderation
concept: Review
actors: [Moderator]
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - docs/features/moderation/FEAT-MOD-002-review-claim.md
  - conversation:2026-09-25
endpoints:
  - listClaims
events: []
depends_on: [FEAT-MOD-001, FEAT-MOD-002, FEAT-MOD-004]
updated: 2026-09-25
---

# FEAT-MOD-008 — Cola de reclamaciones con filtros y prioridad

## Resumen

La pantalla desde la que se trabaja la moderación: qué hay abierto, cuánto hay, y cómo acotarlo
sin poder reordenarlo.

## La prioridad es la antigüedad, y no es configurable

Es la decisión de la ficha, y el detalle que la hace valer es la ausencia de un parámetro de
ordenación.

En una cola cuyo orden elige quien la trabaja, los casos incómodos se hunden: los largos, los
ambiguos, los de alguien conocido. Y una reclamación sin resolver no es una tarea pendiente,
es **alguien esperando** — normalmente alguien a quien le ha pasado algo desagradable.

Lo que sí se puede es **acotar**, y no es lo mismo: elegir a qué dedicarse esta tarde no es
elegir qué atender antes. Dentro de lo acotado, sigue mandando quien lleva más tiempo
esperando.

## Reglas de negocio

- `RN-1` La cola va **de la más antigua a la más reciente, siempre**. No hay parámetro de
  ordenación. El identificador desempata, y sirve porque es un UUIDv7: crece con el tiempo, así
  que ordena igual que la fecha y hace estable la paginación cuando dos caen en el mismo
  segundo.
- `RN-2` Se puede acotar por **motivo** (`reason`). Son doce, y no piden ni el mismo criterio
  ni, a veces, la misma persona: quien revisa plagio no es quien revisa acoso.
- `RN-3` Y por **clase de objeto** (`targetType`). Revisar textos y revisar conducta son dos
  trabajos distintos.
- `RN-4` La respuesta trae **`total`**, con los mismos filtros que la lista. Es lo que convierte
  una página en una cola: sin la cifra, quien modera ve veinte expedientes y no sabe si detrás
  hay cero o mil, que es justo la información con la que se decide si hoy hay que pedir ayuda.
  Un total que contara otra cosa sería peor que no darlo, porque nadie lo comprueba.
- `RN-5` Solo lo abierto: `PENDING` y `UNDER_REVIEW`. Lo resuelto sale de la lista **y del
  total**.
- `RN-6` **Acotar no abre nada.** La exclusión de `FEAT-MOD-002` `RN-1` —las reclamaciones en
  las que quien mira es parte— se aplica antes que cualquier filtro, y tampoco entran en el
  total. Filtrar por el motivo exacto de una reclamación que te señala no la hace aparecer.
- `RN-7` Un filtro que no es un valor del catálogo **se rechaza** con `422`, no se ignora.
  Devolver la cola entera porque alguien escribió mal un motivo le haría creer que de ese
  motivo hay muchas más de las que hay.
- `RN-8` La cola **no lleva identidades**. El moderador decide sobre lo que se escribió; saber
  de quién es antes de mirarlo solo puede inclinar la decisión.
- `RN-9` Es del backoffice: exige `ROLE_MODERATOR`, resuelto contra la base de datos en cada
  petición ([`decision:0007`](../../decisions/0007-jwt-sessions.md)).
- `RN-10` Una cola vacía responde una lista vacía y un cero, no un error.

## Contrato de API

`GET /api/v1/admin/claims` (`listClaims`) gana tres cosas: dos parámetros de acotado y un
campo.

| Parámetro | Qué acota |
|---|---|
| `reason` | El motivo |
| `targetType` | La clase de objeto reclamado |

| Campo nuevo | Qué dice |
|---|---|
| `total` | Cuántas hay en la cola, con los mismos filtros |

## Eventos

Ninguno. Es una lectura.

## Efectos en créditos

Ninguno.

## Modelo de datos afectado

Ninguno. `idx_claim_status` ya existía.

## Criterios de aceptación

- [x] La cola va de la más antigua a la más reciente.
- [x] Se acota por motivo.
- [x] Se acota por clase de objeto.
- [x] Acotar no reordena.
- [x] El total cuenta lo mismo que la lista.
- [x] El total mira más allá de la página.
- [x] Lo resuelto sale de la lista y del total.
- [x] Acotar no alcanza lo que la cola esconde, ni en la lista ni en el total.
- [x] Un filtro desconocido se rechaza.
- [x] Sin el rol, `403`; sin sesión, `401`.
- [x] Una cola vacía es una lista vacía y un cero.

## Preguntas abiertas

Ninguna.

## Estado

**Especificación:** `APPROVED`.

**Implementación:** `DONE`.
