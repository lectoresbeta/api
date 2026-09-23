<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Domain\Repository;

use LectoresBeta\Notification\Delivery\Domain\Entity\Notification;
use LectoresBeta\Notification\Delivery\Domain\Enum\NotificationKind;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;

interface NotificationRepository
{
    public function save(Notification $notification): void;

    /**
     * Whether this exact notice already exists for this recipient and this
     * originating fact.
     *
     * It is the idempotency of `FEAT-NOT-008` `RN-2`, and the same triple is
     * a unique constraint in the database: the check saves the work, the
     * constraint is what actually guarantees it when two workers race.
     */
    public function existsFor(RecipientId $recipientId, NotificationKind $kind, string $sourceEventId): bool;
}
