<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Query;

final readonly class ListChapterComments
{
    public function __construct(
        public string $readerId,
        public string $chapterId,
        public ?string $cursor = null,
        public ?int $limit = null,
    ) {
    }
}
