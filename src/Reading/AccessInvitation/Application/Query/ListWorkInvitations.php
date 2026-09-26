<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\Query;

final readonly class ListWorkInvitations
{
    public function __construct(
        public string $workId,
        public string $authorId,
        public ?string $status,
        public ?string $cursor,
        public ?int $limit,
    ) {
    }
}
