<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\Handler;

use LectoresBeta\Reading\AccessInvitation\Application\DTO\InvitationPage;
use LectoresBeta\Reading\AccessInvitation\Application\Query\ListWorkInvitations;
use LectoresBeta\Reading\AccessInvitation\Application\Service\PageOfInvitations;
use LectoresBeta\Reading\AccessInvitation\Domain\Exception\InvitationNotFound;
use LectoresBeta\Reading\AccessInvitation\Domain\Repository\AccessInvitationRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;
use LectoresBeta\Work\Manuscript\Application\Contract\WorkAccessBriefs;

/**
 * A quién le he ofrecido mi obra (`FEAT-RDG-004`).
 *
 * La autoría se comprueba contra `Work`, que es de quien es el dato, y no
 * contra la propia invitación: así una obra que cambiase de manos no dejaría
 * bandejas abiertas a quien ya no es su autor.
 */
final readonly class ListWorkInvitationsHandler
{
    public function __construct(
        private AccessInvitationRepository $invitations,
        private WorkAccessBriefs $works,
        private PageOfInvitations $page,
    ) {
    }

    public function __invoke(ListWorkInvitations $query): InvitationPage
    {
        $work = $this->works->ofWork($query->workId);

        if (null === $work || $work->authorId !== $query->authorId) {
            throw InvitationNotFound::work();
        }

        $limit = $this->page->size($query->limit);

        return $this->page->of($this->invitations->onWork(
            WorkId::fromString($query->workId),
            $this->page->status($query->status),
            $this->page->after($query->cursor),
            $limit + 1,
        ), $limit);
    }
}
