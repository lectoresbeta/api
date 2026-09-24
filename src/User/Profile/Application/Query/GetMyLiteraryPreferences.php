<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Query;

final readonly class GetMyLiteraryPreferences
{
    public function __construct(public string $userId)
    {
    }
}
