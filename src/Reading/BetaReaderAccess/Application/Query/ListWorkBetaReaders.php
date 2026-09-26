<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Query;

final readonly class ListWorkBetaReaders
{
    public function __construct(
        public string $workId,
        public string $authorId,
        public ?int $limit,
        public ?string $cursor,
    ) {
    }
}
