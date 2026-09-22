---
id: FEAT-USR-033
title: Nombre de usuario — formato, asignación automática y unicidad
context: User
concept: Profile
actors: [Guest, User]
spec_status: DRAFT
impl_status: TODO
priority: P0
sources:
  - decision:0005
  - conversation:2026-09-22
  - docs/ui/my-profile.md
endpoints: [GET /usernames/{username}/availability]
events: [UserRegistered]
depends_on: [FEAT-USR-001]
updated: 2026-09-22
---

# FEAT-USR-033 — Nombre de usuario: formato, asignación y unicidad

> Decidido en [`decision:0005`](../../decisions/0005-username-with-temporary-aliases.md),
> que revierte la decisión previa de no usar nombre de usuario.

## Resumen

Toda cuenta tiene un `username` único, visible como `@bealonso` en el perfil y parte de su
URL pública. Se asigna **automáticamente al registrarse** a partir del email, de modo que
nadie tiene que elegirlo para empezar a usar la plataforma.

## Asignación automática

Al crear la cuenta:

1. Se toma **la parte del email anterior a la `@`**.
2. Se normaliza según `RN-2`.
3. Si el resultado está libre, se asigna.
4. Si está ocupado, se prueba `nombre_1`, `nombre_2`, `nombre_3`… hasta encontrar uno libre.

```text
pabloblanco@ejemplo.com  →  pabloblanco
                            pabloblanco_1   (si el anterior está ocupado)
                            pabloblanco_2   (y así sucesivamente)
```

## Reglas de negocio

- `RN-1` El nombre de usuario es **único en toda la plataforma**, considerando a la vez los
  nombres en uso y los **alias vigentes** (`FEAT-USR-034`).
- `RN-2` Normalización del nombre generado desde el email:
  - se pasa a minúsculas;
  - se eliminan los caracteres no admitidos;
  - se admiten `a–z`, `0–9` y `_`.
- `RN-3` Longitud entre 3 y 30 caracteres. Si tras normalizar queda por debajo del mínimo, se
  completa con el sufijo numérico hasta alcanzarlo.
- `RN-4` Se compara y se almacena **en minúsculas**. `Pablo` y `pablo` son el mismo nombre.
- `RN-5` Hay una **lista de nombres reservados** que no se pueden asignar ni elegir: términos
  de la plataforma (`admin`, `soporte`, `lectoresbeta`, `api`, `me`, `profile`, `null`…). Ver
  `N-3`.
- `RN-6` La unicidad la garantiza una **restricción de la base de datos**, no la comprobación
  previa. Dos registros simultáneos con el mismo email local deben producir nombres distintos.
- `RN-7` Ante colisión en la escritura, se reintenta con el siguiente sufijo. El bucle tiene
  un límite; superado, el alta falla con error interno en lugar de girar indefinidamente.
- `RN-8` Un nombre cuyo **alias ha caducado está disponible**, aunque la fila del alias siga
  existiendo porque el comando de purga (`FEAT-USR-036`) aún no ha pasado. La disponibilidad
  depende de la caducidad, **nunca** de que la limpieza haya corrido.

`RN-8` es la regla que más fácil se rompe al implementar: basta con comprobar la existencia
de la fila en lugar de su vigencia para que un nombre quede bloqueado días de más.

## El alias del onboarding

El saludo del onboarding —«Casi lo tienes, **beatrizalonso**!»— **ya no es un valor
derivado y desechable**: es el nombre de usuario recién asignado.

Eso cierra la incoherencia que arrastraba `FEAT-USR-022`, donde el saludo mostraba un texto
que no se almacenaba en ningún sitio.

## Contrato de API

| Operación | Método y ruta | `operationId` |
|---|---|---|
| Comprobar disponibilidad | `GET /usernames/{username}/availability` | `checkUsernameAvailability` |

Devuelve si está libre y, si no lo está, **sin decir por qué**: que esté ocupado por una
cuenta o por un alias vigente es información interna de otra persona.

El endpoint es público —hace falta en el formulario de cambio y potencialmente en el
registro— y por tanto necesita límite de peticiones: sin él sirve para enumerar qué nombres
existen (`N-4`).

## Modelo de datos afectado

| Tabla | Cambio |
|---|---|
| `user` | Campo `username`, con **índice único** |

La unicidad se comprueba también contra `username_alias` (`FEAT-USR-034`). Al vivir en dos
tablas, **la restricción de base de datos no puede cubrir sola el caso completo**: la
comprobación cruzada es de aplicación, y la restricción evita los duplicados dentro de cada
tabla. Conviene documentar esa limitación, porque invita a asumir garantías que no existen.

## Eventos

`UserRegistered` incorpora el `username` asignado, que `Community` necesita para sus read
models de tarjetas de autor.

## Criterios de aceptación

- [ ] Registrarse con `pabloblanco@ejemplo.com` asigna el nombre `pabloblanco`.
- [ ] Si ya existe, asigna `pabloblanco_1`; si también, `pabloblanco_2`.
- [ ] Un email con caracteres no admitidos produce un nombre normalizado y válido.
- [ ] Un email cuyo local sea muy corto produce un nombre de al menos 3 caracteres.
- [ ] `Pablo` y `pablo` se consideran el mismo nombre.
- [ ] No se asigna ningún nombre de la lista de reservados.
- [ ] Dos altas simultáneas con el mismo email local producen nombres distintos.
- [ ] Un nombre ocupado por un **alias vigente** no está disponible.
- [ ] Un nombre cuyo **alias ha caducado** está disponible, aunque la fila no se haya borrado.
- [ ] `UserRegistered` incluye el nombre asignado.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| N-1 | ¿Se admite el guion `-` además del guion bajo? | El sufijo usa `_`; admitir ambos complica la normalización |
| N-2 | ¿Se le ofrece al usuario elegir el nombre durante el registro, o siempre se genera? | El diseño actual no lo pide |
| N-3 | ¿Cuál es la lista completa de nombres reservados? | Debe cubrir rutas de la aplicación y marcas propias |
| N-4 | ¿Qué límite de peticiones tiene el endpoint de disponibilidad? | Sin él permite enumerar nombres |
| N-5 | ¿Qué ocurre si dos personas con el mismo email local se registran y una elimina su cuenta? | ¿Se libera el nombre de inmediato o también deja alias? |

`N-5` merece respuesta antes de implementar el borrado de cuenta (`FEAT-USR-013`).

## Estado

**Especificación:** `DRAFT`. El mecanismo está definido; faltan la lista de reservados y el
tratamiento del borrado de cuenta.

**Implementación:** `TODO`.
