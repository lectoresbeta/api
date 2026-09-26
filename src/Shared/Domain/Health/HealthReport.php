<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Health;

/**
 * Every check, and the single verdict that comes out of them.
 *
 * The verdict is deliberately pessimistic: one dependency down means the
 * whole thing is down. A health endpoint that answers «mostly fine» forces
 * whoever reads it to decide what that means, and at three in the morning
 * nobody wants to decide anything.
 */
final readonly class HealthReport
{
    /**
     * @param list<CheckResult> $results
     */
    private function __construct(
        public HealthStatus $status,
        public array $results,
        public \DateTimeImmutable $checkedAt,
    ) {
    }

    /**
     * @param list<CheckResult> $results
     */
    public static function of(array $results, \DateTimeImmutable $checkedAt): self
    {
        $failed = array_filter($results, static fn (CheckResult $result): bool => $result->isFailure());

        return new self(
            [] === $failed ? HealthStatus::UP : HealthStatus::DOWN,
            array_values($results),
            $checkedAt,
        );
    }

    public function isHealthy(): bool
    {
        return HealthStatus::UP === $this->status;
    }
}
