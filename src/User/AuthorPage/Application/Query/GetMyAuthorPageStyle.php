<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Application\Query;

final readonly class GetMyAuthorPageStyle
{
    public function __construct(public string $userId)
    {
    }
}
