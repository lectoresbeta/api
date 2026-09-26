<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Command;

final readonly class UpdateMyCover
{
    public function __construct(
        public string $userId,
        public ?string $image,
    ) {
    }
}
