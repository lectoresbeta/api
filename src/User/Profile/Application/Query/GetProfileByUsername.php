<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Query;

final readonly class GetProfileByUsername
{
    public function __construct(
        public string $username,
        public ?string $viewerId,
    ) {
    }
}
