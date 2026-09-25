<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Chapter\Application\Contract\ChapterText;
use LectoresBeta\Work\Chapter\Application\Contract\ChapterTexts;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterVersionRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;

/**
 * La implementación del contrato.
 *
 * Pedir una versión que no está archivada —porque es la vigente, porque la
 * corrección es anterior al versionado, o porque no se pide ninguna— devuelve
 * el texto de ahora **marcado como tal**. Es deliberado: decir «no hay texto»
 * sería peor para quien lee una corrección que decir «este es el de ahora, y
 * puede no ser el que se leyó».
 */
final readonly class ResolveChapterText implements ChapterTexts
{
    public function __construct(
        private ChapterRepository $chapters,
        private ChapterVersionRepository $versions,
    ) {
    }

    public function ofChapter(string $chapterId, ?int $version = null): ?ChapterText
    {
        try {
            $chapter = $this->chapters->ofId(ChapterId::fromString($chapterId));
        } catch (InvalidValue) {
            return null;
        }

        if (null === $chapter) {
            return null;
        }

        if (null !== $version && $version !== $chapter->version()) {
            $archived = $this->versions->ofChapterVersion($chapter->id(), $version);

            if (null !== $archived) {
                return new ChapterText(
                    $chapter->id()->value(),
                    $chapter->workId()->value(),
                    $archived->title(),
                    $archived->content()->html,
                    $archived->wordCount(),
                    $archived->version(),
                    false,
                );
            }
        }

        return new ChapterText(
            $chapter->id()->value(),
            $chapter->workId()->value(),
            $chapter->title(),
            $chapter->content()->html,
            $chapter->wordCount(),
            $chapter->version(),
            true,
        );
    }
}
