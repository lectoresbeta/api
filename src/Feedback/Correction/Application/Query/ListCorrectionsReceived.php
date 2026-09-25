<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Query;

final readonly class ListCorrectionsReceived
{
    public function __construct(
        public string $ownerId,
        public ?string $workId = null,
        public ?string $chapterId = null,
        public bool $unreadOnly = false,
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}
