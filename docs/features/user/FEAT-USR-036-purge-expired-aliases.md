---
id: FEAT-USR-036
title: Purga programada de alias de nombre de usuario caducados
context: User
concept: Profile
actors: []
spec_status: DRAFT
impl_status: TODO
priority: P2
sources:
  - decision:0005
  - conversation:2026-09-22
endpoints: []
events: []
depends_on: [FEAT-USR-034]
updated: 2026-09-22
---

# FEAT-USR-036 — Purga programada de alias caducados

## Resumen

Comando de consola que elimina los alias de nombre de usuario ya caducados. Se programa en
el cron para ejecutarse **una vez al día**.

Es **housekeeping, no lógica de negocio**: un alias caducado ya ha dejado de resolver y ya ha
dejado de ocupar el nombre. El comando solo retira filas que no sirven para nada.

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

Nombre propuesto: `app:user:purge-expired-username-aliases`.

| Aspecto | Comportamiento |
|---|---|
| Qué borra | Alias cuyo `expires_at` es anterior al momento actual |
| Idempotencia | Ejecutarlo dos veces seguidas no tiene efecto adicional |
| Concurrencia | Dos ejecuciones simultáneas no se corrompen; el borrado es por condición, no por lectura previa |
| Por lotes | Borra en lotes acotados para no bloquear la tabla si hay acumulación |
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
15 4 * * *  php /app/bin/console app:user:purge-expired-username-aliases --no-interaction
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
- `RN-5` Registra el número de filas borradas.
- `RN-6` Un fallo no deja la tabla en estado inconsistente: el borrado por lotes se confirma
  por lote.

## Criterios de aceptación

- [ ] Borra los alias caducados y deja intactos los vigentes.
- [ ] Ejecutarlo dos veces seguidas no produce error ni efecto adicional.
- [ ] No modifica ningún `username` en uso.
- [ ] Con `--dry-run` informa de cuántos borraría y no borra ninguno.
- [ ] Registra el recuento de filas eliminadas.
- [ ] Devuelve código de salida distinto de `0` si falla.
- [ ] **Antes de ejecutarlo**, un nombre con alias caducado ya está disponible.
- [ ] **Antes de ejecutarlo**, un alias caducado ya devuelve `404` al resolver.

Los dos últimos criterios son los que demuestran que el comando no es una precondición del
comportamiento correcto.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| N-14 | ¿Se conserva algún registro histórico de los alias borrados? | Útil para investigar suplantaciones (`N-10`) |
| N-15 | ¿Cómo se programa realmente: cron del sistema, Symfony Scheduler o el programador de la plataforma de despliegue? | Depende de `O-1`, aún sin decidir |
| N-16 | ¿Hay alerta si el comando falla varios días seguidos? | No es urgente, pero la acumulación silenciosa acaba notándose |

## Estado

**Especificación:** `DRAFT`. El comando está definido; falta elegir el mecanismo real de
programación.

**Implementación:** `TODO`.
