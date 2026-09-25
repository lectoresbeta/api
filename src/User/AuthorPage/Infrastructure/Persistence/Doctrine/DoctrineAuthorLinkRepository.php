<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\Entity\AuthorLink;
use LectoresBeta\User\AuthorPage\Domain\Repository\AuthorLinkRepository;

/**
 * @extends DoctrineRepository<AuthorLink>
 */
final class DoctrineAuthorLinkRepository extends DoctrineRepository implements AuthorLinkRepository
{
    public function replaceAllOf(UserId $userId, array $links): void
    {
        foreach ($this->of($userId) as $existing) {
            $this->forget($existing);
        }

        foreach ($links as $link) {
            $this->register($link);
        }
    }

    public function of(UserId $userId): array
    {
        return array_values($this->repository()->findBy(
            ['userId' => $userId->value()],
            ['position' => 'ASC'],
        ));
    }

    public function ofUsers(array $userIds): array
    {
        if ([] === $userIds) {
            return [];
        }

        $links = [];

        foreach ($this->repository()->findBy(['userId' => $userIds], ['position' => 'ASC']) as $link) {
            $links[$link->userId()->value()][] = $link;
        }

        return $links;
    }

    protected function entityClass(): string
    {
        return AuthorLink::class;
    }
}
