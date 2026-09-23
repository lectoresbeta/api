---
id: FEAT-WRK-007
title: Configurar la modalidad de acceso de lectores beta
context: Work
concept: Manuscript
actors: [Writer]
spec_status: APPROVED
impl_status: PARTIAL
priority: P0
sources:
  - docs/bounded-contexts/work.md
  - docs/features/work/FEAT-WRK-016-work-status.md
endpoints: [PUT /works/{workId}/access-mode]
events: [WorkAccessModeChanged]
depends_on: [FEAT-WRK-001]
updated: 2026-09-23
---

# FEAT-WRK-007 — Modalidad de acceso de lectores beta

## Resumen

El autor decide **quién puede acercarse a su obra**: cualquiera, quien lo pida y él acepte, o
solo a quien invite.

| `BetaReaderAccessMode` | Quién entra |
|---|---|
| `PUBLIC` | Cualquier usuario |
| `ON_REQUEST` | Quien lo solicite y el autor acepte |
| `PRIVATE` | Solo a quien el autor invite |

Es un eje distinto del estado de la obra: el estado responde «¿se puede corregir?»
([`FEAT-WRK-016`](FEAT-WRK-016-work-status.md)) y esta modalidad responde «¿por parte de
quién?».

## Por qué nace en `ON_REQUEST` y no en `PUBLIC`

Una obra se crea `ON_REQUEST` ([`FEAT-WRK-001`](FEAT-WRK-001-create-work-with-editor.md)
`RN-5`) y **eso es deliberado**: el valor por defecto de una decisión de exposición debe ser
el que menos expone. Quien quiera abrirla del todo lo hace de forma explícita, y esa
explicitud es justamente lo que convierte un descuido en una decisión.

## Reglas de negocio

- `RN-1` Solo el autor cambia la modalidad.
- `RN-2` Se puede cambiar en cualquier estado de la obra, incluido `DRAFT`. Prepararla antes
  de publicar es lo normal.
- `RN-3` Cambiar la modalidad **no revoca los accesos ya concedidos**
  (`docs/bounded-contexts/work.md` `RN-6`). Quien ya estaba dentro sigue dentro: cortar a
  alguien a mitad de una corrección le haría perder su trabajo.
- `RN-4` Cambiarla exige la cuenta activada (`FEAT-USR-025`).
- `RN-5` El **ajuste global de privacidad del usuario es un techo**: una obra puede ser más
  restrictiva que el perfil, **nunca más permisiva**
  ([`FEAT-USR-038`](../user/FEAT-USR-038-privacy-settings.md) `S-14`). Las dos se guardan por
  separado y la autorización evalúa ambas, de modo que relajar el perfil devuelve a cada obra
  la modalidad que había elegido.
- `RN-6` Una modalidad desconocida se rechaza; no se ignora en silencio.

`RN-3` es la que conviene no olvidar al implementar `Reading`: la modalidad gobierna **quién
puede entrar a partir de ahora**, no quién está dentro.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Cambiar la modalidad | `PUT /api/v1/works/{workId}/access-mode` | `setAccessMode` |

## Eventos

| Evento | Cuándo | Consumidores |
|---|---|---|
| `WorkAccessModeChanged` | Cambia la modalidad | `Reading` |

## Criterios de aceptación

- [ ] El autor cambia la modalidad de su obra y se refleja al leerla.
- [ ] Otra persona recibe `404`, igual que ante una obra inexistente.
- [ ] Una modalidad desconocida devuelve `422`.
- [ ] Con la cuenta sin activar, `403`.
- [ ] Se puede cambiar estando la obra en `DRAFT`.
- [ ] Una obra `PUBLIC` y publicada la puede leer cualquier usuario autenticado.
- [ ] Se publica `WorkAccessModeChanged`.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| W-22 | ¿El techo de `FEAT-USR-038` se evalúa al leer o al conceder acceso? | Pertenece a `Reading`, que aún no existe |

## Estado

**Especificación:** `APPROVED` (2026-09-23). Lo que esta ficha añade sobre lo que ya estaba
disperso en la ficha del contexto es `RN-2` —se puede preparar antes de publicar— y dejar por
escrito que el valor por defecto es el que menos expone, y por qué.

**Implementación:** `PARTIAL`.

Hecho: `PUT /api/v1/works/{workId}/access-mode`, con el rechazo de modalidades desconocidas y
la publicación de `WorkAccessModeChanged`. Cubierto por
`tests/Functional/Work/ReadWorkTest.php`.

Apareció al implementar `FEAT-WRK-004`: sin esta pieza, **publicar una obra no la abre a
nadie**. Nace `ON_REQUEST`, y hasta que exista `Reading` eso significa que solo la lee su
autor. Era el eslabón que faltaba para que publicar sirviera de algo.

**Falta:**

- `RN-3` y `RN-5` no se pueden comprobar todavía: no hay accesos que revocar ni ajuste global
  de privacidad ([`FEAT-USR-038`](../user/FEAT-USR-038-privacy-settings.md)) con el que
  contrastar el techo. La regla está escrita para quien implemente `Reading`.
