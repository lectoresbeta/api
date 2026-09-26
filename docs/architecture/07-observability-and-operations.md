# Observabilidad y operación

> Estado: `DRAFT`. Se concretará cuando exista infraestructura desplegada.

## Entornos

| Entorno | Propósito |
|---|---|
| `dev` | Desarrollo local |
| `test` | Ejecución de la suite de tests |
| `prod` | Producción |

La configuración de cada entorno está claramente separada. Los valores específicos viven en
variables de entorno, nunca en el repositorio. Ninguna variable de entorno se lee desde
`Domain` ni desde `Application`: `Infrastructure` las traduce a dependencias tipadas.

Toda variable de entorno nueva se documenta al introducirla.

## Logs

- Logging estructurado a través del sistema configurado en Symfony.
- **Nunca** se registran: contenido de obras, contenido de feedback, mensajes directos,
  contraseñas, tokens ni datos personales innecesarios.
- Los errores inesperados se registran con contexto suficiente para diagnosticar, sin
  exponerlo al cliente de la API.
- Se registran identificadores (`workId`, `userId`), no contenidos.

## Trazabilidad de eventos

Cada evento de integración lleva `eventId`. Ese identificador debe poder seguirse desde la
publicación hasta el consumo para poder responder a *"¿por qué este usuario tiene este
saldo?"*. Es un requisito de auditoría de `Credits`, no una comodidad.

## Métricas a vigilar

Derivadas de los principios del sistema de créditos —justo, equilibrado, dinámico,
sostenible— el propio documento de partida exige monitorización constante:

| Métrica | Por qué importa |
|---|---|
| Créditos emitidos frente a créditos gastados, por periodo | Detecta inflación o deflación de la economía |
| Distribución del saldo entre usuarios | Detecta acumuladores y usuarios bloqueados sin saldo |
| Número de usuarios con saldo insuficiente para recibir feedback | Indicador directo de fricción en el producto |
| Ratio feedback dado / feedback recibido por usuario | Mide la reciprocidad real |
| Porcentaje de feedback valorado positivamente | Mide la calidad, no solo el volumen |
| Mensajes en la cola de fallos | Salud de la mensajería |
| Eventos deduplicados | Frecuencia real de entregas duplicadas |
| Alias de nombre de usuario caducados sin purgar | Indica que la tarea diaria dejó de ejecutarse |
| Filas de deduplicación con más de 180 días | Lo mismo, para la purga de `credits_ctx.processed_event` |

## Tareas programadas

Procesos que se ejecutan por calendario, no por petición ni por evento.

| Tarea | Comando | Frecuencia | Qué pasa si falla |
|---|---|---|---|
| Purga de alias de nombre de usuario caducados | `lectoresbeta:user:purge-expired-username-aliases` | **Diaria** | Nada funcional: los alias caducados ya no resuelven ni ocupan nombre. Solo se acumulan filas. Ver [`FEAT-USR-036`](../features/user/FEAT-USR-036-purge-expired-aliases.md) |
| Purga de manuscritos subidos sin confirmar | `lectoresbeta:work:purge-expired-manuscript-uploads` | **Diaria** | Nada funcional: una subida caducada ya no se puede confirmar. Se acumulan filas y ficheros |
| Purga del registro de deduplicación | `lectoresbeta:credits:purge-processed-events` | **Diaria** | Nada funcional: una fila caducada no hace daño mientras esté. Pero la tabla crece con **cada evento que recibe `Credits`**, y es la clase de crecimiento que no molesta durante dos años y luego obliga a una migración con la aplicación parada. Ver [`FEAT-CRD-011`](../features/credits/FEAT-CRD-011-deduplicate-integration-events.md) `RN-7` |
| Reparto del cupo de descubiertos | `lectoresbeta:credits:grant-overdrafts` | **Por periodo** | Ver [`FEAT-CRD-019`](../features/credits/FEAT-CRD-019-overdraft-correction.md) |

```cron
# Purga de alias de nombre de usuario caducados — a diario a las 04:15 UTC
15 4 * * *  php /app/bin/console lectoresbeta:user:purge-expired-username-aliases --no-interaction

# Purga de manuscritos subidos sin confirmar — a diario a las 04:25 UTC
25 4 * * *  php /app/bin/console lectoresbeta:work:purge-expired-manuscript-uploads --no-interaction

# Purga del registro de deduplicación — a diario a las 04:35 UTC
35 4 * * *  php /app/bin/console lectoresbeta:credits:purge-processed-events --no-interaction
```

Las tres a horas distintas y no a la misma: son borrados por lotes sobre tablas que la
aplicación sigue leyendo, y solaparlos no aporta nada.

Reglas para cualquier tarea programada del proyecto:

- **Idempotente.** Ejecutarla dos veces no debe producir efectos distintos de ejecutarla una.
- **Saltarse una ejecución no debe cambiar el comportamiento del sistema.** Si una regla de
  negocio depende de que la tarea haya corrido, la regla está mal colocada: debe derivarse del
  dato, no de la limpieza. La caducidad de los alias es el ejemplo: gobierna el
  comportamiento por sí sola y el comando solo retira filas.
- **Por lotes**, para no bloquear tablas si hay acumulación.
- **Registra cuánto ha hecho**, para poder detectar que dejó de hacerlo.
- **Código de salida distinto de `0` al fallar**, para que el programador pueda alertar.
- Vive en `Infrastructure/Console`; la regla de negocio que aplica es de `Domain`.

Cómo se programan realmente —cron del sistema, Symfony Scheduler o el programador de la
plataforma de despliegue— depende de `O-1`, todavía sin decidir.

## Salud del sistema

**Implementado.** Dos rutas, y la diferencia entre ellas importa:

| Ruta | Responde a | Qué toca |
|---|---|---|
| `GET /health/live` | ¿Está vivo el proceso? | Nada |
| `GET /health` | ¿Puede atender peticiones? | PostgreSQL y RabbitMQ |

**Conectar una sonda de vida a `/health` es la forma clásica de convertir un
hipo de la base de datos en el reinicio simultáneo de todos los contenedores**,
que es como un incidente pequeño se vuelve una caída. De ahí que sean dos.

Las dos viven **fuera de `/api/v1`**. La dirección de una sonda acaba escrita
en orquestadores, balanceadores y sistemas de monitorización, que actualiza
gente que no lee las notas de versión: no puede moverse cuando cambie la
versión de la API.

### Qué devuelve

```json
{
  "status": "up",
  "checkedAt": "2026-09-24T10:00:00+00:00",
  "checks": {
    "database":       { "status": "up", "durationMs": 1.42 },
    "message_broker": { "status": "up", "durationMs": 3.08 }
  }
}
```

`200` si todo responde, `503` si algo no. El veredicto es **pesimista**: una
sola dependencia caída basta. Un endpoint que responde «casi bien» obliga a
quien lo lee a decidir qué significa eso, y a las tres de la mañana nadie
quiere decidir nada.

Un chequeo puede salir `skipped` cuando no aplica —en test el transporte es
in-memory y no hay RabbitMQ al que llegar—. No cuenta como fallo: la
diferencia entre «funciona» y «no se ha preguntado» es justo lo que necesita
saber quien lee el informe.

### No dice por qué ha fallado

El endpoint es público, porque tiene que responder antes de que nadie pueda
autenticarse. Eso obliga a que el cuerpo **no contenga el mensaje del error**:
«conexión rechazada en 10.0.3.7:5432» le regala la topología del sistema a
cualquiera que pase por ahí. El motivo va al log, que es donde lo necesita
quien tiene que arreglarlo.

### Por qué se comprueba RabbitMQ

Nada en la API espera al broker —[`decision:0006`](../decisions/0006-credit-system.md)
eliminó el último caso en que un contexto bloqueaba a otro—, así que un
RabbitMQ caído no rompe ni una sola petición. Rompe todo lo que viene después:
los créditos de bienvenida no se abonan, el correo de activación no sale, y el
único síntoma visible es una cola que crece. Es exactamente el tipo de fallo
que justifica una sonda.

El chequeo abre conexión y canal, y **no declara nada**. `countMessages()`
sería la sonda evidente y es la equivocada: el puente de Symfony la implementa
con `declareQueue()`, así que preguntar **crearía** la cola por la que
pregunta.

### Añadir una dependencia

Implementar `Shared\Domain\Health\HealthCheck` en `Infrastructure` y nada
más: el contenedor los etiqueta y el caso de uso los recoge. El contrato exige
que un chequeo **nunca lance**; un fallo es un resultado `DOWN`, no una
excepción, porque un endpoint de salud que responde 500 no dice qué se ha roto.

### Lo que falta

- Alerta sobre acumulación de mensajes en la cola de fallos.
- Alerta sobre crecimiento anómalo del retraso en las colas.

## Preguntas abiertas

| # | Pregunta |
|---|---|
| O-1 | ¿Qué plataforma de despliegue se usa? |
| O-2 | ¿Hay entorno de staging además de los tres anteriores? |
| O-3 | ¿Qué herramienta de agregación de logs y métricas? |
| O-4 | ¿Política de copias de seguridad y de recuperación, dada la criticidad del contenido? |
| O-5 | ¿Cómo se programan las tareas periódicas y quién avisa si dejan de ejecutarse? |
