<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Infrastructure\Health;

use Doctrine\DBAL\Connection;
use LectoresBeta\Shared\Domain\Health\CheckResult;
use LectoresBeta\Shared\Domain\Health\HealthCheck;
use Psr\Log\LoggerInterface;

/**
 * Asks PostgreSQL whether it is there.
 *
 * `SELECT 1` and nothing else: the point is to find out if the connection
 * works, not to measure the database. A health check that runs a real query
 * ends up being the slowest request on the system and gets called every few
 * seconds by whatever is probing it.
 */
final readonly class DatabaseHealthCheck implements HealthCheck
{
    public function __construct(
        private Connection $connection,
        private LoggerInterface $logger,
    ) {
    }

    public function name(): string
    {
        return 'database';
    }

    public function run(): CheckResult
    {
        $startedAt = microtime(true);

        try {
            $this->connection->executeQuery('SELECT 1')->free();

            return CheckResult::up($this->name(), $this->elapsedMs($startedAt));
        } catch (\Throwable $failure) {
            // The exception goes to the log and stops there. The response says
            // «down» and no more: a public endpoint must not hand out host
            // names, ports or driver errors.
            $this->logger->error('El chequeo de salud de PostgreSQL ha fallado.', [
                'exception' => $failure,
            ]);

            return CheckResult::down($this->name(), $this->elapsedMs($startedAt));
        }
    }

    private function elapsedMs(float $startedAt): float
    {
        return round((microtime(true) - $startedAt) * 1000, 2);
    }
}
