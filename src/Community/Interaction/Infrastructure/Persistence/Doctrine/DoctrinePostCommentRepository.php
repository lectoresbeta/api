<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\QueryBuilder;
use LectoresBeta\Community\Interaction\Domain\Entity\PostComment;
use LectoresBeta\Community\Interaction\Domain\Repository\PostCommentRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\PostCommentId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<PostComment>
 */
final class DoctrinePostCommentRepository extends DoctrineRepository implements PostCommentRepository
{
    public function save(PostComment $comment): void
    {
        $this->register($comment);
    }

    public function ofId(PostCommentId $commentId): ?PostComment
    {
        $comment = $this->repository()->find($commentId->value());

        return null !== $comment && !$comment->isDeleted() ? $comment : null;
    }

    public function topLevelOf(PostId $postId, string $sort, ?Cursor $after, int $limit): array
    {
        $query = $this->repository()->createQueryBuilder('c')
            ->where('c.postId = :post')
            ->andWhere('c.parentCommentId IS NULL')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('post', $postId->value());

        if ('RELEVANT' === $sort) {
            return $this->byRelevance($query, $after, $limit);
        }

        return $this->byDate($query, 'RECENT' === $sort ? 'DESC' : 'ASC', $after, $limit);
    }

    public function repliesOf(PostCommentId $rootId, ?Cursor $after, int $limit): array
    {
        $query = $this->repository()->createQueryBuilder('c')
            ->where('c.parentCommentId = :root')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('root', $rootId->value());

        return $this->byDate($query, 'ASC', $after, $limit);
    }

    public function allRepliesOf(PostCommentId $rootId): array
    {
        /** @var list<PostComment> $found */
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
        return PostComment::class;
    }

    /**
     * `apoyos + 2 × respuestas`, de mayor a menor (`FEAT-COM-006`).
     *
     * **Una respuesta pesa el doble que un apoyo** porque cuesta más: dar un
     * «me gusta» es un gesto y escribir una respuesta es participar. Sin ese
     * peso, «más relevantes» sería «más gustados» con otro nombre.
     *
     * **A igual puntuación, el más antiguo primero**: es quien abrió la
     * conversación. Nótese que el desempate va al revés que el criterio, al
     * contrario que en los órdenes por fecha, y por eso la comparación del
     * cursor también.
     *
     * La aritmética va en un `HIDDEN` porque DQL no ordena por una expresión
     * que no esté seleccionada, y el cursor la lleva dentro: sin ella, la
     * página siguiente empezaría donde la cuenta hubiera caído esta vez, y un
     * apoyo entre dos peticiones repetiría o se saltaría comentarios.
     *
     * @return list<PostComment>
     */
    private function byRelevance(QueryBuilder $query, ?Cursor $after, int $limit): array
    {
        $query->addSelect('(c.likeCount + 2 * c.replyCount) AS HIDDEN relevance');

        if (null !== $after && null !== $after->rank) {
            $query
                ->andWhere(
                    '(c.likeCount + 2 * c.replyCount) < :rank'
                    .' OR ((c.likeCount + 2 * c.replyCount) = :rank'
                    .' AND (c.createdAt > :at OR (c.createdAt = :at AND c.id > :id)))',
                )
                ->setParameter('rank', $after->rank)
                ->setParameter('at', $after->at)
                ->setParameter('id', $after->id);
        }

        /** @var list<PostComment> $found */
        $found = $query
            ->orderBy('relevance', 'DESC')
            ->addOrderBy('c.createdAt', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();

        return $found;
    }

    /**
     * @return list<PostComment>
     */
    private function byDate(QueryBuilder $query, string $direction, ?Cursor $after, int $limit): array
    {
        if (null !== $after) {
            // El desempate va en la misma dirección que el criterio. Dos
            // comentarios del mismo segundo son lo normal en un hilo vivo, y
            // un desempate fijo haría que el orden se contradijera a sí mismo.
            $comparison = 'DESC' === $direction ? '<' : '>';

            $query
                ->andWhere(\sprintf('(c.createdAt %1$s :at OR (c.createdAt = :at AND c.id %1$s :id))', $comparison))
                ->setParameter('at', $after->at)
                ->setParameter('id', $after->id);
        }

        /** @var list<PostComment> $found */
        $found = $query
            ->orderBy('c.createdAt', $direction)
            ->addOrderBy('c.id', $direction)
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();

        return $found;
    }
}
