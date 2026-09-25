<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Post\Domain\Entity\Post;
use LectoresBeta\Community\Post\Domain\Entity\PostAttachment;
use LectoresBeta\Community\Post\Domain\Enum\PostAudience;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<Post>
 */
final class DoctrinePostRepository extends DoctrineRepository implements PostRepository
{
    public function save(Post $post): void
    {
        $this->register($post);
    }

    public function attach(PostAttachment $attachment): void
    {
        $this->entityManager->persist($attachment);
    }

    public function ofId(PostId $postId): ?Post
    {
        $post = $this->repository()->find($postId->value());

        return null !== $post && !$post->isDeleted() ? $post : null;
    }

    public function wallFor(
        MemberId $readerId,
        array $followedAuthorIds,
        array $hiddenAuthorIds,
        ?Cursor $after,
        int $limit,
    ): array {
        $query = $this->repository()->createQueryBuilder('p')
            ->where('p.deletedAt IS NULL');

        // Lo que cada quien puede ver, dicho una sola vez: lo público, lo de
        // quienes sigue, y lo suyo. El autor entra por la última rama y no
        // por la primera: sus publicaciones `FOLLOWERS` son suyas aunque no
        // se siga a sí mismo.
        $visible = 'p.audience = :everyone OR p.authorId = :reader';

        if ([] !== $followedAuthorIds) {
            $visible .= ' OR p.authorId IN (:followed)';
            $query->setParameter('followed', $followedAuthorIds);
        }

        $query
            ->andWhere('('.$visible.')')
            ->setParameter('everyone', PostAudience::EVERYONE)
            ->setParameter('reader', $readerId->value());

        if ([] !== $hiddenAuthorIds) {
            $query
                ->andWhere('p.authorId NOT IN (:hidden)')
                ->setParameter('hidden', $hiddenAuthorIds);
        }

        if (null !== $after) {
            $query
                ->andWhere('(p.createdAt < :at OR (p.createdAt = :at AND p.id < :id))')
                ->setParameter('at', $after->at)
                ->setParameter('id', $after->id);
        }

        /** @var list<Post> $found */
        $found = $query
            ->orderBy('p.createdAt', 'DESC')
            ->addOrderBy('p.id', 'DESC')
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();

        return $found;
    }

    public function attachmentsOf(array $postIds): array
    {
        if ([] === $postIds) {
            return [];
        }

        /** @var list<PostAttachment> $rows */
        $rows = $this->entityManager->getRepository(PostAttachment::class)
            ->createQueryBuilder('a')
            ->where('a.postId IN (:posts)')
            ->setParameter('posts', $postIds)
            ->orderBy('a.position', 'ASC')
            ->getQuery()
            ->getResult();

        $byPost = [];

        foreach ($rows as $row) {
            $byPost[$row->postId()->value()] ??= $row;
        }

        return $byPost;
    }

    protected function entityClass(): string
    {
        return Post::class;
    }
}
