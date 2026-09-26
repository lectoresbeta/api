<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\Handler;

use LectoresBeta\Reading\AccessInvitation\Application\DTO\InvitationPage;
use LectoresBeta\Reading\AccessInvitation\Application\Query\ListMyInvitations;
use LectoresBeta\Reading\AccessInvitation\Application\Service\PageOfInvitations;
use LectoresBeta\Reading\AccessInvitation\Domain\Repository\AccessInvitationRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;

/**
 * Lo que me han ofrecido (`FEAT-RDG-005`).
 */
final readonly class ListMyInvitationsHandler
{
    public function __construct(
        private AccessInvitationRepository $invitations,
        private PageOfInvitations $page,
    ) {
    }

    public function __invoke(ListMyInvitations $query): InvitationPage
    {
        $limit = $this->page->size($query->limit);

        return $this->page->of($this->invitations->ofInvitee(
            ReaderId::fromString($query->inviteeId),
            $this->page->status($query->status),
            $this->page->after($query->cursor),
            $limit + 1,
        ), $limit);
    }
}
