<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\Command;

final readonly class SubscribeToAuthor
{
    public function __construct(
        public string $subscriberId,
        public string $authorId,
    ) {
    }
}
