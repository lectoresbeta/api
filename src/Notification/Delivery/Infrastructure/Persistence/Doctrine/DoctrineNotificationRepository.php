<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Infrastructure\Persistence\Doctrine;

use LectoresBeta\Notification\Delivery\Domain\Entity\Notification;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\Notification\Delivery\Domain\Repository\NotificationRepository;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;
use LectoresBeta\Shared\Infrastructure\Persistence\Doctrine\DoctrineRepository;

/**
 * @extends DoctrineRepository<Notification>
 */
final class DoctrineNotificationRepository extends DoctrineRepository implements NotificationRepository
{
    public function save(Notification $notification): void
    {
        $this->register($notification);
    }

    public function existsFor(RecipientId $recipientId, NotificationKind $kind, string $sourceEventId): bool
    {
        return null !== $this->repository()->findOneBy([
            'recipientId' => $recipientId->value(),
            'kind' => $kind,
            'sourceEventId' => $sourceEventId,
        ]);
    }

    protected function entityClass(): string
    {
        return Notification::class;
    }
}
