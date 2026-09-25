---
id: FEAT-USR-036
title: Purga programada de alias de nombre de usuario caducados
context: User
concept: Profile
actors: []
spec_status: APPROVED
impl_status: DONE
priority: P2
sources:
  - decision:0005
  - conversation:2026-09-22
endpoints: []
events: []
depends_on: [FEAT-USR-034]
updated: 2026-09-25
---

# FEAT-USR-036 — Purga programada de alias caducados

## Resumen

Comando de consola que elimina los alias de nombre de usuario ya caducados. Se programa en
el cron para ejecutarse **una vez al día**.

Es **housekeeping, no lógica de negocio**: un alias caducado ya ha dejado de resolver y ya ha
dejado de ocupar el nombre. El comando solo retira filas que no sirven para nada.

Atiende a los dos orígenes de alias —cambio de nombre y borrado de cuenta— sin distinguirlos.
Que no necesite saber de dónde viene cada uno es señal de que el mecanismo de caducidad está
bien colocado.

El borrado es **real**: se elimina la fila y no queda rastro. Sin marca de borrado, sin tabla
de archivo y sin histórico.

## Por qué el comando no es una precondición

Es el punto que más fácil se malinterpreta, así que conviene dejarlo explícito:

| Comportamiento | Lo decide | **No** lo decide |
|---|---|---|
| Un alias deja de resolver | Su `expires_at` | El comando |
| Un nombre vuelve a estar disponible | Su `expires_at` | El comando |
| La fila desaparece de la tabla | El comando | — |

Si la disponibilidad de un nombre dependiera de que el comando hubiera pasado, un fallo del
cron mantendría nombres bloqueados sin que nadie se enterase, y el sistema daría respuestas
distintas según la hora del día. Ver `FEAT-USR-033` `RN-8` y `FEAT-USR-035` `RN-2`.

## El comando

```text
src/User/Profile/Infrastructure/Console/PurgeExpiredUsernameAliasesCommand.php
```

Nombre: `lectoresbeta:user:purge-expired-username-aliases`. La ficha proponía el prefijo
`app:`; se usa `lectoresbeta:` para no estrenar un segundo convenio junto al que ya existe
(`lectoresbeta:admin:grant`).

La regla de qué está caducado se resuelve en `Application`, en
`PurgeExpiredAliases`, y el comando solo la invoca:

```text
src/User/Profile/Application/Service/PurgeExpiredAliases.php
src/User/Profile/Infrastructure/Console/PurgeExpiredUsernameAliasesCommand.php
```

| Aspecto | Comportamiento |
|---|---|
| Qué borra | Alias cuyo `expires_at` es anterior al momento actual |
| Cómo borra | **Borrado real**: se elimina la fila. Ni marca de borrado, ni tabla de archivo, ni histórico |
| Idempotencia | Ejecutarlo dos veces seguidas no tiene efecto adicional |
| Concurrencia | Dos ejecuciones simultáneas no se corrompen; el borrado es por condición, no por lectura previa |
| Por lotes | Lotes de 500, confirmados uno a uno, repetidos hasta agotar con un tope de 100 rondas por ejecución. El tope no es cuántos se borran en total: lo que sobre se borra mañana |
| Salida | Registra cuántos alias ha borrado |
| Simulación | Admite `--dry-run` para contar sin borrar |
| Código de salida | `0` si termina bien; distinto de `0` si falla, para que el cron pueda alertar |

El comando vive en `Infrastructure`, como cualquier adaptador. **La regla de qué está
caducado es de `Domain`**: el comando la invoca, no la reimplementa en una consulta SQL
suelta.

## Programación

Entrada de cron propuesta, a diario y en hora de poco tráfico:

```cron
# Purga de alias de nombre de usuario caducados — a diario a las 04:15 UTC
15 4 * * *  php /app/bin/console lectoresbeta:user:purge-expired-username-aliases --no-interaction
```

| Aspecto | Decisión |
|---|---|
| Frecuencia | Diaria. La caducidad ya gobierna el comportamiento, así que no hace falta más |
| Hora | Franja de bajo tráfico |
| Entorno | Producción. También conviene en staging para detectar fallos antes |
| Si se salta un día | No pasa nada: los alias siguen sin resolver y sin ocupar. Solo se acumulan filas |

Que saltarse una ejecución sea inocuo es consecuencia directa del diseño, y es lo que
convierte este proceso en uno que no hay que vigilar de cerca.

## Reglas de negocio

- `RN-1` Borra únicamente alias con `expires_at` estrictamente anterior al momento actual.
- `RN-2` No toca ningún nombre de usuario en uso.
- `RN-3` Es idempotente y seguro de ejecutar varias veces.
- `RN-4` No modifica ninguna cuenta: solo elimina filas de `username_alias`.
- `RN-4c` El borrado es **real**: `DELETE` de la fila. No hay borrado lógico, ni copia a una
  tabla de histórico, ni rastro posterior. Un alias purgado deja de existir.
- `RN-4b` **Trata igual todos los alias, cualquiera que sea su origen.** Da lo mismo que
  vengan de un cambio de nombre (`USERNAME_CHANGED`) o del borrado de una cuenta
  (`ACCOUNT_DELETED`): lo único que mira es `expires_at`.
- `RN-5` Registra el número de filas borradas.
- `RN-6` Un fallo no deja la tabla en estado inconsistente: el borrado por lotes se confirma
  por lote.

## Criterios de aceptación

- [x] Borra los alias caducados y deja intactos los vigentes.
- [x] Borra por igual los de cambio de nombre y los de cuenta eliminada.
- [x] Borra un alias de cuenta eliminada aunque no quede fila de usuario asociada.
- [x] La fila desaparece de la tabla: no queda marcada como borrada ni copiada a otro sitio.
- [x] Ejecutarlo dos veces seguidas no produce error ni efecto adicional.
- [x] No modifica ningún `username` en uso.
- [x] Con `--dry-run` informa de cuántos borraría y no borra ninguno.
- [x] Registra el recuento de filas eliminadas.
- [x] Devuelve código de salida distinto de `0` si falla. Una excepción sin capturar sale con
      `1`, que es lo que hace falta para que el programador pueda alertar.
- [x] **Antes de ejecutarlo**, un nombre con alias caducado ya está disponible.
- [x] **Antes de ejecutarlo**, un alias caducado ya devuelve `404` al resolver.

Los dos últimos criterios son los que demuestran que el comando no es una precondición del
comportamiento correcto. Son los dos primeros casos de
`tests/Functional/User/PurgeExpiredAliasesTest.php`, y son los que más falta hacen: no
comprueban lo que el comando hace, sino lo que no hace falta que haga.

## Decisiones tomadas al implementar

| Decisión | Por qué |
|---|---|
| El lote se pregunta a la entidad (`isInForceAt`) antes de borrar cada fila | La consulta ya filtra por fecha. Volver a preguntárselo al dominio no cuesta nada y protege de la única forma en que esto podría hacer daño: que la consulta y la regla dejen de decir lo mismo |
| `--dry-run` cuenta un lote y para | Sin borrar, la ronda siguiente devolvería exactamente las mismas filas y no acabaría nunca. Con acumulación por encima de 500 informa de un mínimo, no del total |
| Tope de 100 rondas | Para que un fallo raro no deje el comando dando vueltas. Lo que sobre se borra en la ejecución siguiente, que es inocuo |
| El recuento se informa también cuando es cero | Un cero dice que el comando corrió, que es justo lo que alguien busca cuando sospecha que dejó de correr |

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| N-14 | ¿Se conserva algún registro histórico de los alias borrados? | **Resuelta:** no. El borrado es real y no deja rastro (`RN-4c`) |
| N-18 | ¿Conviene una métrica de alias vigentes y caducados sin purgar? | Detectaría que la tarea dejó de ejecutarse |
| N-15 | ¿Cómo se programa realmente: cron del sistema, Symfony Scheduler o el programador de la plataforma de despliegue? | Sigue abierta, y a propósito: es decisión de operación, y atarla al código significaría que cambiar la hora es un despliegue. El comando queda listo para que lo llame cualquiera de las tres |
| N-16 | ¿Hay alerta si el comando falla varios días seguidos? | No es urgente, pero la acumulación silenciosa acaba notándose |

## Estado

**Especificación:** `APPROVED` (2026-09-24). Las preguntas abiertas que quedan no
afectan al modelo, al contrato ni a ninguna regla de negocio: se resuelven durante la
implementación.

**Implementación:** `DONE` (2026-09-25). `N-15`, `N-16` y `N-18` siguen abiertas: las tres son
de operación —cómo se programa, si se alerta, si se mide— y ninguna afecta al comportamiento
del comando.
