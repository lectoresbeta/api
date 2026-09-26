<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Domain\Repository;

use LectoresBeta\Reading\AccessInvitation\Domain\Entity\AccessInvitation;
use LectoresBeta\Reading\AccessInvitation\Domain\Enum\AccessInvitationStatus;
use LectoresBeta\Reading\AccessInvitation\Domain\ValueObject\AccessInvitationId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Shared\Domain\Pagination\Cursor;

interface AccessInvitationRepository
{
    public function save(AccessInvitation $invitation): void;

    public function ofId(AccessInvitationId $id): ?AccessInvitation;

    public function openFor(ReaderId $inviteeId, WorkId $workId): ?AccessInvitation;

    /**
     * What this person has been offered, newest first.
     *
     * @return list<AccessInvitation>
     */
    public function ofInvitee(ReaderId $inviteeId, ?AccessInvitationStatus $status, ?Cursor $after, int $limit): array;

    /**
     * What the author has offered on one work, newest first.
     *
     * @return list<AccessInvitation>
     */
    public function onWork(WorkId $workId, ?AccessInvitationStatus $status, ?Cursor $after, int $limit): array;
}
