<?php

declare(strict_types=1);

namespace LectoresBeta\Tests\Unit\Shared;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;

/**
 * Keeps what was published so a test can read it back.
 *
 * It asserts on the **payload**, never on the class: that is what other
 * contexts receive, and a test that checked the object would pass while the
 * contract on the wire broke.
 */
final class RecordingEventPublisher implements EventPublisher
{
    /** @var list<IntegrationEvent> */
    private array $published = [];

    public function publish(IntegrationEvent ...$events): void
    {
        foreach ($events as $event) {
            $this->published[] = $event;
        }
    }

    /**
     * @return list<IntegrationEvent>
     */
    public function all(?string $eventName = null): array
    {
        if (null === $eventName) {
            return $this->published;
        }

        return array_values(array_filter(
            $this->published,
            static fn (IntegrationEvent $event): bool => $event->eventName() === $eventName,
        ));
    }

    /**
     * @return list<array<string, string|int|float|bool|list<string|int|float|bool>|null>>
     */
    public function payloadsOf(string $eventName): array
    {
        return array_map(
            static fn (IntegrationEvent $event): array => $event->payload(),
            $this->all($eventName),
        );
    }
}
