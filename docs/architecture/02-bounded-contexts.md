# Bounded contexts

> Estado: `DRAFT`. Los límites son una **propuesta razonada** a partir del material de
> partida. Cambiarlos ahora es barato; después de implementar, no.

## Contextos propuestos

| Contexto | Responsabilidad | Prefijo | Ficha |
|---|---|---|---|
| `User` | Identidad, autenticación, cuenta, perfil público y página de autor | `USR` | [user.md](../bounded-contexts/user.md) |
| `Work` | Obras, fragmentos, contenido, autoría, visibilidad, cuestionario | `WRK` | [work.md](../bounded-contexts/work.md) |
| `Reading` | Quién puede leer qué: accesos, solicitudes, invitaciones, grupos, writing buddies | `RDG` | [reading.md](../bounded-contexts/reading.md) |
| `Feedback` | Comentarios sobre obras, respuestas al cuestionario, valoraciones | `FBK` | [feedback.md](../bounded-contexts/feedback.md) |
| `Community` | Muro, publicaciones, reacciones, suscripciones, mensajes directos, rankings | `COM` | [community.md](../bounded-contexts/community.md) |
| `Credits` | Economía de créditos, aislada por decisión arquitectónica | `CRD` | [credits.md](../bounded-contexts/credits.md) |
| `Moderation` | Reclamaciones, decisiones de moderación, sanciones y backoffice | `MOD` | [moderation.md](../bounded-contexts/moderation.md) |
| `Notification` | Entrega de avisos in-app y por email, y sus preferencias | `NOT` | [notification.md](../bounded-contexts/notification.md) |
| `Shared` | Primitivos técnicos genéricos. **Sin lógica de negocio.** | — | [shared.md](../bounded-contexts/shared.md) |

## Por qué estos límites

**`Work` y `Reading` separados.** `Work` responde *"qué es la obra y qué contiene"*.
`Reading` responde *"quién tiene derecho a leerla"*. Son ciclos de vida distintos: una obra
puede cambiar de modalidad de acceso sin que cambien los accesos ya concedidos, y un acceso
puede revocarse sin tocar la obra. Mantenerlos juntos convertiría `Work` en el contexto que
lo sabe todo.

**`Feedback` separado de `Work`.** El feedback tiene sus propias reglas (quién puede dejarlo,
cuántas veces, cómo se valora) y es el productor de los eventos que mueven la economía de
créditos. Acoplarlo a `Work` haría que cualquier cambio en la crítica tocara el contenido.

**`Credits` aislado.** Regla dura de `AGENTS.md`. El modelo de créditos va a cambiar mucho:
las cantidades del documento de partida son una primera propuesta y el propio documento ya
plantea una fórmula continua alternativa. Aislarlo permite cambiarlo sin tocar nada más.

**`Community` como un único contexto, por ahora.** Muro, mensajes directos y rankings
podrían ser tres contextos. Se mantienen juntos porque el alcance actual es reducido y
separarlos prematuramente multiplicaría la infraestructura sin beneficio. Ver `BC-1`.

## Mapa de contextos

```text
                           ┌──────────┐
                           │   User   │
                           └────┬─────┘
                UserRegistered  │  (evento)
          ┌─────────────────────┼─────────────────────┐
          ▼                     ▼                     ▼
    ┌──────────┐          ┌──────────┐         ┌─────────────┐
    │   Work   │─────────▶│ Reading  │         │  Community  │
    └────┬─────┘  Work    └────┬─────┘         └──────┬──────┘
         │      Published      │ AccessGranted        │
         │                     ▼                      │
         │              ┌────────────┐                │
         └─────────────▶│  Feedback  │                │
           (contexto de └─────┬──────┘                │
            la obra)          │                       │
                              │ FeedbackSubmitted     │
                              │ FeedbackRatedPositively
                              ▼                       │
                        ┌───────────┐                 │
                        │  Credits  │◀────────────────┘
                        └─────┬─────┘  InvitedUserParticipated
                              │
                              │ CreditsSpent / CreditsAdded
                              ▼
                       ┌──────────────┐
                       │ Notification │◀── (todos los contextos)
                       └──────────────┘
```

Todas las flechas son **eventos de integración asíncronos**. No hay ninguna llamada
síncrona entre contextos en el diseño actual.

## Relaciones entre contextos

| Origen | Destino | Mecanismo | Motivo |
|---|---|---|---|
| `User` | todos | Evento `UserRegistered` | Crear la cuenta de créditos, el perfil, las preferencias |
| `Work` | `Reading` | Evento `WorkPublished`, `WorkDeleted` | Saber qué obras existen y cerrar lo pendiente de las que dejan de existir |
| `Reading` | `Feedback` | Evento `BetaReaderAccessGranted` / `Revoked` | Saber quién puede comentar |
| `Work` | `Credits` | Eventos `ChapterContentUpdated`, `QuestionnaireUpdated` | Las palabras del capítulo y las exigidas, que fijan el precio |
| `Feedback` | `Credits` | Eventos `CorrectionStarted`, `FeedbackSubmitted` | Hechos que mueven créditos |
| `Credits` | `Feedback`, `Community` | Evento `CorrectionTipped` | Que una corrección fue propinada, y cuánto pesa en la reputación de quien la escribió |
| `Credits` | `Feedback` / `Work` | Evento `ChapterCorrectabilityChanged` | Saber si un capítulo admite correcciones ahora |
| `Credits` | `Work` | Evento `ChapterPriceChanged` | Pintar la insignia del catálogo con lo que gana quien corrija |
| `Moderation` | `Credits`, `Work`, `User` | Evento `ClaimUpheld` | Aplicar, cada uno en su modelo, lo que la decisión significa |
| cualquiera | `Notification` | Eventos de negocio | Avisar al usuario |
| `Feedback`, `Community` | `Community` (rankings) | Eventos | Alimentar los read models de ranking |
| `Reading` | `Work` | **Contrato** `WorkAccessBriefs` | De quién es una obra, cómo está abierta y si existe para quien pregunta |
| `Work` | `Reading` | **Contrato** `BetaReaderAccessCheck` | Si alguien es lector beta, para decidir si puede leer |
| `Feedback` | `User` | **Contrato** `AuthorAudience` | El techo de privacidad del autor sobre quién puede comentar sus textos |

> Las dos últimas filas son **el primer ciclo del sistema**: `Work` y `Reading` se preguntan
> el uno al otro. Es tolerable porque las dos direcciones preguntan, cada una responde sobre
> estado propio y ninguna llama a la otra mientras responde. Los motivos, las alternativas y
> la regla que lo sostiene están en
> [`decision:0015`](../decisions/0015-work-and-reading-ask-each-other.md). Deptrac **no lo
> ve**, porque todos los contratos viven en una sola capa: un segundo ciclo tendría que
> argumentarse contra esa decisión, no contra la herramienta.

> El flujo de «recibir feedback cuesta créditos» es el más delicado del sistema: implica
> `Feedback` y `Credits` en contextos separados con comunicación asíncrona. Está resuelto en
> [`decision:0006`](../decisions/0006-credit-system.md) y detallado en
> [credits.md](../bounded-contexts/credits.md).
>
> `ClaimUpheld` es el evento que más contextos moviliza a la vez —`Credits`, `Work` y `User`
> reaccionan al mismo hecho—, y precisamente por eso **no lleva instrucciones**: dice qué se ha
> estimado, no qué debe hacer cada uno.

## Lo que un contexto nunca hace

- Inyectar el repositorio de otro contexto.
- Consultar las tablas de otro contexto.
- Instanciar entidades de otro contexto.
- Reutilizar el servicio de aplicación de otro contexto.
- Llamar a `Credits` para sumar o restar créditos.

## Preguntas abiertas

| # | Pregunta | Impacto |
|---|---|---|
| BC-1 | ¿`Community` debe dividirse en `Feed`, `Messaging` y `Ranking`? | Si los rankings crecen o los MD exigen tiempo real, sí |
| BC-2 | ¿La búsqueda de obras y autores necesita un contexto `Discovery` con su propio índice? | Depende del volumen y de si se usa un motor de búsqueda |
| BC-3 | ¿La autenticación (OAuth, tokens) debería separarse de `User` como `Identity`? | Si se añaden más proveedores o SSO |
| BC-4 | ¿Dónde vive la clasificación por extensión del texto: `Work` la calcula y `Credits` la consume? | Propuesto así; confirmar |
