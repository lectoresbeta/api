<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Application\Query;

final readonly class GetUserSheet
{
    public function __construct(
        public string $moderatorId,
        public string $userId,
    ) {
    }
}
