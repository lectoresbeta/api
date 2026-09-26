<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Command;

final readonly class SubmitPublicCorrection
{
    /**
     * @param array<string, string> $answers texto por identificador de pregunta
     */
    public function __construct(
        public string $token,
        public string $chapterId,
        public array $answers,
        public bool $acceptedTerms,
        public ?string $authorLabel,
        /** Quién lo envía, **si es alguien**. Nulo es el caso normal. */
        public ?string $readerId = null,
    ) {
    }
}
