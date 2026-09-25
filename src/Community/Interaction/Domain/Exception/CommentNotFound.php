<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Ese comentario no existe **para quien pregunta** (`FEAT-COM-006`).
 *
 * Mismo criterio que con una publicación: no existe, está eliminado, o es de
 * otra persona y se intenta editar. Distinguirlos sería contestar a una
 * pregunta que nadie ha hecho sobre algo que no se puede ver.
 */
final class CommentNotFound extends \DomainException implements BusinessFailure
{
    public static function create(): self
    {
        return new self('That comment does not exist.');
    }

    public function errorCode(): string
    {
        return 'COMMENT_NOT_FOUND';
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
