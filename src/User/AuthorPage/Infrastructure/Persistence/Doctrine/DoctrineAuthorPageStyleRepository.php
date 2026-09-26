<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\Entity\AuthorPageStyle;
use LectoresBeta\User\AuthorPage\Domain\Repository\AuthorPageStyleRepository;

/**
 * @extends DoctrineRepository<AuthorPageStyle>
 */
final class DoctrineAuthorPageStyleRepository extends DoctrineRepository implements AuthorPageStyleRepository
{
    public function ofUser(UserId $userId): ?AuthorPageStyle
    {
        return $this->repository()->find($userId->value());
    }

    public function save(AuthorPageStyle $style): void
    {
        $this->register($style);
    }

    protected function entityClass(): string
    {
        return AuthorPageStyle::class;
    }
}
