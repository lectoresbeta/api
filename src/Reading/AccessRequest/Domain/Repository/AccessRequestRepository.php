<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Domain\Repository;

use LectoresBeta\Reading\AccessRequest\Domain\Entity\AccessRequest;
use LectoresBeta\Reading\AccessRequest\Domain\Enum\AccessRequestStatus;
use LectoresBeta\Reading\AccessRequest\Domain\ValueObject\AccessRequestId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\AuthorId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;

interface AccessRequestRepository
{
    public function save(AccessRequest $request): void;

    public function ofId(AccessRequestId $id): ?AccessRequest;

    /**
     * The open request of this reader on this work, if there is one.
     *
     * It answers the `409`; what makes the rule hold under a double click is
     * the partial unique index, not this read.
     */
    public function openOf(ReaderId $readerId, WorkId $workId): ?AccessRequest;

    /**
     * The reader's own requests, newest first.
     *
     * `limit` is asked for one more row than the page needs, so the caller
     * can tell whether there is a next page without counting anything.
     *
     * @return list<AccessRequest>
     */
    public function ofRequester(ReaderId $readerId, ?AccessRequestStatus $status, ?Cursor $after, int $limit): array;

    /**
     * The author's queue for one work, newest first.
     *
     * @return list<AccessRequest>
     */
    public function onWork(WorkId $workId, AuthorId $authorId, ?AccessRequestStatus $status, ?Cursor $after, int $limit): array;
}
