<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Subscription\Application\Query;

final readonly class GetAuthorSubscription
{
    public function __construct(
        public string $subscriberId,
        public string $authorId,
    ) {
    }
}
