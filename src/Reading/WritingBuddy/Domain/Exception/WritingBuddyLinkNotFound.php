<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Ese vínculo no existe, o no es tuyo, o ya está resuelto (`FEAT-RDG-009`).
 *
 * Los tres responden igual: quién le ha propuesto qué a quién no es
 * información que se le deba a nadie que no sea parte.
 */
final class WritingBuddyLinkNotFound extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That writing buddy proposal is not there.');
    }

    public function errorCode(): string
    {
        return 'WRITING_BUDDY_LINK_NOT_FOUND';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
