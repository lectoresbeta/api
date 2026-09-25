<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\Work\Ingest\Domain\Entity\ManuscriptUpload;
use LectoresBeta\Work\Ingest\Domain\Repository\ManuscriptUploadRepository;
use LectoresBeta\Work\Ingest\Domain\ValueObject\ManuscriptUploadId;

/**
 * @extends DoctrineRepository<ManuscriptUpload>
 */
final class DoctrineManuscriptUploadRepository extends DoctrineRepository implements ManuscriptUploadRepository
{
    public function ofId(ManuscriptUploadId $id): ?ManuscriptUpload
    {
        return $this->repository()->find($id->value());
    }

    public function save(ManuscriptUpload $upload): void
    {
        $this->register($upload);
    }

    public function remove(ManuscriptUpload $upload): void
    {
        $this->forget($upload);
    }

    public function purgeExpired(\DateTimeImmutable $now): int
    {
        // Un `DELETE` de golpe y no una carga seguida de borrados: lo que se
        // retira es basura, y traerse a memoria el texto de cada novela
        // abandonada para volver a tirarlo sería justo lo contrario de lo que
        // esta purga viene a hacer.
        return (int) $this->entityManager->createQuery(
            'DELETE FROM '.ManuscriptUpload::class.' u WHERE u.expiresAt <= :now',
        )->setParameter('now', $now)->execute();
    }

    protected function entityClass(): string
    {
        return ManuscriptUpload::class;
    }
}
