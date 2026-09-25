<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Contract;

/**
 * Cómo se llama un capítulo y dónde está, sin su texto.
 *
 * Lo pide quien tiene que **nombrar** un capítulo en una lista —«capítulo 3
 * de *La ciudad de los pájaros*»— y no tiene ninguna necesidad de sus treinta
 * mil palabras.
 */
final readonly class ChapterHeading
{
    public function __construct(
        public string $chapterId,
        public string $workId,
        public ?string $title,
        public int $position,
        public string $workTitle,
    ) {
    }
}
