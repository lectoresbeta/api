<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\Command;

final readonly class DeleteBetaReaderGroup
{
    public function __construct(
        public string $groupId,
        public string $authorId,
    ) {
    }
}
