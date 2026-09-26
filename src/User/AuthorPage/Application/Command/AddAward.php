<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Command;

final readonly class AddAward
{
    public function __construct(
        public string $userId,
        public string $title,
        public ?string $awardedBy,
        public ?int $year,
        public ?string $note,
        public ?string $url,
    ) {
    }
}
