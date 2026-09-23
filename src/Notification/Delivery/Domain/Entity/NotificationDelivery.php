<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Delivery\Domain\Entity;

use LectoresBeta\Notification\Delivery\Domain\Enum\DeliveryChannel;
use LectoresBeta\Notification\Delivery\Domain\Enum\DeliveryStatus;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\NotificationId;

/**
 * One attempt to deliver a notice through one channel.
 *
 * Kept apart from the notice itself because the same notice goes to more than
 * one channel and each one fails on its own. `SUPPRESSED` is recorded rather
 * than skipped silently: «why did I not get that email» is a question the
 * system should be able to answer.
 */
class NotificationDelivery
{
    private string $notificationId;

    /**
     * Held as the backed value and handed out as the enum.
     *
     * It is part of the primary key, and Doctrine's XML mapping does not
     * allow an enum on an identifier — the XSD rejects `enum-type` there.
     * Same trade-off the identifiers make, for the same reason.
     */
    private string $channel;

    private DeliveryStatus $status;

    private int $attempts = 0;

    private ?string $failureReason = null;

    private \DateTimeImmutable $updatedAt;

    private ?\DateTimeImmutable $deliveredAt = null;

    public function __construct(
        NotificationId $notificationId,
        DeliveryChannel $channel,
        \DateTimeImmutable $now,
    ) {
        $this->notificationId = $notificationId->value();
        $this->channel = $channel->value;
        $this->status = DeliveryStatus::PENDING;
        $this->updatedAt = $now;
    }

    public function notificationId(): NotificationId
    {
        return NotificationId::fromString($this->notificationId);
    }

    public function channel(): DeliveryChannel
    {
        return DeliveryChannel::from($this->channel);
    }

    public function status(): DeliveryStatus
    {
        return $this->status;
    }

    public function markSent(\DateTimeImmutable $now): void
    {
        ++$this->attempts;
        $this->status = DeliveryStatus::SENT;
        $this->deliveredAt = $now;
        $this->updatedAt = $now;
    }

    public function markFailed(string $reason, \DateTimeImmutable $now): void
    {
        ++$this->attempts;
        $this->status = DeliveryStatus::FAILED;
        $this->failureReason = mb_substr($reason, 0, 255);
        $this->updatedAt = $now;
    }

    public function suppress(\DateTimeImmutable $now): void
    {
        $this->status = DeliveryStatus::SUPPRESSED;
        $this->updatedAt = $now;
    }
}
