<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\QueryBuilder;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Ranking\Domain\Entity\AuthorGenre;
use LectoresBeta\Community\Ranking\Domain\Entity\AuthorStats;
use LectoresBeta\Community\Recommendation\Domain\Entity\MemberGenre;
use LectoresBeta\Community\Recommendation\Domain\Repository\AuthorSuggestionRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<AuthorStats>
 */
final class DoctrineAuthorSuggestionRepository extends DoctrineRepository implements AuthorSuggestionRepository
{
    public function byGenres(array $genreCodes, array $excluded, int $limit): array
    {
        if ([] === $genreCodes) {
            return [];
        }

        $query = $this->entityManager->createQueryBuilder()
            ->select('s')
            ->from(AuthorStats::class, 's')
            ->join(AuthorGenre::class, 'g', 'WITH', 'g.authorId = s.authorId')
            ->where('g.genreCode IN (:genres)')
            ->setParameter('genres', $genreCodes)
            ->groupBy('s.authorId')
            ->orderBy('SUM(g.works)', 'DESC')
            ->addOrderBy('s.followers', 'DESC')
            ->addOrderBy('s.authorId', 'ASC')
            ->setMaxResults($limit);

        self::excluding($query, $excluded, 's.authorId');

        /** @var list<AuthorStats> $found */
        $found = $query->getQuery()->getResult();

        return $found;
    }

    public function mostFollowed(array $excluded, int $limit): array
    {
        $query = $this->repository()->createQueryBuilder('s')
            ->orderBy('s.followers', 'DESC')
            ->addOrderBy('s.authorId', 'ASC')
            ->setMaxResults($limit);

        self::excluding($query, $excluded, 's.authorId');

        /** @var list<AuthorStats> $found */
        $found = $query->getQuery()->getResult();

        return $found;
    }

    public function matchedGenres(array $authorIds, array $genreCodes): array
    {
        if ([] === $authorIds || [] === $genreCodes) {
            return [];
        }

        /** @var list<AuthorGenre> $rows */
        $rows = $this->entityManager->getRepository(AuthorGenre::class)
            ->createQueryBuilder('g')
            ->where('g.authorId IN (:authors)')
            ->andWhere('g.genreCode IN (:genres)')
            ->setParameter('authors', $authorIds)
            ->setParameter('genres', $genreCodes)
            ->getQuery()
            ->getResult();

        $byAuthor = [];

        foreach ($rows as $row) {
            $byAuthor[$row->authorId()->value()][] = $row->genreCode();
        }

        return $byAuthor;
    }

    public function genresOf(MemberId $memberId): array
    {
        /** @var list<MemberGenre> $rows */
        $rows = $this->entityManager->getRepository(MemberGenre::class)
            ->findBy(['memberId' => $memberId->value()]);

        return array_map(static fn (MemberGenre $row): string => $row->genreCode(), $rows);
    }

    public function countCandidates(array $excluded): int
    {
        $query = $this->repository()->createQueryBuilder('s')->select('COUNT(s.authorId)');

        self::excluding($query, $excluded, 's.authorId');

        return (int) $query->getQuery()->getSingleScalarResult();
    }

    protected function entityClass(): string
    {
        return AuthorStats::class;
    }

    /**
     * @param list<string> $excluded
     */
    private static function excluding(QueryBuilder $query, array $excluded, string $field): void
    {
        if ([] === $excluded) {
            return;
        }

        $query->andWhere($field.' NOT IN (:excluded)')->setParameter('excluded', $excluded);
    }
}
