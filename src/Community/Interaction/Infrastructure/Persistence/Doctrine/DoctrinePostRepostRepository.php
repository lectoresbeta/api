<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Community\Interaction\Domain\Entity\PostRepost;
use LectoresBeta\Community\Interaction\Domain\Repository\PostRepostRepository;
use LectoresBeta\Community\Post\Domain\Entity\Post;
use LectoresBeta\Community\Post\Domain\Enum\PostAudience;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Post\Domain\ValueObject\PostFilters;
use LectoresBeta\Community\Post\Domain\ValueObject\PostId;
use LectoresBeta\Community\Post\Infrastructure\Persistence\Doctrine\PostFilterClauses;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<PostRepost>
 */
final class DoctrinePostRepostRepository extends DoctrineRepository implements PostRepostRepository
{
    public function save(PostRepost $repost): void
    {
        $this->register($repost);
    }

    public function remove(PostRepost $repost): void
    {
        $this->forget($repost);
    }

    public function between(MemberId $memberId, PostId $postId): ?PostRepost
    {
        return $this->repository()->findOneBy([
            'memberId' => $memberId->value(),
            'postId' => $postId->value(),
        ]);
    }

    public function wallFor(
        MemberId $readerId,
        array $followedAuthorIds,
        array $hiddenAuthorIds,
        ?Cursor $after,
        int $limit,
        ?MemberId $onlyMemberId = null,
        ?PostFilters $filters = null,
    ): array {
        // El original se une aquí y no se pide después, por dos razones: la
        // audiencia hay que comprobarla sobre él —un repost no la amplía— y
        // el muro tiene que servirlo embebido, sin una petición por entrada.
        // Se selecciona **solo el repost** y el original se une para poder
        // filtrar por su audiencia. Traerlo también aquí hidrataría dos
        // entidades sin relación declarada, que Doctrine devuelve de una
        // forma que depende de la consulta; pedirlos por lotes después es más
        // aburrido y siempre significa lo mismo.
        $query = $this->entityManager->createQueryBuilder()
            ->select('r')
            ->from(PostRepost::class, 'r')
            ->join(Post::class, 'p', 'WITH', 'p.id = r.postId')
            ->where('p.deletedAt IS NULL');

        // En el muro de una persona, **los que sacó ella** (`FEAT-COM-026`).
        // Se filtra por quien repostea y no por quien escribió: lo que
        // alguien saca a su muro es suyo aunque el texto sea de otro.
        if (null !== $onlyMemberId) {
            $query
                ->andWhere('r.memberId = :onlyMember')
                ->setParameter('onlyMember', $onlyMemberId->value());
        }

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
            // Los dos lados: con quien repostea y con quien escribió. Un
            // bloqueo que solo mirara a uno dejaría la puerta de vuelta.
            $query
                ->andWhere('r.memberId NOT IN (:hidden)')
                ->andWhere('p.authorId NOT IN (:hidden)')
                ->setParameter('hidden', $hiddenAuthorIds);
        }

        // Los mismos filtros que la otra consulta (`FEAT-COM-009`), sobre el
        // original salvo la fecha: esa es la del repost, que es cuando esta
        // entrada apareció en el muro y por la que se ordena.
        if (null !== $filters) {
            PostFilterClauses::applyTo($query, $filters, 'p', 'r');
        }

        if (null !== $after) {
            $query
                ->andWhere('(r.createdAt < :at OR (r.createdAt = :at AND r.id < :id))')
                ->setParameter('at', $after->at)
                ->setParameter('id', $after->id);
        }

        /** @var list<PostRepost> $rows */
        $rows = $query
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();

        return $rows;
    }

    protected function entityClass(): string
    {
        return PostRepost::class;
    }
}
