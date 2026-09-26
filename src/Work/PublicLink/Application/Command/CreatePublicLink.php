<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\Command;

final readonly class CreatePublicLink
{
    public function __construct(
        public string $workId,
        public string $authorId,
        public ?int $maxCorrections,
        public ?string $expiresAt,
        public ?string $label,
    ) {
    }
}
