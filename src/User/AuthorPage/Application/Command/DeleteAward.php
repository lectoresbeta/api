<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Command;

final readonly class DeleteAward
{
    public function __construct(
        public string $userId,
        public string $awardId,
    ) {
    }
}
