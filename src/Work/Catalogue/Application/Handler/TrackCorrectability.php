<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Catalogue\Application\Handler;

use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\Work\Catalogue\Application\Event\ChapterCorrectabilityChanged;
use LectoresBeta\Work\Catalogue\Domain\Entity\ChapterCorrectability;
use LectoresBeta\Work\Catalogue\Domain\Repository\CatalogueSignalRepository;
use LectoresBeta\Work\Chapter\Domain\ValueObject\ChapterId;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * El filtro duro del catálogo, y su primer factor (`FEAT-WRK-012`).
 *
 * Idempotente por construcción, sin registro de hechos procesados: la última
 * palabra gana y la entidad descarta lo que llega con fecha anterior a lo que
 * ya tiene. Que el catálogo vaya unos segundos por detrás no importa — el
 * peor caso es enseñar un capítulo que acaba de dejar de ser corregible, y
 * ahí el lector recibe el mismo mensaje que si hubiera llegado tarde.
 */
final readonly class TrackCorrectability
{
    public function __construct(
        private CatalogueSignalRepository $signals,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(ChapterCorrectabilityChanged $event): void
    {
        $chapterId = ChapterId::fromString($event->chapterId);
        $known = $this->signals->correctabilityOf($chapterId);

        if (null === $known) {
            $known = new ChapterCorrectability(
                $chapterId,
                WorkId::fromString($event->workId),
                $event->correctable,
                $event->affordableCorrections,
                $event->occurredAt(),
            );
        } else {
            $known->record($event->correctable, $event->affordableCorrections, $event->occurredAt());
        }

        $this->session->execute(function () use ($known): void {
            $this->signals->saveCorrectability($known);
        });
    }
}
