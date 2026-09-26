<?php

declare(strict_types=1);

namespace LectoresBeta\Community\EventProcessing\Domain\Repository;

use LectoresBeta\Community\EventProcessing\Domain\Entity\ProcessedEvent;

/**
 * El registro de lo ya aplicado por este contexto.
 *
 * La comprobación y el apunte van **dentro de la misma transacción** que el
 * efecto que protegen, y los dos van por `(eventId, consumer)`: un hecho se
 * aplica como mucho una vez **por regla**, no una vez en total.
 */
interface ProcessedEventRepository
{
    public function wasProcessed(string $eventId, string $consumer): bool;

    public function markProcessed(ProcessedEvent $event): void;
}
