<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Command;

final readonly class ReplyToModeration
{
    public function __construct(
        public string $claimId,
        public string $authorId,
        public ?string $body,
    ) {
    }
}
