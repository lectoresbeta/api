<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Application\Handler;

use LectoresBeta\Shared\Domain\Pagination\PageSize;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Invitation\Application\DTO\SentInvitation;
use LectoresBeta\User\Invitation\Application\Query\ListMyInvitations;
use LectoresBeta\User\Invitation\Domain\Entity\PlatformInvitation;
use LectoresBeta\User\Invitation\Domain\Repository\PlatformInvitationRepository;

/**
 * «Mis invitaciones» (`FEAT-USR-018`).
 *
 * De la más reciente a la más antigua, que es el orden en que alguien busca
 * la que acaba de mandar.
 */
final readonly class ListMyInvitationsHandler
{
    public function __construct(private PlatformInvitationRepository $invitations)
    {
    }

    /**
     * @return list<SentInvitation>
     */
    public function __invoke(ListMyInvitations $query): array
    {
        $sent = $this->invitations->sentBy(
            UserId::fromString($query->inviterId),
            PageSize::of($query->limit),
            max(0, $query->offset),
        );

        return array_map(
            static fn (PlatformInvitation $one): SentInvitation => new SentInvitation(
                $one->id()->value(),
                $one->email(),
                !$one->isAvailable(),
                $one->createdAt()->format(\DATE_ATOM),
                $one->consumedAt()?->format(\DATE_ATOM),
            ),
            $sent,
        );
    }
}
