<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Clock;

/**
 * Time, as a port.
 *
 * Calling `new \DateTimeImmutable()` inside a business rule makes that rule
 * impossible to test: there is no way to write a test for "the alias expires
 * after 30 days" if the domain asks the operating system what time it is.
 */
interface Clock
{
    public function now(): \DateTimeImmutable;
}
