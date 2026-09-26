---
id: FEAT-USR-033
title: Nombre de usuario — formato, asignación automática y unicidad
context: User
concept: Profile
actors: [Guest, User]
spec_status: APPROVED
impl_status: PARTIAL
priority: P0
sources:
  - decision:0005
  - conversation:2026-09-22
  - docs/ui/my-profile.md
endpoints: [GET /usernames/{username}/availability]
events: [UserRegistered]
depends_on: [FEAT-USR-001]
updated: 2026-09-24
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
- `RN-8b` Un nombre **no** queda disponible al eliminarse la cuenta que lo tenía: se bloquea
  30 días como alias (`FEAT-USR-034` `RN-13`). Pasado ese plazo sí.
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

- [x] Registrarse con `pabloblanco@ejemplo.com` asigna el nombre `pabloblanco`.
- [x] Si ya existe, asigna `pabloblanco_1`; si también, `pabloblanco_2`.
- [x] Un email con caracteres no admitidos produce un nombre normalizado y válido.
- [x] Un email cuyo local sea muy corto produce un nombre de al menos 3 caracteres.
- [x] `Pablo` y `pablo` se consideran el mismo nombre.
- [x] No se asigna ningún nombre de la lista de reservados.
- [ ] Dos altas simultáneas con el mismo email local producen nombres distintos. *Lo sostiene el índice único, no una prueba: reproducir la carrera pide dos conexiones a la vez.*
- [x] Un nombre ocupado por un **alias vigente** no está disponible.
- [x] Un nombre cuyo **alias ha caducado** está disponible, aunque la fila no se haya borrado.
- [x] El nombre de una cuenta eliminada hace menos de 30 días **no** está disponible. *El alias bloquea sea cual sea su motivo; lo que no existe todavía es el borrado que lo crea (`FEAT-USR-013`).*
- [x] `UserRegistered` incluye el nombre asignado.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| N-1 | ¿Se admite el guion `-` además del guion bajo? | El sufijo usa `_`; admitir ambos complica la normalización |
| N-2 | ¿Se le ofrece al usuario elegir el nombre durante el registro, o siempre se genera? | El diseño actual no lo pide |
| N-3 | ¿Cuál es la lista completa de nombres reservados? | Debe cubrir rutas de la aplicación y marcas propias |
| N-4 | ¿Qué límite de peticiones tiene el endpoint de disponibilidad? | Sin él permite enumerar nombres |
| N-5 | ¿Qué ocurre si dos personas con el mismo email local se registran y una elimina su cuenta? | **Resuelta:** el nombre queda bloqueado 30 días y solo después vuelve a estar libre (`FEAT-USR-034` `RN-13`) |

El borrado de cuenta no libera el nombre hasta pasados 30 días, así que `pabloblanco_1`
seguiría siendo `pabloblanco_1` durante ese mes aunque el titular de `pabloblanco` se diera
de baja. Conviene tenerlo presente al especificar `FEAT-USR-013`.

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `PARTIAL` (2026-09-24). La asignación automática funciona desde el
registro: normalización, sufijo numérico, unicidad frente a nombres en uso **y frente a alias
vigentes**, que es la parte que ninguna restricción de base de datos puede cubrir porque
abarca dos tablas.

La lista de reservados (`RN-5`) se aplica desde `FEAT-USR-034`, y también al asignar: sin eso,
registrarse con `soporte@…` bastaba para llamarse `soporte`, que es exactamente la
suplantación que la lista existe para evitar. El sufijo resuelve el caso sin dejar a nadie sin
cuenta.

**Falta `GET /usernames/{username}/availability`.** Hoy no hace falta: el nombre no se elige
al registrarse, y al cambiarlo la respuesta del cambio ya distingue ocupado de reservado. Su
día habrá que decidir antes `N-4`, porque un endpoint de disponibilidad sin límite de
frecuencia permite enumerar nombres.

`N-3` sigue abierta: la lista actual es corta y está formada por lo que alguien podría
confundir con una voz oficial —la plataforma, quien atiende, el correo del sistema, y las
palabras que en una interfaz se leen como un estado y no como alguien—. Se compara **exacta y
en minúsculas**: `administradora` es un nombre legítimo, `admin` no, y una comparación por
prefijo secuestraría nombres reales.
