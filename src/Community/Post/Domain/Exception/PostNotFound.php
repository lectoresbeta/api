<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Post\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Esa publicación no existe **para quien pregunta** (`FEAT-COM-002` `RN-13`).
 *
 * Una sola respuesta para cuatro situaciones distintas: no existe, está
 * eliminada, es de otra persona y se intenta editar, o su audiencia no
 * alcanza a quien mira. Distinguirlas sería contestar a una pregunta que
 * nadie ha hecho —«¿existe esto?»— sobre algo que su autor decidió no
 * enseñar.
 */
final class PostNotFound extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function create(): self
    {
        return new self('POST_NOT_FOUND', 'That post does not exist.');
    }

    /**
     * El relato que se quiere promocionar.
     *
     * Mismo criterio y distinto código: quien publica **eligió** esa obra, así
     * que decirle que la publicación no existe sería contestarle a otra cosa.
     */
    public static function work(): self
    {
        return new self('WORK_NOT_FOUND', 'That work does not exist.');
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
