<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Domain\Repository;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Subscription\Domain\Entity\AuthorSubscription;
use LectoresBeta\Shared\Domain\Pagination\Cursor;

interface AuthorSubscriptionRepository
{
    public function between(MemberId $subscriberId, MemberId $authorId): ?AuthorSubscription;

    /**
     * A quién sigue esta persona, de lo más reciente a lo más antiguo
     * (`FEAT-COM-027`).
     *
     * Devuelve **una fila de más que el límite**, que es como se sabe si hay
     * página siguiente sin contar nada.
     *
     * @return list<AuthorSubscription>
     */
    public function subscriptionsOf(MemberId $subscriberId, ?Cursor $after, int $limit): array;

    /**
     * Quién sigue a esta persona, con el mismo orden y el mismo truco.
     *
     * @return list<AuthorSubscription>
     */
    public function subscribersOf(MemberId $authorId, ?Cursor $after, int $limit): array;

    public function save(AuthorSubscription $subscription): void;

    public function remove(AuthorSubscription $subscription): void;
}
