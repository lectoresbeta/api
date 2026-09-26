<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Post\Domain\Entity\Post;
use LectoresBeta\Community\Post\Domain\Entity\PostAttachment;
use LectoresBeta\Community\Post\Domain\Enum\PostAudience;
use LectoresBeta\Community\Post\Domain\Repository\PostRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostFilters;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Community\Post\Domain\ValueObject\WallCuration;
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
        ?MemberId $onlyAuthorId = null,
        ?PostFilters $filters = null,
        ?WallCuration $curation = null,
    ): array {
        $curation ??= WallCuration::none();

        // Nada que enseñar, y no hay consulta que hacer: un `IN ()` vacío no
        // se puede escribir. Pasa con los guardados de quien no ha guardado
        // nada.
        if ($curation->excludesEverything()) {
            return [];
        }

        $query = $this->repository()->createQueryBuilder('p')
            ->where('p.deletedAt IS NULL');

        // El muro de una persona (`FEAT-COM-026`) es este mismo muro con un
        // filtro más. Todo lo de abajo —audiencia, bloqueo, cursor— sigue
        // aplicándose igual, que es justo lo que se quiere: mirar el perfil
        // de alguien no enseña nada que su muro no enseñara.
        if (null !== $onlyAuthorId) {
            $query
                ->andWhere('p.authorId = :onlyAuthor')
                ->setParameter('onlyAuthor', $onlyAuthorId->value());
        }

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

        // Lo que este espectador escondió una a una (`FEAT-COM-022`). Va en
        // la consulta por lo mismo que la audiencia: quitarlo después dejaría
        // páginas cortas.
        if ([] !== $curation->hiddenPostIds) {
            $query
                ->andWhere('p.id NOT IN (:hiddenPosts)')
                ->setParameter('hiddenPosts', $curation->hiddenPostIds);
        }

        // La lista de guardados es este mismo muro restringido a lo que uno
        // guardó (`FEAT-COM-021`), y de ahí sale gratis que guardar no
        // conserve acceso.
        if (null !== $curation->onlyPostIds) {
            $query
                ->andWhere('p.id IN (:onlyPosts)')
                ->setParameter('onlyPosts', $curation->onlyPostIds);
        }

        // Lo que el usuario ha acotado (`FEAT-COM-009`). Va en la consulta y
        // después de la visibilidad: filtrar en memoria rompería la
        // paginación igual que la rompería filtrar la audiencia ahí.
        if (null !== $filters) {
            PostFilterClauses::applyTo($query, $filters, 'p', 'p');
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

    public function ofIds(array $postIds): array
    {
        if ([] === $postIds) {
            return [];
        }

        /** @var list<Post> $rows */
        $rows = $this->repository()->createQueryBuilder('p')
            ->where('p.id IN (:posts)')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('posts', $postIds)
            ->getQuery()
            ->getResult();

        $byId = [];

        foreach ($rows as $row) {
            $byId[$row->id()->value()] = $row;
        }

        return $byId;
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
