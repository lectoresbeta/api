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
