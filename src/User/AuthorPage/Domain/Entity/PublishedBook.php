<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\PublishedBookId;

/**
 * A book the person published **outside** the platform (`FEAT-USR-029`).
 *
 * Not a `Work`: it has no content here, no beta readers and no credits. The
 * interface calls a `Work` a «relato»; this is the already-edited book that
 * decorates an author's page, and the two live in different contexts on
 * purpose.
 */
class PublishedBook
{
    private string $id;

    private string $userId;

    private string $title;

    private ?string $publisher = null;

    private ?int $publicationYear = null;

    private ?string $purchaseUrl = null;

    private ?string $coverUrl = null;

    private int $position;

    private \DateTimeImmutable $createdAt;

    public function __construct(
        PublishedBookId $id,
        UserId $userId,
        string $title,
        int $position,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->userId = $userId->value();
        $this->title = $title;
        $this->position = $position;
        $this->createdAt = $now;
    }

    public function id(): PublishedBookId
    {
        return PublishedBookId::fromString($this->id);
    }

    public function userId(): UserId
    {
        return UserId::fromString($this->userId);
    }

    public function title(): string
    {
        return $this->title;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function describe(?string $publisher, ?int $publicationYear, ?string $purchaseUrl): void
    {
        $this->publisher = $publisher;
        $this->publicationYear = $publicationYear;
        $this->purchaseUrl = $purchaseUrl;
    }

    public function setCover(?string $coverUrl): void
    {
        $this->coverUrl = $coverUrl;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }
}
