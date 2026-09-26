<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Application\Command;

final readonly class BlockUser
{
    public function __construct(
        public string $blockerId,
        public string $blockedId,
    ) {
    }
}
