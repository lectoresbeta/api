<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Command;

final readonly class AddPublishedBook
{
    public function __construct(
        public string $userId,
        public string $title,
        public ?string $publisher = null,
        public ?int $publicationYear = null,
        public ?string $purchaseUrl = null,
    ) {
    }
}
