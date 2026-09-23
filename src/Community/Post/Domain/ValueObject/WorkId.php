<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\ValueObject;

use LectoresBeta\Shared\Domain\ValueObject\Uuid;

/**
 * An identifier from another context, kept as a value. Community paints its
 * home page from its own projections, never by reading anybody else's tables
 * (`RN-6`).
 */
final readonly class WorkId extends Uuid
{
}
