<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Messenger;

use LectoresBeta\Shared\Domain\Event\IncomingIntegrationEvent;

/**
 * Which class rebuilds which fact, for this application.
 *
 * The map is declared in `config/services.yaml` rather than discovered, and
 * that is the point: **the set of facts a context subscribes to is a
 * decision**, and a decision that is visible in one file can be reviewed. A
 * registry that scanned for classes would let a new subscription appear
 * because somebody added a file.
 *
 * Several contexts may subscribe to the same fact, so the map is keyed by
 * event name and holds a list.
 */
final readonly class IncomingEventRegistry
{
    /** @var array<string, list<class-string<IncomingIntegrationEvent>>> */
    private array $byEventName;

    /**
     * Los nombres llegan de `config/services.yaml`, así que lo que se declara
     * es lo que de verdad se recibe —nombres de clase— y no una garantía que
     * un fichero de configuración no puede dar. De ahí la comprobación en
     * tiempo de arranque: un error de wiring revienta al construir el
     * contenedor, no al consumir el primer mensaje en producción.
     *
     * @param iterable<class-string> $eventClasses
     */
    public function __construct(iterable $eventClasses)
    {
        $map = [];

        foreach ($eventClasses as $class) {
            if (!is_subclass_of($class, IncomingIntegrationEvent::class)) {
                throw new \InvalidArgumentException(\sprintf('%s is registered as an incoming integration event but does not implement %s.', $class, IncomingIntegrationEvent::class));
            }

            $map[$class::subscribesTo()][] = $class;
        }

        $this->byEventName = $map;
    }

    /**
     * @return list<class-string<IncomingIntegrationEvent>>
     */
    public function classesFor(string $eventName): array
    {
        return $this->byEventName[$eventName] ?? [];
    }
}
