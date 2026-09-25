<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Ranking\Domain\Entity\AuthorGenre;
use LectoresBeta\Community\Ranking\Domain\Entity\AuthorStats;
use LectoresBeta\Community\Recommendation\Domain\Repository\AuthorStatsRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<AuthorStats>
 */
final class DoctrineAuthorStatsRepository extends DoctrineRepository implements AuthorStatsRepository
{
    public function of(MemberId $authorId, \DateTimeImmutable $now): AuthorStats
    {
        return $this->repository()->find($authorId->value()) ?? new AuthorStats($authorId, $now);
    }

    public function save(AuthorStats $stats): void
    {
        $this->register($stats);
    }

    public function genre(MemberId $authorId, string $genreCode): AuthorGenre
    {
        $found = $this->entityManager->getRepository(AuthorGenre::class)->find([
            'authorId' => $authorId->value(),
            'genreCode' => strtoupper($genreCode),
        ]);

        return $found ?? new AuthorGenre($authorId, $genreCode);
    }

    public function saveGenre(AuthorGenre $genre): void
    {
        $this->entityManager->persist($genre);
    }

    protected function entityClass(): string
    {
        return AuthorStats::class;
    }
}
