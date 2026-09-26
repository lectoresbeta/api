<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Domain\Entity;

use LectoresBeta\Reading\AccessInvitation\Domain\Enum\AccessInvitationStatus;
use LectoresBeta\Reading\AccessInvitation\Domain\ValueObject\AccessInvitationId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;

/**
 * The author inviting a specific person to read a private work (`RN-4`: only
 * the author of the work may issue one).
 */
class AccessInvitation
{
    private string $id;

    private string $workId;

    private string $authorId;

    private string $inviteeId;

    private AccessInvitationStatus $status;

    private ?string $message = null;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct(
        AccessInvitationId $id,
        WorkId $workId,
        AuthorId $authorId,
        ReaderId $inviteeId,
        \DateTimeImmutable $now,
        ?string $message = null,
    ) {
        $this->id = $id->value();
        $this->workId = $workId->value();
        $this->authorId = $authorId->value();
        $this->inviteeId = $inviteeId->value();
        $this->status = AccessInvitationStatus::PENDING;
        $this->createdAt = $now;
        $this->message = $message;
    }

    public function id(): AccessInvitationId
    {
        return AccessInvitationId::fromString($this->id);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function authorId(): AuthorId
    {
        return AuthorId::fromString($this->authorId);
    }

    public function inviteeId(): ReaderId
    {
        return ReaderId::fromString($this->inviteeId);
    }

    public function status(): AccessInvitationStatus
    {
        return $this->status;
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    /**
     * What the author wrote with the offer. On an unpublished work, «te
     * invito a leer lo que llevo» says far more than a bare notice.
     */
    public function message(): ?string
    {
        return $this->message;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function resolvedAt(): ?\DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function accept(\DateTimeImmutable $now): void
    {
        $this->resolve(AccessInvitationStatus::ACCEPTED, $now);
    }

    public function decline(\DateTimeImmutable $now): void
    {
        $this->resolve(AccessInvitationStatus::DECLINED, $now);
    }

    public function cancel(\DateTimeImmutable $now): void
    {
        $this->resolve(AccessInvitationStatus::CANCELLED, $now);
    }

    private function resolve(AccessInvitationStatus $status, \DateTimeImmutable $now): void
    {
        if (!$this->status->isOpen()) {
            return;
        }

        $this->status = $status;
        $this->resolvedAt = $now;
    }
}
