<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Query;

final readonly class GetMyPrivacySettings
{
    public function __construct(public string $userId)
    {
    }
}
