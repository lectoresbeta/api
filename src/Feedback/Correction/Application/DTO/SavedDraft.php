<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\DTO;

final readonly class SavedDraft
{
    public function __construct(
        public string $correctionId,
        public int $questionnaireVersion,
        /** El borrador se escribió contra una versión que ya no es la vigente. */
        public bool $questionnaireVersionChanged,
        /** Si la corrección no existía y este guardado la ha empezado. */
        public bool $started,
    ) {
    }
}
