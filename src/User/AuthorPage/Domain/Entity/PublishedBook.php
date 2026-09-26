<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Entity;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\AuthorPage\Domain\Exception\PublishedBookRefused;
use LectoresBeta\User\AuthorPage\Domain\Service\PublishedBookPolicy;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\PublishedBookId;
use LectoresBeta\User\AuthorPage\Domain\ValueObject\PurchaseLink;

/**
 * A book the person published **outside** the platform (`FEAT-USR-029`).
 *
 * Not a `Work`: it has no content here, no beta readers and no credits. The
 * interface calls a `Work` a «relato»; this is the already-edited book that
 * decorates an author's page, and the two live in different contexts on
 * purpose.
 *
 * That difference is the whole reason this class exists rather than a flag on
 * `Work`: a `Work` is an aggregate whose job is to guard unpublished text,
 * and the moment a bibliography entry shared it, every question that
 * aggregate answers —who may read it, what a correction costs, who gets the
 * credits— would have to grow a second, meaningless answer.
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

    private function __construct(
        PublishedBookId $id,
        UserId $userId,
        string $title,
        int $position,
        \DateTimeImmutable $now,
    ) {
        $this->id = $id->value();
        $this->userId = $userId->value();
        $this->title = self::acceptableTitle($title);
        $this->position = $position;
        $this->createdAt = $now;
    }

    public static function add(
        PublishedBookId $id,
        UserId $userId,
        string $title,
        int $position,
        \DateTimeImmutable $now,
    ): self {
        return new self($id, $userId, $title, $position, $now);
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

    public function publisher(): ?string
    {
        return $this->publisher;
    }

    public function publicationYear(): ?int
    {
        return $this->publicationYear;
    }

    public function purchaseUrl(): ?string
    {
        return $this->purchaseUrl;
    }

    public function coverUrl(): ?string
    {
        return $this->coverUrl;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * `RN-1`. Lo pregunta el agregado y no cada caso de uso: una comprobación
     * repartida por cuatro sitios es una comprobación que faltará en el
     * quinto.
     */
    public function belongsTo(UserId $userId): bool
    {
        return $this->userId === $userId->value();
    }

    public function retitle(string $title): void
    {
        $this->title = self::acceptableTitle($title);
    }

    /**
     * **La editorial es texto libre** (`RN-9`). El ejemplo del diseño dice
     * «Amazon», que no es un sello sino una plataforma de autopublicación, y
     * validarla contra un catálogo dejaría fuera justo a quien más usa este
     * campo.
     */
    public function setPublisher(?string $publisher): void
    {
        $publisher = self::trimmedOrNull($publisher);

        if (null !== $publisher && mb_strlen($publisher) > PublishedBookPolicy::PUBLISHER_MAX_LENGTH) {
            throw PublishedBookRefused::publisherTooLong();
        }

        $this->publisher = $publisher;
    }

    public function setPublicationYear(?int $year, \DateTimeImmutable $now): void
    {
        if (null !== $year && !PublishedBookPolicy::isCredibleYear($year, $now)) {
            throw PublishedBookRefused::implausibleYear();
        }

        $this->publicationYear = $year;
    }

    public function setPurchaseLink(?PurchaseLink $link): void
    {
        $this->purchaseUrl = $link?->value();
    }

    /**
     * La **clave** de almacenamiento, no una dirección (`file-uploads.md`):
     * la dirección se calcula en un solo sitio, y así mudarse a un CDN no
     * obliga a reescribir ninguna fila.
     */
    public function setCover(?string $coverKey): void
    {
        $this->coverUrl = $coverKey;
    }

    public function moveTo(int $position): void
    {
        $this->position = $position;
    }

    private static function acceptableTitle(string $title): string
    {
        $title = trim($title);

        if ('' === $title) {
            throw PublishedBookRefused::missingTitle();
        }

        if (mb_strlen($title) > PublishedBookPolicy::TITLE_MAX_LENGTH) {
            throw PublishedBookRefused::titleTooLong();
        }

        return $title;
    }

    private static function trimmedOrNull(?string $value): ?string
    {
        if (null === $value) {
            return null;
        }

        $trimmed = trim($value);

        return '' === $trimmed ? null : $trimmed;
    }
}
