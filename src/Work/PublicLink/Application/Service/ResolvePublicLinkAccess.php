<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\Service;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Catalogue\Domain\Repository\CatalogueSignalRepository;
use LectoresBeta\Work\Chapter\Domain\Entity\Chapter;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\PublicLink\Application\Contract\PublicLinkAccess;
use LectoresBeta\Work\PublicLink\Application\Contract\PublicLinkOpening;
use LectoresBeta\Work\PublicLink\Domain\Repository\PublicLinkRepository;
use LectoresBeta\Work\PublicLink\Domain\Service\PublicLinkToken;

/**
 * El lado de `Work` del enlace público (`FEAT-WRK-010`).
 *
 * Cruza cuatro conceptos de este contexto —el enlace, la obra, el capítulo y
 * la señal del catálogo— y por eso existe: montarlo **dentro** de `Work` es
 * lo que evita que quien pregunta tenga que saber que una obra bloqueada
 * cierra también esta puerta.
 *
 * Una obra bloqueada por moderación o archivada no da nada (`RN-15`). Si el
 * bloqueo solo cerrara el catálogo, bastaría con tener la URL para
 * saltárselo.
 */
final readonly class ResolvePublicLinkAccess implements PublicLinkAccess
{
    public function __construct(
        private PublicLinkRepository $links,
        private WorkRepository $works,
        private ChapterRepository $chapters,
        private CatalogueSignalRepository $signals,
        private Clock $clock,
    ) {
    }

    public function opening(string $token, ?string $chapterId = null): ?PublicLinkOpening
    {
        $link = $this->links->ofTokenHash(PublicLinkToken::hash($token));

        if (null === $link) {
            return null;
        }

        $work = $this->works->ofId($link->workId());

        if (null === $work) {
            return null;
        }

        // Bloqueada o archivada: el enlace deja de servir, no de existir.
        // Quien lo tiene recibe un `410`, que es lo que dice la verdad.
        $usable = $link->isUsableAt($this->clock->now()) && !$work->isBlocked() && !$work->isArchived();

        $chapter = $this->chapterOf($chapterId);
        $served = null !== $chapter
            && $chapter->workId()->equals($link->workId())
            && !$chapter->isHidden()
            && !$chapter->isBlocked();

        return new PublicLinkOpening(
            $link->id()->value(),
            $link->workId()->value(),
            $work->authorId()->value(),
            $usable,
            $link->maxCorrections(),
            $served,
            $served && null !== $chapter ? $this->signals->signalOf($chapter->id())?->credits() : null,
        );
    }

    private function chapterOf(?string $chapterId): ?Chapter
    {
        if (null === $chapterId) {
            return null;
        }

        try {
            return $this->chapters->ofId(ChapterId::fromString($chapterId));
        } catch (InvalidValue) {
            // Un identificador ilegible es un capítulo que no existe. Quien
            // pregunta por la URL puede escribir cualquier cosa.
            return null;
        }
    }
}
