<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Infrastructure\Persistence\Doctrine;

use Doctrine\ORM\QueryBuilder;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Subscription\Domain\Entity\AuthorSubscription;
use LectoresBeta\Community\Subscription\Domain\Repository\AuthorSubscriptionRepository;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<AuthorSubscription>
 */
final class DoctrineAuthorSubscriptionRepository extends DoctrineRepository implements AuthorSubscriptionRepository
{
    public function between(MemberId $subscriberId, MemberId $authorId): ?AuthorSubscription
    {
        return $this->repository()->findOneBy([
            'subscriberId' => $subscriberId->value(),
            'authorId' => $authorId->value(),
        ]);
    }

    public function subscriptionsOf(MemberId $subscriberId, ?Cursor $after, int $limit): array
    {
        return $this->page(
            $this->repository()->createQueryBuilder('s')
                ->where('s.subscriberId = :member')
                ->setParameter('member', $subscriberId->value()),
            $after,
            $limit,
        );
    }

    public function subscribersOf(MemberId $authorId, ?Cursor $after, int $limit): array
    {
        return $this->page(
            $this->repository()->createQueryBuilder('s')
                ->where('s.authorId = :member')
                ->setParameter('member', $authorId->value()),
            $after,
            $limit,
        );
    }

    public function countSubscriptionsOf(MemberId $subscriberId): int
    {
        return $this->repository()->count(['subscriberId' => $subscriberId->value()]);
    }

    public function countSubscribersOf(MemberId $authorId): int
    {
        return $this->repository()->count(['authorId' => $authorId->value()]);
    }

    public function save(AuthorSubscription $subscription): void
    {
        $this->register($subscription);
    }

    public function remove(AuthorSubscription $subscription): void
    {
        $this->forget($subscription);
    }

    protected function entityClass(): string
    {
        return AuthorSubscription::class;
    }

    /**
     * El resto de la consulta, igual en las dos direcciones.
     *
     * Ordena por **fecha e identificador**, y las dos hacen falta: con la
     * fecha sola, dos seguimientos del mismo instante harían que una fila se
     * repitiese o se perdiera al pasar de página. Es la misma pareja que
     * transporta el cursor, y por eso la comparación `(fecha, id) <` va
     * escrita a mano — Doctrine no compara tuplas.
     *
     * Pide **una fila de más** que el límite: así se sabe si hay siguiente
     * sin contar la tabla entera.
     *
     * @return list<AuthorSubscription>
     */
    private function page(QueryBuilder $query, ?Cursor $after, int $limit): array
    {
        if (null !== $after) {
            $query
                ->andWhere('(s.createdAt < :at OR (s.createdAt = :at AND s.id < :id))')
                ->setParameter('at', $after->at)
                ->setParameter('id', $after->id);
        }

        /** @var list<AuthorSubscription> $found */
        $found = $query
            ->orderBy('s.createdAt', 'DESC')
            ->addOrderBy('s.id', 'DESC')
            ->setMaxResults($limit + 1)
            ->getQuery()
            ->getResult();

        return $found;
    }
}
