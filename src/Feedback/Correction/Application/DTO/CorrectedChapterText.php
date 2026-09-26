<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\DTO;

/**
 * El capítulo tal y como lo leyó quien corrigió.
 *
 * `isCurrentVersion` es el dato importante y no un detalle: si es `true`,
 * esto **puede no ser** lo que esa persona leyó, y quien lo enseñe tiene que
 * poder decirlo. Callarlo haría creer que una corrección habla de este texto
 * cuando habla de otro.
 */
final readonly class CorrectedChapterText
{
    public function __construct(
        public string $correctionId,
        public string $chapterId,
        public ?string $title,
        public string $contentHtml,
        public int $wordCount,
        public int $version,
        public bool $isCurrentVersion,
    ) {
    }
}
