<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Application\Health;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Health\CheckResult;
use LectoresBeta\Shared\Domain\Health\HealthCheck;
use LectoresBeta\Shared\Domain\Health\HealthReport;

/**
 * Runs every registered check and builds the report.
 *
 * It does not know which checks exist: the container hands them over, tagged.
 * Adding a dependency to the health endpoint is therefore writing one class
 * in `Infrastructure` and nothing else.
 *
 * Every check runs, even after one has already failed. Stopping at the first
 * failure would be faster and much less useful — «the database is down» and
 * «the database and the broker are down» are different incidents.
 */
final readonly class CheckHealth
{
    /**
     * @param iterable<HealthCheck> $checks
     */
    public function __construct(
        private iterable $checks,
        private Clock $clock,
    ) {
    }

    public function __invoke(): HealthReport
    {
        $results = [];

        foreach ($this->checks as $check) {
            $results[] = $check->run();
        }

        /** @var list<CheckResult> $results */
        return HealthReport::of($results, $this->clock->now());
    }
}
