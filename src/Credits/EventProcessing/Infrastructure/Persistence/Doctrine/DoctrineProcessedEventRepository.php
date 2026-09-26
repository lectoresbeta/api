<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\EventProcessing\Infrastructure\Persistence\Doctrine;

use Doctrine\DBAL\ParameterType;
use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<ProcessedEvent>
 */
final class DoctrineProcessedEventRepository extends DoctrineRepository implements ProcessedEventRepository
{
    public function wasProcessed(string $eventId, string $consumer): bool
    {
        return null !== $this->repository()->find(['eventId' => $eventId, 'consumer' => $consumer]);
    }

    public function markProcessed(ProcessedEvent $event): void
    {
        $this->register($event);
    }

    /**
     * En SQL nativo porque **DQL no sabe acotar un `DELETE`**, y acotarlo es
     * el punto entero: esta tabla la lee cada consumidor antes de mover un
     * crédito, y un borrado sin tope la bloquearía entera.
     *
     * `ctid` es la dirección física de la fila en PostgreSQL. Se usa aquí
     * porque la clave es compuesta y compararla en un `IN` de dos columnas
     * contra una subconsulta acotada es más caro y menos legible que esto.
     */
    public function purgeOlderThan(\DateTimeImmutable $moment, int $batch): int
    {
        return (int) $this->entityManager->getConnection()->executeStatement(
            <<<'SQL'
                DELETE FROM credits_ctx.processed_event
                WHERE ctid IN (
                    SELECT ctid FROM credits_ctx.processed_event
                    WHERE processed_at < :moment
                    LIMIT :batch
                )
                SQL,
            ['moment' => $moment->format('Y-m-d H:i:s'), 'batch' => $batch],
            // El tope va tipado: sin esto DBAL lo enviaría como texto y
            // PostgreSQL rechaza un `LIMIT` que no sea entero.
            ['moment' => ParameterType::STRING, 'batch' => ParameterType::INTEGER],
        );
    }

    protected function entityClass(): string
    {
        return ProcessedEvent::class;
    }
}
