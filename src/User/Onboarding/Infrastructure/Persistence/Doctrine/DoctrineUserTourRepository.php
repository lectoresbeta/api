<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Onboarding\Domain\Entity\UserTour;
use LectoresBeta\User\Onboarding\Domain\Repository\UserTourRepository;

/**
 * @extends DoctrineRepository<UserTour>
 */
final class DoctrineUserTourRepository extends DoctrineRepository implements UserTourRepository
{
    public function of(UserId $userId, string $tourId): ?UserTour
    {
        return $this->repository()->find(['userId' => $userId->value(), 'tourId' => $tourId]);
    }

    public function save(UserTour $tour): void
    {
        $this->register($tour);
    }

    protected function entityClass(): string
    {
        return UserTour::class;
    }
}
