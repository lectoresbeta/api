<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Command;

final readonly class LogOut
{
    public function __construct(public string $refreshToken)
    {
    }
}
