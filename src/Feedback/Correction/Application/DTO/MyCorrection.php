<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\DTO;

/**
 * Una corrección propia, vista por quien la escribió (`FEAT-FBK-010`).
 *
 * `earnedCredits` es **nulo mientras el importe no ha llegado**, nunca cero:
 * un cero es una afirmación falsa sobre el trabajo de alguien, y la cifra
 * viene de una proyección que puede ir un instante por detrás.
 *
 * No lleva si el autor la ha leído. Es información sobre otra persona, y
 * convertiría una entrega en una conversación que nadie ha aceptado tener
 * (`F-14`).
 */
final readonly class MyCorrection
{
    public function __construct(
        public string $correctionId,
        public string $workId,
        public string $chapterId,
        public ?string $chapterTitle,
        public int $chapterPosition,
        public string $workTitle,
        public string $status,
        public ?string $submittedAt,
        public ?int $earnedCredits,
        public ?bool $helpful,
        public bool $replied,
        public bool $tipped,
    ) {
    }
}
