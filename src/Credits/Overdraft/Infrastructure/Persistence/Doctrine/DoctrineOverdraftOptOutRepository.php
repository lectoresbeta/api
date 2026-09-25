<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Overdraft\Domain\Entity\OverdraftOptOut;
use LectoresBeta\Credits\Overdraft\Domain\Repository\OverdraftOptOutRepository;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<OverdraftOptOut>
 */
final class DoctrineOverdraftOptOutRepository extends DoctrineRepository implements OverdraftOptOutRepository
{
    public function ofUser(UserId $userId): ?OverdraftOptOut
    {
        return $this->repository()->find($userId->value());
    }

    public function save(OverdraftOptOut $optOut): void
    {
        $this->register($optOut);
    }

    public function remove(OverdraftOptOut $optOut): void
    {
        $this->forget($optOut);
    }

    protected function entityClass(): string
    {
        return OverdraftOptOut::class;
    }
}
