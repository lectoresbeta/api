<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Domain\Repository;

use LectoresBeta\Reading\BetaReaderAccess\Domain\Entity\BetaReaderAccess;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\BetaReaderAccessId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;

/**
 * Who may read what.
 *
 * `liveFor` is the authorisation question, and it is asked on every read of a
 * chapter. It must never be answered from the client.
 */
interface BetaReaderAccessRepository
{
    public function save(BetaReaderAccess $access): void;

    public function ofId(BetaReaderAccessId $id): ?BetaReaderAccess;

    public function liveFor(ReaderId $readerId, WorkId $workId): ?BetaReaderAccess;

    /**
     * @return list<BetaReaderAccess>
     */
    public function liveOnWork(WorkId $workId): array;

    /**
     * @return list<BetaReaderAccess>
     */
    public function liveOfReader(ReaderId $readerId): array;

    /**
     * Quién puede leer esta obra ahora mismo, de lo más reciente a lo más
     * antiguo, con una fila de más para saber si hay página siguiente
     * (`FEAT-RDG-010`).
     *
     * @return list<BetaReaderAccess>
     */
    public function livePageOnWork(WorkId $workId, ?Cursor $after, int $limit): array;
}
