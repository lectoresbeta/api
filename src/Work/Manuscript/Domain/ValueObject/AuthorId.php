<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Domain\ValueObject;

use LectoresBeta\Shared\Domain\ValueObject\Uuid;

/**
 * The author, as `Work` knows them: an identifier and nothing else. This
 * context never loads a `User`.
 */
final readonly class AuthorId extends Uuid
{
}
