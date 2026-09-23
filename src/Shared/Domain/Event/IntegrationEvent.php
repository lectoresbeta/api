<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Event;

/**
 * Contrato público asíncrono entre bounded contexts.
 *
 * Un evento de integración describe **un hecho que ya ha ocurrido** en el
 * contexto que lo publica. Nunca es una instrucción para otro: quien lo
 * recibe decide qué significa en su propio modelo.
 *
 * Reglas que esta interfaz existe para recordar:
 *
 * - no transporta agregados ni entidades de Doctrine;
 * - no transporta importes de créditos salvo que los publique `Credits`;
 * - no transporta contenido privado —texto de obras, correcciones, mensajes—,
 *   porque una cola con reintentos y colas de fallos no es sitio para él;
 * - lleva `eventId` estable, porque los consumidores deben ser idempotentes:
 *   hay que asumir entrega repetida.
 *
 * Es una interfaz de dominio a propósito: marcar un evento no puede obligar a
 * conocer Messenger. El enrutado a RabbitMQ es cosa de Infrastructure.
 */
interface IntegrationEvent
{
    /**
     * Identificador estable del hecho. Reintentar la publicación no lo cambia.
     */
    public function eventId(): string;

    /**
     * Nombre del hecho en términos de negocio: `FeedbackSubmitted`,
     * `AccountActivated`. Nunca `UpdateCreditsCommand`.
     */
    public function eventName(): string;

    public function occurredAt(): \DateTimeImmutable;
}
