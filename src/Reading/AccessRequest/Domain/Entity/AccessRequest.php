<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Domain\Entity;

use LectoresBeta\Reading\AccessRequest\Domain\Enum\AccessRequestStatus;
use LectoresBeta\Reading\AccessRequest\Domain\ValueObject\AccessRequestId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;

/**
 * Somebody asking to be a beta reader of a work on request (`RN-3`: a private
 * work does not take requests at all).
 *
 * One open request per (reader, work), enforced by a partial unique index
 * over the pending ones. Asking again after a rejection has to stay possible.
 */
class AccessRequest
{
    private string $id;

    private string $workId;

    private string $requesterId;

    private string $authorId;

    private AccessRequestStatus $status;

    private ?string $message = null;

    private \DateTimeImmutable $requestedAt;

    private ?\DateTimeImmutable $resolvedAt = null;

    public function __construct(
        AccessRequestId $id,
        WorkId $workId,
        ReaderId $requesterId,
        AuthorId $authorId,
        \DateTimeImmutable $now,
        ?string $message = null,
    ) {
        $this->id = $id->value();
        $this->workId = $workId->value();
        $this->requesterId = $requesterId->value();
        $this->authorId = $authorId->value();
        $this->status = AccessRequestStatus::PENDING;
        $this->requestedAt = $now;
        $this->message = $message;
    }

    public function id(): AccessRequestId
    {
        return AccessRequestId::fromString($this->id);
    }

    public function workId(): WorkId
    {
        return WorkId::fromString($this->workId);
    }

    public function requesterId(): ReaderId
    {
        return ReaderId::fromString($this->requesterId);
    }

    public function authorId(): AuthorId
    {
        return AuthorId::fromString($this->authorId);
    }

    public function status(): AccessRequestStatus
    {
        return $this->status;
    }

    public function accept(\DateTimeImmutable $now): void
    {
        $this->resolve(AccessRequestStatus::ACCEPTED, $now);
    }

    public function reject(\DateTimeImmutable $now): void
    {
        $this->resolve(AccessRequestStatus::REJECTED, $now);
    }

    public function cancel(\DateTimeImmutable $now): void
    {
        $this->resolve(AccessRequestStatus::CANCELLED, $now);
    }

    private function resolve(AccessRequestStatus $status, \DateTimeImmutable $now): void
    {
        if (!$this->status->isOpen()) {
            return;
        }

        $this->status = $status;
        $this->resolvedAt = $now;
    }
}
