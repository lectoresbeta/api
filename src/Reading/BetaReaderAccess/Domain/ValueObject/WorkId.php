<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject;

use LectoresBeta\Shared\Domain\ValueObject\Uuid;

/**
 * An identifier that reached this context in an integration event. Reading
 * never loads a user or a work: it only remembers who may read what.
 */
final readonly class WorkId extends Uuid
{
}
