<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\Query;

final readonly class SearchInvitableReaders
{
    public function __construct(
        public string $workId,
        public string $authorId,
        public ?string $query,
    ) {
    }
}
