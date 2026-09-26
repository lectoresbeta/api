<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\Query;

final readonly class ListMyBetaReaderGroups
{
    public function __construct(
        public string $authorId,
        public ?string $query,
    ) {
    }
}
