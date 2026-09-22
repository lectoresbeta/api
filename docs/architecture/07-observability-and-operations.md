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

## Tareas programadas

Procesos que se ejecutan por calendario, no por petición ni por evento.

| Tarea | Comando | Frecuencia | Qué pasa si falla |
|---|---|---|---|
| Purga de alias de nombre de usuario caducados | `app:user:purge-expired-username-aliases` | **Diaria** | Nada funcional: los alias caducados ya no resuelven ni ocupan nombre. Solo se acumulan filas. Ver [`FEAT-USR-036`](../features/user/FEAT-USR-036-purge-expired-aliases.md) |

```cron
# Purga de alias de nombre de usuario caducados — a diario a las 04:15 UTC
15 4 * * *  php /app/bin/console app:user:purge-expired-username-aliases --no-interaction
```

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

- Endpoint de salud que verifique el acceso a PostgreSQL y a RabbitMQ.
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
