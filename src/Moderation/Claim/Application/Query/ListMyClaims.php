<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Application\Query;

final readonly class ListMyClaims
{
    public function __construct(public string $reporterId)
    {
    }
}
