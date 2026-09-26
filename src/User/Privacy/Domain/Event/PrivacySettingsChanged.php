<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Domain\Event;

use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Event\IntegrationEvent;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Privacy\Domain\Enum\PrivacyAudience;

/**
 * Somebody changed who can reach them (`FEAT-USR-038`).
 *
 * It carries **the settings, not the profile**: the read models that depend
 * on visibility — the wall, the catalogue, the profile — need to know what
 * the new limits are, and nothing else about the person.
 *
 * It does not rewrite the past (`RN-3`). Whoever already commented stays
 * commented; what changes is what happens from now on.
 */
final readonly class PrivacySettingsChanged implements IntegrationEvent
{
    public function __construct(
        private EventId $eventId,
        private UserId $userId,
        private PrivacyAudience $profileVisibility,
        private PrivacyAudience $commentPermission,
        private PrivacyAudience $messagePermission,
        private \DateTimeImmutable $changedAt,
    ) {
    }

    public function eventId(): string
    {
        return $this->eventId->value();
    }

    public function eventName(): string
    {
        return 'PrivacySettingsChanged';
    }

    public function occurredAt(): \DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function payload(): array
    {
        return [
            'userId' => $this->userId->value(),
            'profileVisibility' => $this->profileVisibility->value,
            'commentPermission' => $this->commentPermission->value,
            'messagePermission' => $this->messagePermission->value,
            'changedAt' => $this->changedAt->format(\DATE_ATOM),
        ];
    }
}
