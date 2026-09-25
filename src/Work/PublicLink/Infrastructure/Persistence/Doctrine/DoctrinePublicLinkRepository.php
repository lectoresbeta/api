<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;
use LectoresBeta\Work\PublicLink\Domain\Entity\PublicLink;
use LectoresBeta\Work\PublicLink\Domain\Repository\PublicLinkRepository;
use LectoresBeta\Work\PublicLink\Domain\ValueObject\PublicLinkId;

/**
 * @extends DoctrineRepository<PublicLink>
 */
final class DoctrinePublicLinkRepository extends DoctrineRepository implements PublicLinkRepository
{
    public function save(PublicLink $link): void
    {
        $this->register($link);
    }

    public function ofId(PublicLinkId $id): ?PublicLink
    {
        return $this->repository()->find($id->value());
    }

    public function ofTokenHash(string $tokenHash): ?PublicLink
    {
        return $this->repository()->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function ofWork(WorkId $workId): array
    {
        return array_values($this->repository()->findBy(
            ['workId' => $workId->value()],
            ['createdAt' => 'DESC'],
        ));
    }

    protected function entityClass(): string
    {
        return PublicLink::class;
    }
}
