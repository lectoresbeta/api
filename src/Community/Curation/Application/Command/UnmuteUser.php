<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Application\Command;

final readonly class UnmuteUser
{
    public function __construct(
        public string $memberId,
        public string $mutedId,
    ) {
    }
}
