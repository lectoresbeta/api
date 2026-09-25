<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Command;

/**
 * Sustituir el texto de un capítulo (`FEAT-WRK-005`).
 *
 * El contenido entero y no un parche: es lo que hace un editor al guardar, y
 * un parche obligaría a este contexto a entender de diferencias sobre HTML
 * saneado, que es un problema mucho mayor del que resuelve.
 */
final readonly class UpdateChapter
{
    public function __construct(
        public string $chapterId,
        public string $authorId,
        public ?string $title,
        public bool $titleWasSent,
        public string $contentHtml,
    ) {
    }
}
