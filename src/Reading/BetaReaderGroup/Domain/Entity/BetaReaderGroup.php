<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Domain\Entity;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderGroup\Domain\ValueObject\BetaReaderGroupId;

/**
 * A list of beta readers an author keeps for themselves.
 *
 * Belonging to a group grants nothing on its own: whether a group can be
 * given access to a work in one go is still open (`R-4`). Today it is an
 * address book.
 */
class BetaReaderGroup
{
    private string $id;

    private string $authorId;

    private string $name;

    private \DateTimeImmutable $createdAt;

    public function __construct(
        BetaReaderGroupId $id,
        AuthorId $authorId,
        string $name,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->authorId = $authorId->value();
        $this->name = trim($name);
        $this->createdAt = $now;
    }

    public function id(): BetaReaderGroupId
    {
        return BetaReaderGroupId::fromString($this->id);
    }

    public function authorId(): AuthorId
    {
        return AuthorId::fromString($this->authorId);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function rename(string $name): void
    {
        $this->name = trim($name);
    }
}
