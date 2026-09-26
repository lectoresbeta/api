<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Entity;

use LectoresBeta\Feedback\Correction\Domain\ValueObject\AuthorId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionReplyId;

/**
 * The author answering a correction. Only the author of the work replies
 * (`RN-4`).
 */
class CorrectionReply
{
    private string $id;

    private string $correctionId;

    private string $authorId;

    private string $body;

    private \DateTimeImmutable $createdAt;

    private ?\DateTimeImmutable $editedAt = null;

    public function __construct(
        CorrectionReplyId $id,
        CorrectionId $correctionId,
        AuthorId $authorId,
        string $body,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->correctionId = $correctionId->value();
        $this->authorId = $authorId->value();
        $this->body = trim($body);
        $this->createdAt = $now;
    }

    public function id(): CorrectionReplyId
    {
        return CorrectionReplyId::fromString($this->id);
    }

    public function correctionId(): CorrectionId
    {
        return CorrectionId::fromString($this->correctionId);
    }

    public function body(): string
    {
        return $this->body;
    }

    public function edit(string $body, \DateTimeImmutable $now): void
    {
        $this->body = trim($body);
        $this->editedAt = $now;
    }
}
