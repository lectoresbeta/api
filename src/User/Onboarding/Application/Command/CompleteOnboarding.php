<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Application\Command;

final readonly class CompleteOnboarding
{
    public function __construct(public string $userId)
    {
    }
}
