<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\Query;

final readonly class GetBetaReaderGroup
{
    public function __construct(
        public string $groupId,
        public string $authorId,
    ) {
    }
}
