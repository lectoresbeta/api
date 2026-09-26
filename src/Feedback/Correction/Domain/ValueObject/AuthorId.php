<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\ValueObject;

use LectoresBeta\Shared\Domain\ValueObject\Uuid;

/**
 * A reference to something owned by another context, held as an identifier.
 * Feedback owns the answers, never the questions or the text.
 */
final readonly class AuthorId extends Uuid
{
}
