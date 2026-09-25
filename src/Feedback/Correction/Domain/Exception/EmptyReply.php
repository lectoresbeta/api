<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Una respuesta vacía no es una respuesta. Para no decir nada ya está no
 * contestar, que además no genera un aviso.
 */
final class EmptyReply extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('A reply cannot be empty.');
    }

    public function errorCode(): string
    {
        return 'EMPTY_REPLY';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
