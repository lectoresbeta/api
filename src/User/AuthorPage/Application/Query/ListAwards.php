<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Query;

final readonly class ListAwards
{
    public function __construct(
        public string $userId,
        public ?string $viewerId,
    ) {
    }
}
