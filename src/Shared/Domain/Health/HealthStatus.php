<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Health;

/**
 * How one check came out.
 *
 * `SKIPPED` exists because a check can be legitimately inapplicable: there is
 * no RabbitMQ to reach when the configured transport is in-memory. Reporting
 * that as `DOWN` would make the test suite depend on a broker, and reporting
 * it as `UP` would be a lie — the difference between «it works» and «it was
 * not asked» is exactly what somebody reading a health report needs.
 */
enum HealthStatus: string
{
    case UP = 'up';
    case DOWN = 'down';
    case SKIPPED = 'skipped';

    public function isFailure(): bool
    {
        return self::DOWN === $this;
    }
}
