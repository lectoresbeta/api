<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Command;

/**
 * The plain password travels as a string and stops at the handler, which
 * hands it to the hasher and keeps nothing.
 */
final readonly class LogIn
{
    public function __construct(
        public string $email,
        public string $plainPassword,
        public ?string $userAgent = null,
    ) {
    }
}
