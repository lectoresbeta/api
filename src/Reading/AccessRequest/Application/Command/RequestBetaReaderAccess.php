<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Application\Command;

final readonly class RequestBetaReaderAccess
{
    public function __construct(
        public string $workId,
        public string $requesterId,
        public ?string $message,
    ) {
    }
}
