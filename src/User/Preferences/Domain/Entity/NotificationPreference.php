<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationChannel;
use LectoresBeta\User\Preferences\Domain\Enum\NotificationTopic;

/**
 * One explicit choice: this topic, this channel, on or off.
 *
 * Only choices that differ from the default are stored. A row per topic and
 * channel — instead of a column per topic — is what lets a new topic ship
 * without a migration (`FEAT-USR-039`).
 */
class NotificationPreference
{
    private string $userId;

    /**
     * Held as the backed value and handed out as the enum.
     *
     * It is part of the primary key, and Doctrine's XML mapping does not
     * allow an enum on an identifier — the XSD rejects `enum-type` there.
     * Same trade-off the identifiers make, for the same reason.
     */
    private string $topic;

    private string $channel;

    private bool $enabled;

    private \DateTimeImmutable $updatedAt;

    public function __construct(
        UserId $userId,
        NotificationTopic $topic,
        NotificationChannel $channel,
        bool $enabled,
        \DateTimeImmutable $now,
    ) {
        $this->userId = $userId->value();
        $this->topic = $topic->value;
        $this->channel = $channel->value;
        $this->enabled = $enabled;
        $this->updatedAt = $now;
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function topic(): NotificationTopic
    {
        return NotificationTopic::from($this->topic);
    }

    public function channel(): NotificationChannel
    {
        return NotificationChannel::from($this->channel);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function change(bool $enabled, \DateTimeImmutable $now): void
    {
        $this->enabled = $enabled;
        $this->updatedAt = $now;
    }
}
