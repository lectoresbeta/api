<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\Query;

final readonly class ListPublicLinks
{
    public function __construct(
        public string $workId,
        public string $authorId,
    ) {
    }
}
