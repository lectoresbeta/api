<?php

declare(strict_types=1);

namespace LectoresBeta\Notification\Preference\Domain\Entity;

use LectoresBeta\Notification\Delivery\Domain\Enum\DeliveryChannel;
use LectoresBeta\Notification\Delivery\Domain\ValueObject\RecipientId;

/**
 * A projection of what each person has chosen to receive.
 *
 * The settings themselves belong to `User` (`FEAT-USR-039`); this is the copy
 * that lets `Notification` decide at delivery time (`RN-6`) without reading
 * another context's tables. It is fed by `NotificationPreferencesChanged`.
 *
 * `allMuted` is stored separately, mirroring the original: the master switch
 * **suspends** the individual choices, it does not overwrite them, so turning
 * it off has to give the person their configuration back exactly as it was.
 *
 * A topic with no row takes its default. That is what lets a new kind of
 * notice ship without a migration.
 */
class RecipientPreference
{
    private string $recipientId;

    private string $topic;

    /**
     * Held as the backed value and handed out as the enum.
     *
     * It is part of the primary key, and Doctrine's XML mapping does not
     * allow an enum on an identifier — the XSD rejects `enum-type` there.
     * Same trade-off the identifiers make, for the same reason.
     */
    private string $channel;

    private bool $enabled;

    private bool $allMuted = false;

    private \DateTimeImmutable $updatedAt;

    public function __construct(
        RecipientId $recipientId,
        string $topic,
        DeliveryChannel $channel,
        bool $enabled,
        \DateTimeImmutable $now,
    ) {
        $this->recipientId = $recipientId->value();
        $this->topic = $topic;
        $this->channel = $channel->value;
        $this->enabled = $enabled;
        $this->updatedAt = $now;
    }

    public function recipientId(): RecipientId
    {
        return RecipientId::fromString($this->recipientId);
    }

    public function topic(): string
    {
        return $this->topic;
    }

    public function channel(): DeliveryChannel
    {
        return DeliveryChannel::from($this->channel);
    }

    public function allows(bool $isOperational): bool
    {
        if ($isOperational) {
            return true;
        }

        return !$this->allMuted && $this->enabled;
    }

    public function change(bool $enabled, bool $allMuted, \DateTimeImmutable $now): void
    {
        $this->enabled = $enabled;
        $this->allMuted = $allMuted;
        $this->updatedAt = $now;
    }
}
