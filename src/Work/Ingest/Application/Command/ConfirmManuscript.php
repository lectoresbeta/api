<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Ingest\Application\Command;

final readonly class ConfirmManuscript
{
    /**
     * @param list<string> $genres
     * @param list<string> $chapterTitles títulos corregidos por el autor, **en
     *                                    el orden propuesto**. Una cadena
     *                                    vacía deja el que se propuso, así que
     *                                    corregir el primero de treinta no
     *                                    obliga a reenviar los treinta
     */
    public function __construct(
        public string $authorId,
        public string $uploadId,
        public ?string $title,
        public ?string $synopsis,
        public array $genres = [],
        public array $chapterTitles = [],
    ) {
    }
}
