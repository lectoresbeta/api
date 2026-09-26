<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Command;

final readonly class DeleteMyCover
{
    public function __construct(public string $userId)
    {
    }
}
