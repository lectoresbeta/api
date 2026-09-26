# 0002 — `Credits` es un bounded context aislado accesible solo por eventos

- **Estado:** Aceptada
- **Fecha:** 2026-09-21
- **Afecta a:** `Credits`, `Feedback`, `Work`, `User`, `Reading`

## Contexto

El sistema de créditos es el mecanismo central de Lectores Beta: hace que recibir feedback
exija haberlo dado antes. Casi cualquier acción relevante lo toca —registrarse, comentar,
valorar un comentario, invitar a alguien, recibir crítica— lo que lo convierte en el
candidato natural a acabar siendo un servicio que todos llaman.

Además, va a cambiar mucho. El documento de partida declara que el sistema debe ser
"dinámico", revisado y monitorizado constantemente, y ya plantea dos formas distintas de
calcular el coste: tramos por extensión y una fórmula continua.

Un sistema que cambia a menudo y del que todos dependen es exactamente la combinación que
produce acoplamiento generalizado.

Esta decisión ya está establecida como regla dura en `AGENTS.md`. Este ADR la registra con su
razonamiento para que se entienda por qué, no solo que existe.

## Decisión

`Credits` es un bounded context independiente. Ningún otro contexto puede:

- llamarlo para sumar, restar o ajustar créditos;
- calcular cuántos créditos vale una acción;
- conocer su modelo interno, sus entidades o sus repositorios.

Los demás contextos **publican hechos de negocio**. `Credits` los consume y decide por sí
mismo el efecto:

```text
Feedback ──FeedbackSubmitted──▶ RabbitMQ ──▶ Credits ──▶ aplica sus reglas
```

Prohibido de forma explícita:

```text
Feedback  ──▶ CreditsService::addCredits(...)
Reading   ──▶ CreditRepository
Work      ──▶ Credits\Application\...
```

## Alternativas consideradas

| Alternativa | A favor | En contra | Por qué no |
|---|---|---|---|
| Servicio de créditos inyectable | Simple, síncrono, transacción única | Todos los contextos acaban dependiendo de él; cambiar las reglas obliga a tocarlos todos | Es exactamente el acoplamiento que se quiere evitar en el componente más volátil |
| Créditos dentro de `User` | El saldo "es del usuario" | Mezcla identidad con economía; las reglas de crédito dependen de obras y feedback, no de la cuenta | `User` acabaría conociendo el modelo de negocio entero |
| Eventos, pero indicando el importe (`AddCreditsToUser`) | El emisor controla el efecto | El emisor tendría que conocer las reglas de crédito | Es una instrucción disfrazada de evento: el acoplamiento sigue, solo cambia de forma |

## Consecuencias

**Positivas**

- Las reglas de crédito cambian sin tocar ningún otro contexto.
- Se pueden añadir reglas nuevas suscribiéndose a eventos ya existentes.
- La economía es auditable: todo movimiento tiene un evento de origen trazable.
- Los demás contextos no necesitan saber nada de créditos para funcionar.

**Negativas**

- No hay transacción común entre el hecho y su efecto económico: la consistencia es eventual.
- Obliga a resolver qué ocurre cuando el saldo no alcanza (`C-1`), que con una llamada
  síncrona sería trivial de comprobar (aunque no de garantizar).
- Todos los handlers deben ser idempotentes: un crédito aplicado dos veces corrompe la
  economía.
- Exige Outbox Pattern donde perder el evento sea inaceptable.

**Coste de revertirla**

Muy alto. Revertirla significa reintroducir dependencias directas desde varios contextos, y
esas dependencias no se quitan después.

## Cumplimiento

- Regla de Deptrac dedicada: ningún namespace fuera de `Credits` puede depender de `Credits\*`.
- Revisión: cualquier cálculo de importe de crédito fuera de `Credits` se rechaza.
- `Credits` registra cada `eventId` procesado antes de aplicar su efecto.
