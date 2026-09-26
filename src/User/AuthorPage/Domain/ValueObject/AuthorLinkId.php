<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\ValueObject;

use LectoresBeta\Shared\Domain\ValueObject\Uuid;

/**
 * La identidad de una referencia de la página de autor (`FEAT-USR-015`).
 */
final readonly class AuthorLinkId extends Uuid
{
}
