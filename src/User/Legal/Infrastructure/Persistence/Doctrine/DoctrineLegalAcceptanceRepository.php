<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Legal\Domain\Entity\LegalAcceptance;
use LectoresBeta\User\Legal\Domain\Repository\LegalAcceptanceRepository;

/**
 * @extends DoctrineRepository<LegalAcceptance>
 */
final class DoctrineLegalAcceptanceRepository extends DoctrineRepository implements LegalAcceptanceRepository
{
    public function save(LegalAcceptance $acceptance): void
    {
        $this->register($acceptance);
    }

    public function ofUser(UserId $userId): array
    {
        return array_values($this->repository()->findBy(
            ['userId' => $userId->value()],
            ['acceptedAt' => 'DESC'],
        ));
    }

    protected function entityClass(): string
    {
        return LegalAcceptance::class;
    }
}
