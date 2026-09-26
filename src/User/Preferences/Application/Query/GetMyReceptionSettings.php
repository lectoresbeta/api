<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Query;

final readonly class GetMyReceptionSettings
{
    public function __construct(public string $userId)
    {
    }
}
