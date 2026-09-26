<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Health;

/**
 * The outcome of one check.
 *
 * `detail` only ever carries text this project wrote — «the configured
 * transport is not AMQP» and the like. **The message of a caught exception
 * never goes in here.** A health endpoint is public, and
 * «connection to server at 10.0.3.7 port 5432 refused» hands a stranger the
 * topology of the system. The exception belongs in the log, where the people
 * who need it can read it.
 */
final readonly class CheckResult
{
    private function __construct(
        public string $name,
        public HealthStatus $status,
        public float $durationMs,
        public ?string $detail = null,
    ) {
    }

    public static function up(string $name, float $durationMs): self
    {
        return new self($name, HealthStatus::UP, $durationMs);
    }

    public static function down(string $name, float $durationMs): self
    {
        return new self($name, HealthStatus::DOWN, $durationMs);
    }

    public static function skipped(string $name, string $reason): self
    {
        return new self($name, HealthStatus::SKIPPED, 0.0, $reason);
    }

    public function isFailure(): bool
    {
        return $this->status->isFailure();
    }
}
