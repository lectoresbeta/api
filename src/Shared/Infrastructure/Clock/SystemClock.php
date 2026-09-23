<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Clock;

use LectoresBeta\Shared\Domain\Clock\Clock;

final class SystemClock implements Clock
{
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
