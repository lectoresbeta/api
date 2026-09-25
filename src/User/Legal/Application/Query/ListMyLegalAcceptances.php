<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Application\Query;

final readonly class ListMyLegalAcceptances
{
    public function __construct(public string $userId)
    {
    }
}
