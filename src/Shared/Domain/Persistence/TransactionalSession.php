<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Persistence;

/**
 * A unit of work, as a port.
 *
 * Repositories here only register changes; nothing is written until a
 * transaction closes. That is deliberate: one use case is one transaction
 * over one consistency boundary, and deciding where it begins and ends is the
 * application's job, not a repository's.
 *
 * It matters most in `Credits`. Recording that an event was processed and
 * applying the movement it caused have to commit together, or a crash between
 * them either loses the movement or lets the event be applied twice.
 */
interface TransactionalSession
{
    /**
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function execute(callable $operation): mixed;
}
