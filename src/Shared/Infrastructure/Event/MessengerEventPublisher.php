<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Event;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * The port, over Symfony Messenger.
 *
 * Routing is not decided here: `config/packages/messenger.yaml` routes
 * anything implementing `IntegrationEvent` to the RabbitMQ transport. So this
 * class stays a one-liner, and adding an event never means editing it.
 */
final readonly class MessengerEventPublisher implements EventPublisher
{
    public function __construct(private MessageBusInterface $eventBus)
    {
    }

    public function publish(IntegrationEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->eventBus->dispatch($event);
        }
    }
}
