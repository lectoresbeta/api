<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;
use LectoresBeta\User\Account\Domain\Entity\EmailChangeRequest;
use LectoresBeta\User\Account\Domain\Repository\EmailChangeRequestRepository;
use LectoresBeta\User\Account\Domain\ValueObject\EmailChangeRequestId;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * @extends DoctrineRepository<EmailChangeRequest>
 */
final class DoctrineEmailChangeRequestRepository extends DoctrineRepository implements EmailChangeRequestRepository
{
    public function save(EmailChangeRequest $request): void
    {
        $this->register($request);
    }

    public function ofId(EmailChangeRequestId $id): ?EmailChangeRequest
    {
        return $this->repository()->find($id->value());
    }

    public function ofTokenHash(string $tokenHash): ?EmailChangeRequest
    {
        return $this->repository()->findOneBy(['tokenHash' => $tokenHash]);
    }

    public function pendingOf(UserId $userId): ?EmailChangeRequest
    {
        return $this->repository()->findOneBy([
            'userId' => $userId->value(),
            'consumedAt' => null,
        ]);
    }

    protected function entityClass(): string
    {
        return EmailChangeRequest::class;
    }
}
