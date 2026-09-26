<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Application\Query;

final readonly class ListOpenSanctions
{
    public function __construct(
        public int $page = 1,
        public int $perPage = 20,
    ) {
    }
}
