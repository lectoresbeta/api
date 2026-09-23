<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Health;

/**
 * Something the application depends on and can be asked about.
 *
 * Implementations live in `Infrastructure`, because what they probe —a
 * database connection, a broker— is infrastructure. This contract is here so
 * that the thing that runs them does not have to know what any of them are.
 *
 * An implementation **never throws**: a check that fails is a `DOWN` result,
 * not an exception. A health endpoint that returns 500 because a probe blew
 * up tells the operator nothing about which dependency broke.
 */
interface HealthCheck
{
    /**
     * A short, stable key: `database`, `message_broker`. It is part of the
     * public response, so renaming one breaks whoever parses it.
     */
    public function name(): string;

    public function run(): CheckResult;
}
