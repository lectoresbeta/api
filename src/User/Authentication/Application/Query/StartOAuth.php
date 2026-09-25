<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Query;

final readonly class StartOAuth
{
    public function __construct(
        public string $provider,
        public ?string $redirectUri = null,
    ) {
    }
}
