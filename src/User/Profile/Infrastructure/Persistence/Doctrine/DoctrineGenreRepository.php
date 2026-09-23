<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Profile\Domain\Entity\Genre;
use LectoresBeta\User\Profile\Domain\Repository\GenreRepository;

/**
 * @extends DoctrineRepository<Genre>
 */
final class DoctrineGenreRepository extends DoctrineRepository implements GenreRepository
{
    public function activeCatalogue(): array
    {
        return array_values($this->repository()->findBy(['active' => true], ['position' => 'ASC']));
    }

    public function unknownAmong(array $codes): array
    {
        if ([] === $codes) {
            return [];
        }

        $normalised = array_values(array_unique(array_map(strtoupper(...), $codes)));

        /** @var list<array{code: string}> $rows */
        $rows = $this->entityManager->createQueryBuilder()
            ->select('g.code')
            ->from(Genre::class, 'g')
            ->where('g.code IN (:codes)')
            ->andWhere('g.active = true')
            ->setParameter('codes', $normalised)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_diff($normalised, array_column($rows, 'code')));
    }

    protected function entityClass(): string
    {
        return Genre::class;
    }
}
