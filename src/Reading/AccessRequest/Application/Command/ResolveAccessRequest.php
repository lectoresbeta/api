<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Application\Command;

final readonly class ResolveAccessRequest
{
    public function __construct(
        public string $requestId,
        public string $authorId,
        public ?string $decision,
    ) {
    }
}
