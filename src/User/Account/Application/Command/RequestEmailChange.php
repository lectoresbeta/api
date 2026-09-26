<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Command;

final readonly class RequestEmailChange
{
    public function __construct(
        public string $userId,
        public string $newEmail,
        public ?string $currentPassword,
    ) {
    }
}
