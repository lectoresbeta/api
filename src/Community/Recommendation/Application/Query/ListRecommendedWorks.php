<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Recommendation\Application\Query;

final readonly class ListRecommendedWorks
{
    public function __construct(
        public string $memberId,
        public ?int $howMany = null,
    ) {
    }
}
