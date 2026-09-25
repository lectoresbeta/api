<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Mention\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Mention\Domain\Entity\Mention;
use LectoresBeta\Community\Mention\Domain\Enum\MentionSubject;
use LectoresBeta\Community\Mention\Domain\Repository\MentionRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<Mention>
 */
final class DoctrineMentionRepository extends DoctrineRepository implements MentionRepository
{
    public function save(Mention $mention): void
    {
        $this->register($mention);
    }

    public function remove(Mention $mention): void
    {
        $this->forget($mention);
    }

    public function of(MentionSubject $kind, array $subjectIds): array
    {
        if ([] === $subjectIds) {
            return [];
        }

        /** @var list<Mention> $rows */
        $rows = $this->repository()->createQueryBuilder('m')
            ->where('m.subjectKind = :kind')
            ->andWhere('m.subjectId IN (:subjects)')
            ->setParameter('kind', $kind)
            ->setParameter('subjects', $subjectIds)
            ->orderBy('m.position', 'ASC')
            ->getQuery()
            ->getResult();

        $bySubject = [];

        foreach ($rows as $row) {
            $bySubject[$row->subjectId()][] = $row;
        }

        return $bySubject;
    }

    protected function entityClass(): string
    {
        return Mention::class;
    }
}
