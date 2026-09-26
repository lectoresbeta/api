<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\QueryBuilder;
use LectoresBeta\Community\Interaction\Domain\Entity\ChapterComment;
use LectoresBeta\Community\Interaction\Domain\Repository\ChapterCommentRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterCommentId;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<ChapterComment>
 */
final class DoctrineChapterCommentRepository extends DoctrineRepository implements ChapterCommentRepository
{
    public function save(ChapterComment $comment): void
    {
        $this->register($comment);
    }

    public function ofId(ChapterCommentId $commentId): ?ChapterComment
    {
        $comment = $this->repository()->find($commentId->value());

        return null !== $comment && !$comment->isDeleted() ? $comment : null;
    }

    public function topLevelOf(ChapterId $chapterId, ?Cursor $after, int $limit): array
    {
        $query = $this->repository()->createQueryBuilder('c')
            ->where('c.chapterId = :chapter')
            ->andWhere('c.parentCommentId IS NULL')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('chapter', $chapterId->value());

        return $this->byDate($query, 'DESC', $after, $limit);
    }

    public function repliesOf(ChapterCommentId $rootId, ?Cursor $after, int $limit): array
    {
        $query = $this->repository()->createQueryBuilder('c')
            ->where('c.parentCommentId = :root')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('root', $rootId->value());

        return $this->byDate($query, 'ASC', $after, $limit);
    }

    public function allRepliesOf(ChapterCommentId $rootId): array
    {
        /** @var list<ChapterComment> $found */
        $found = $this->repository()->createQueryBuilder('c')
            ->where('c.parentCommentId = :root')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('root', $rootId->value())
            ->getQuery()
            ->getResult();

        return $found;
    }

    protected function entityClass(): string
    {
        return ChapterComment::class;
    }

    /**
     * @return list<ChapterComment>
     */
    private function byDate(QueryBuilder $query, string $direction, ?Cursor $after, int $limit): array
    {
        if (null !== $after) {
            // El desempate va en la misma dirección que el criterio: dos
            // comentarios del mismo segundo son lo normal en un hilo vivo, y
            // uno fijo haría que el orden se contradijera a sí mismo.
            $comparison = 'DESC' === $direction ? '<' : '>';

            $query
                ->andWhere(\sprintf('(c.createdAt %1$s :at OR (c.createdAt = :at AND c.id %1$s :id))', $comparison))
                ->setParameter('at', $after->at)
                ->setParameter('id', $after->id);
        }

        /** @var list<ChapterComment> $found */
        $found = $query
            ->orderBy('c.createdAt', $direction)
            ->addOrderBy('c.id', $direction)
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();

        return $found;
    }
}
