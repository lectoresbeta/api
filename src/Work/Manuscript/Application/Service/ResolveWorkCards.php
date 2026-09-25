<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Chapter\Domain\Service\ReadingTime;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkCard;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkCards;
use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Enum\ContentWarning;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkContentWarningRepository;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkGenreRepository;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * El lado de `Work` en la tarjeta de obra (`FEAT-COM-028`).
 *
 * Lee el agregado y responde. **No llama al contrato de ningún otro
 * contexto**, que es la regla que mantiene las referencias cruzadas entre
 * contextos como referencias y no como una cadena de llamadas
 * ([`decision:0015`](../../../../../docs/decisions/0015-work-and-reading-ask-each-other.md)).
 *
 * **Lo invisible se cae aquí.** Un borrador, una obra archivada y una
 * bloqueada por moderación no salen en la respuesta, y es la misma condición
 * que hace que `GET /works/{id}` responda `404` a un extraño. Devolverlas y
 * dejar que quien pregunta decida sería reescribir esa regla fuera de este
 * contexto.
 *
 * Tres consultas para toda la página —obras, temáticas y advertencias— y no
 * tres por tarjeta. Un muro que consulta por fila se degrada justo el día que
 * la plataforma empieza a usarse.
 */
final readonly class ResolveWorkCards implements WorkCards
{
    public function __construct(
        private WorkRepository $works,
        private WorkGenreRepository $genres,
        private WorkContentWarningRepository $warnings,
    ) {
    }

    public function ofWorks(array $workIds): array
    {
        $ids = [];

        foreach (array_unique($workIds) as $workId) {
            try {
                $ids[] = WorkId::fromString($workId);
            } catch (InvalidValue) {
                // Lo que no es un identificador no está, igual que lo que no
                // existe: llega de un cuerpo de petición guardado hace meses.
                continue;
            }
        }

        if ([] === $ids) {
            return [];
        }

        $visible = array_filter($this->works->ofIds($ids), self::isVisibleToEveryone(...));

        if ([] === $visible) {
            return [];
        }

        $found = array_map(static fn (Work $work): WorkId => $work->id(), array_values($visible));
        $genres = $this->genres->codesOfWorks($found);
        $warnings = $this->warnings->ofWorks($found);

        $cards = [];

        foreach ($visible as $work) {
            $id = $work->id()->value();

            $cards[$id] = new WorkCard(
                $id,
                $work->authorId()->value(),
                $work->title()->value(),
                $work->synopsis(),
                $work->status()->value,
                $work->chapterCount(),
                ReadingTime::minutesFor($work->wordCount()),
                $work->isAdultsOnly(),
                array_map(
                    static fn (ContentWarning $warning): string => $warning->value,
                    $warnings[$id] ?? [],
                ),
                $genres[$id] ?? [],
            );
        }

        return $cards;
    }

    private static function isVisibleToEveryone(Work $work): bool
    {
        return $work->status()->isReadableByOthers() && !$work->isBlocked() && !$work->isArchived();
    }
}
