<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Application\DTO\BetaReaderPage;
use LectoresBeta\Reading\BetaReaderAccess\Application\Query\ListWorkBetaReaders;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Entity\BetaReaderAccess;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Exception\BetaReaderAccessNotFound;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Repository\BetaReaderAccessRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Exception\InvalidCursor;
use LectoresBeta\Shared\Domain\Pagination\Cursor;
use LectoresBeta\Shared\Domain\Pagination\PageSize;
use LectoresBeta\User\Account\Application\Contract\ProfileCards;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * Quién puede leer mi obra (`FEAT-RDG-010`).
 *
 * Usa `ProfileCards` y **no** `VisibleProfiles`, por la misma razón que la
 * lista de bloqueados: quien tiene acceso a una obra inédita tiene que
 * aparecer aquí **aunque haya cerrado su perfil después**. Si se filtrara,
 * cerrar el perfil sería una forma de volverse invisible para el autor cuya
 * obra se está leyendo, y no habría manera de retirarle el acceso.
 *
 * Es el caso que mejor justifica que ese contrato exista: aquí quien pregunta
 * no está descubriendo a nadie — esas personas están dentro de su obra.
 */
final readonly class ListWorkBetaReadersHandler
{
    public function __construct(
        private BetaReaderAccessRepository $accesses,
        private WorkAccessBriefs $works,
        private ProfileCards $profiles,
    ) {
    }

    public function __invoke(ListWorkBetaReaders $query): BetaReaderPage
    {
        $work = $this->works->ofWork($query->workId);

        if (null === $work || $work->authorId !== $query->authorId) {
            throw BetaReaderAccessNotFound::work();
        }

        $limit = PageSize::of($query->limit);
        $after = null === $query->cursor || '' === $query->cursor
            ? null
            : Cursor::decode($query->cursor) ?? throw InvalidCursor::create();

        $found = $this->accesses->livePageOnWork(WorkId::fromString($query->workId), $after, $limit);
        $rows = \array_slice($found, 0, $limit);
        $last = end($rows);

        $cards = $this->profiles->of(array_values(array_unique(array_map(
            static fn (BetaReaderAccess $access): string => $access->readerId()->value(),
            $rows,
        ))));

        $readers = [];

        foreach ($rows as $access) {
            $card = $cards[$access->readerId()->value()] ?? null;

            // Una cuenta eliminada no tiene tarjeta. Su acceso sigue ahí, y
            // es coherente: lo que se enseña es gente, y ya no hay.
            if (null !== $card) {
                $readers[] = [
                    'profile' => $card,
                    'source' => $access->source()->value,
                    'earned' => $access->isEarned(),
                    'grantedAt' => $access->grantedAt(),
                ];
            }
        }

        return new BetaReaderPage(
            $readers,
            \count($found) > $limit && false !== $last
                ? Cursor::of($last->grantedAt(), $last->id()->value())->encode()
                : null,
        );
    }
}
