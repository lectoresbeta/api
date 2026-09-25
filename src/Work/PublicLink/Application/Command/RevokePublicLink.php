<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\Command;

final readonly class RevokePublicLink
{
    public function __construct(
        public string $publicLinkId,
        public string $authorId,
    ) {
    }
}
