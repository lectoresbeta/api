<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Command;

final readonly class RevokeBetaReaderAccess
{
    public function __construct(
        public string $workId,
        public string $readerId,
        public string $authorId,
    ) {
    }
}
