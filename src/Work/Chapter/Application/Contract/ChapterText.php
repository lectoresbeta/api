<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Contract;

/**
 * El texto de un capítulo en una versión concreta.
 *
 * `isCurrentVersion` no es decoración: quien lo enseña tiene que poder decir
 * **«esto es lo que se leyó»** o **«esto es lo que hay ahora, y puede no ser
 * lo que se leyó»**. Dar el texto sin decir cuál de las dos cosas es sería
 * peor que no darlo.
 */
final readonly class ChapterText
{
    public function __construct(
        public string $chapterId,
        public string $workId,
        public ?string $title,
        public string $contentHtml,
        public int $wordCount,
        public int $version,
        public bool $isCurrentVersion,
    ) {
    }
}
