<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Ese capítulo no está, al menos para quien pregunta (`FEAT-COM-036`).
 *
 * `create()` es el mismo `404` para «no existe», «es un borrador ajeno», «es
 * para adultos y no tienes edad» y «hace falta ser lector beta». Es la regla
 * de siempre con el contenido inédito: un `403` sobre una obra que no se
 * puede ver ya confirma que existe.
 *
 * `becauseTheAuthorTookCommentsDown()` sí distingue, y puede: para llegar
 * hasta ahí hay que **poder ver el capítulo**, así que no revela nada que
 * quien pregunta no tuviera ya delante. Decirlo es lo correcto — quien
 * escribe un comentario merece saber por qué no sale.
 */
final class ChapterNotReachable extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function create(): self
    {
        return new self('CHAPTER_NOT_FOUND', FailureKind::NOT_FOUND, 'That chapter is not there.');
    }

    public static function becauseTheAuthorTookCommentsDown(): self
    {
        return new self(
            'COMMENTS_NOT_ACCEPTED',
            FailureKind::FORBIDDEN,
            'That author does not take comments from you.',
        );
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return $this->failureKind;
    }
}
