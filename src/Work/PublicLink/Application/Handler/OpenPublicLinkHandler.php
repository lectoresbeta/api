<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Catalogue\Domain\Repository\CatalogueSignalRepository;
use LectoresBeta\Work\Chapter\Application\Contract\CorrectionBriefs;
use LectoresBeta\Work\Chapter\Domain\Entity\Chapter;
use LectoresBeta\Work\Chapter\Domain\Repository\ChapterRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\PublicLink\Application\DTO\PublicChapterPage;
use LectoresBeta\Work\PublicLink\Application\DTO\PublicChapterSummary;
use LectoresBeta\Work\PublicLink\Application\DTO\PublicWorkPage;
use LectoresBeta\Work\PublicLink\Application\Query\OpenPublicLink;
use LectoresBeta\Work\PublicLink\Domain\Entity\PublicLink;
use LectoresBeta\Work\PublicLink\Domain\Exception\PublicLinkRefused;
use LectoresBeta\Work\PublicLink\Domain\Repository\PublicLinkRepository;
use LectoresBeta\Work\PublicLink\Domain\Service\PublicLinkToken;

/**
 * Abrir un enlace público (`FEAT-WRK-010`).
 *
 * Tres negativas distintas y las tres importan:
 *
 * - un token que **nunca existió** es un `404`. No hay nada que contar;
 * - uno revocado, caducado o de una obra bloqueada es un `410` (`RN-10`).
 *   Quien lo tiene sabe que existió, y fingir lo contrario no protege nada;
 * - quien lo abre **con sesión** recibe un `409` con el identificador de la
 *   obra, para que su cliente le lleve al flujo normal
 *   ([`FEAT-FBK-008`](../../../../../docs/features/feedback/FEAT-FBK-008-public-link-correction.md)
 *   `RN-1`). Sin esta tercera, el autor podría pegar el enlace en su muro y
 *   conseguir que usuarios registrados le corrigieran gratis: él se ahorraría
 *   los créditos y ellos perderían los suyos.
 *
 * Las preguntas del capítulo se piden al contrato de `Work` en vez de
 * rehacerse aquí: es el único sitio que sabe que el último capítulo responde
 * más preguntas que los demás, y tener ese filtro dos veces sería tenerlo mal
 * en uno de los dos.
 */
final readonly class OpenPublicLinkHandler
{
    public function __construct(
        private PublicLinkRepository $links,
        private WorkRepository $works,
        private ChapterRepository $chapters,
        private CorrectionBriefs $briefs,
        private CatalogueSignalRepository $signals,
        private Clock $clock,
    ) {
    }

    public function page(OpenPublicLink $query): PublicWorkPage
    {
        [, $work] = $this->opened($query);

        $visible = array_values(array_filter(
            $this->chapters->ofWork($work->id()),
            // `RN-13`: el capítulo que su autor ha ocultado lo está también
            // aquí. El enlace abre una puerta, no la levanta.
            static fn (Chapter $chapter): bool => !$chapter->isHidden() && !$chapter->isBlocked(),
        ));

        usort($visible, static fn (Chapter $a, Chapter $b): int => $a->position() <=> $b->position());

        return new PublicWorkPage(
            $work->id()->value(),
            $work->title()->value(),
            $work->synopsis(),
            $work->isAdultsOnly(),
            array_map(
                static fn (Chapter $chapter): PublicChapterSummary => new PublicChapterSummary(
                    $chapter->id()->value(),
                    $chapter->position(),
                    $chapter->title(),
                    $chapter->wordCount(),
                ),
                $visible,
            ),
        );
    }

    public function chapter(OpenPublicLink $query): PublicChapterPage
    {
        [, $work] = $this->opened($query);

        $chapter = $this->chapterOf($query->chapterId);

        if (null === $chapter
            || !$chapter->workId()->equals($work->id())
            || $chapter->isHidden()
            || $chapter->isBlocked()
        ) {
            throw PublicLinkRefused::gone();
        }

        $brief = $this->briefs->ofChapter($chapter->id()->value());

        return new PublicChapterPage(
            $chapter->id()->value(),
            $work->id()->value(),
            $work->title()->value(),
            $chapter->position(),
            $chapter->title(),
            $chapter->content()->html,
            $chapter->wordCount(),
            $chapter->version(),
            null === $brief ? 0 : $brief->questionnaireVersion,
            // Lo que valdría si la escribiera alguien con cuenta. Sale de la
            // señal que este contexto ya mantiene para la insignia del
            // catálogo: `Work` no calcula precios, repite el último que
            // `Credits` anunció.
            $this->signals->signalOf($chapter->id())?->credits(),
            null === $brief ? [] : $brief->questions,
        );
    }

    /**
     * @return array{0: PublicLink, 1: Work}
     */
    private function opened(OpenPublicLink $query): array
    {
        $link = $this->links->ofTokenHash(PublicLinkToken::hash($query->token));

        if (null === $link) {
            throw PublicLinkRefused::linkNotYours();
        }

        $work = $this->works->ofId($link->workId());

        if (null === $work) {
            throw PublicLinkRefused::gone();
        }

        if (!$link->isUsableAt($this->clock->now()) || $work->isBlocked() || $work->isArchived()) {
            throw PublicLinkRefused::gone();
        }

        if (null !== $query->readerId) {
            throw PublicLinkRefused::forSignedInReader($work->id()->value());
        }

        return [$link, $work];
    }

    private function chapterOf(?string $chapterId): ?Chapter
    {
        if (null === $chapterId) {
            return null;
        }

        try {
            return $this->chapters->ofId(ChapterId::fromString($chapterId));
        } catch (InvalidValue) {
            return null;
        }
    }
}
