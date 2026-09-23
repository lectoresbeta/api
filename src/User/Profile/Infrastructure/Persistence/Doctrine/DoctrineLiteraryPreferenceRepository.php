<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Profile\Domain\Entity\LiteraryPreference;
use LectoresBeta\User\Profile\Domain\Repository\LiteraryPreferenceRepository;

/**
 * @extends DoctrineRepository<LiteraryPreference>
 */
final class DoctrineLiteraryPreferenceRepository extends DoctrineRepository implements LiteraryPreferenceRepository
{
    public function add(LiteraryPreference $preference): void
    {
        $this->register($preference);
    }

    public function codesOf(UserId $userId): array
    {
        return array_map(
            static fn (LiteraryPreference $preference): string => $preference->genreCode(),
            array_values($this->repository()->findBy(['userId' => $userId->value()])),
        );
    }

    public function removeAllOf(UserId $userId): void
    {
        // Removed one by one and not with a bulk `DELETE`: the new selection
        // is persisted in the same transaction, and a bulk delete bypassing
        // the unit of work would leave Doctrine trying to insert a row it
        // still believes exists.
        foreach ($this->repository()->findBy(['userId' => $userId->value()]) as $preference) {
            $this->forget($preference);
        }
    }

    protected function entityClass(): string
    {
        return LiteraryPreference::class;
    }
}
