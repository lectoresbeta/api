<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\DTO;

/**
 * Una fila de la bandeja del autor (`FEAT-FBK-004`).
 *
 * **Sin el contenido.** Una bandeja de veinte correcciones de una novela
 * larga sería una respuesta de cientos de miles de palabras, y quien la abre
 * está decidiendo cuál leer, no leyendo.
 *
 * Tampoco lleva lo que costó: los importes viven en el historial de créditos
 * ([`FEAT-CRD-008`](../../../../../docs/features/credits/FEAT-CRD-008-credit-history.md)).
 */
final readonly class ReceivedCorrection
{
    public function __construct(
        public string $correctionId,
        public string $workId,
        public string $chapterId,
        public ?string $chapterTitle,
        public int $chapterPosition,
        public ?string $readerId,
        public ?string $authorLabel,
        public string $visibility,
        public int $questionnaireVersion,
        public string $submittedAt,
        public bool $read,
        public ?bool $helpful,
    ) {
    }
}
