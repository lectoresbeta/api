<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Service;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBrief;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;
use LectoresBeta\Work\Manuscript\Domain\Entity\Work;
use LectoresBeta\Work\Manuscript\Domain\Repository\WorkRepository;
use LectoresBeta\Work\Manuscript\Domain\ValueObject\WorkId;

/**
 * `Work`'s side of the access brief (`FEAT-RDG-002`,
 * [`decision:0015`](../../../../../docs/decisions/0015-work-and-reading-ask-each-other.md)).
 *
 * It reads the aggregate and reports. **It calls no other context's
 * contract**, which is the rule that keeps `Work` and `Reading` pointing at
 * each other without that becoming a chain of calls — in particular it does
 * not ask `Reading` whether anybody has access, although `WorkReadPolicy`
 * does for its own question.
 *
 * A malformed identifier answers the same as a work that is not there. It
 * arrives from a URL, and distinguishing «badly written» from «not yours»
 * would be telling a stranger which of the two it was.
 */
final readonly class ResolveWorkAccessBriefs implements WorkAccessBriefs
{
    public function __construct(private WorkRepository $works)
    {
    }

    public function ofWork(string $workId): ?WorkAccessBrief
    {
        try {
            $work = $this->works->ofId(WorkId::fromString($workId));
        } catch (InvalidValue) {
            return null;
        }

        return null === $work ? null : self::briefOf($work);
    }

    public function ofWorks(array $workIds): array
    {
        $ids = [];

        foreach (array_unique($workIds) as $workId) {
            try {
                $ids[] = WorkId::fromString($workId);
            } catch (InvalidValue) {
                // Igual que arriba: lo que no es un identificador no está.
                continue;
            }
        }

        return array_map(self::briefOf(...), $this->works->ofIds($ids));
    }

    private static function briefOf(Work $work): WorkAccessBrief
    {
        return new WorkAccessBrief(
            $work->id()->value(),
            $work->authorId()->value(),
            $work->title()->value(),
            $work->accessMode()->value,
            $work->isAdultsOnly(),
            $work->status()->isReadableByOthers() && !$work->isBlocked(),
        );
    }
}
