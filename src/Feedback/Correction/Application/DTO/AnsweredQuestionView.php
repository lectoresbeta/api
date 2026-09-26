<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\DTO;

/**
 * Una pregunta con su respuesta.
 *
 * `statement` puede venir vacío si la versión del cuestionario ya no está —no
 * debería ocurrir, porque las versiones se conservan— y entonces la respuesta
 * se enseña igual: el texto que alguien escribió no se esconde porque falte
 * su enunciado.
 */
final readonly class AnsweredQuestionView
{
    public function __construct(
        public string $questionId,
        public int $position,
        public string $statement,
        public string $text,
        public int $wordCount,
    ) {
    }
}
