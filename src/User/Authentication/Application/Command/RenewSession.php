<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Command;

final readonly class RenewSession
{
    public function __construct(
        public string $refreshToken,
        public ?string $userAgent = null,
    ) {
    }
}
