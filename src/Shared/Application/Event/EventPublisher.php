<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Application\Event;

use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Publishing a fact for the other bounded contexts to react to.
 *
 * The port exists so that a use case can say «this happened» without knowing
 * that «this happened» travels over RabbitMQ. Messenger, the transport, the
 * serializer and the retry policy are Infrastructure, and a handler that
 * imported `MessageBusInterface` would drag all four into Application.
 */
interface EventPublisher
{
    public function publish(IntegrationEvent ...$events): void;
}
