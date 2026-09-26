<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Interaction\Application\Service;

use LectoresBeta\Community\Interaction\Domain\Entity\ChapterEngagement;
use LectoresBeta\Community\Interaction\Domain\Repository\ChapterEngagementRepository;
use LectoresBeta\Community\Interaction\Domain\ValueObject\ChapterId;

/**
 * La fila de contadores de un capítulo, creándola si hace falta
 * (`FEAT-COM-036`).
 *
 * Existe para que «si no hay fila, créala» esté escrito **una vez**: lo
 * necesitan apoyar y comentar, y repetido en los dos acabaría divergiendo el
 * día que alguien añada un tercer contador.
 *
 * Un capítulo que nadie ha tocado no tiene fila, y eso se lee como ceros.
 */
final readonly class ChapterCounters
{
    public function __construct(private ChapterEngagementRepository $engagements)
    {
    }

    public function of(ChapterId $chapterId): ChapterEngagement
    {
        return $this->engagements->ofChapter($chapterId) ?? new ChapterEngagement($chapterId);
    }

    public function save(ChapterEngagement $engagement): void
    {
        $this->engagements->save($engagement);
    }
}
