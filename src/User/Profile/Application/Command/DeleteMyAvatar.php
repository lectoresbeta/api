<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Command;

final readonly class DeleteMyAvatar
{
    public function __construct(public string $userId)
    {
    }
}
