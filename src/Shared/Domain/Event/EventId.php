<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Event;

use LectoresBeta\Shared\Domain\ValueObject\Uuid;

/**
 * The identity of a fact, not of the message carrying it.
 *
 * Republishing the same fact must reuse it: consumers deduplicate on this
 * value, and a fresh identifier on a retry would apply a credit twice
 * (`FEAT-CRD-011`).
 */
final readonly class EventId extends Uuid
{
}
