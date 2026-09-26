<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\Handler;

use LectoresBeta\Reading\BetaReaderGroup\Application\Command\DeleteBetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Application\Service\OwnGroup;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Repository\BetaReaderGroupMemberRepository;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Repository\BetaReaderGroupRepository;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Tirar la lista (`FEAT-RDG-007` `RN-10`).
 *
 * Se lleva a sus miembros y **no toca ninguna invitación ya cursada**: lo que
 * se invitó, invitado está. El grupo era el atajo, no la puerta.
 */
final readonly class DeleteBetaReaderGroupHandler
{
    public function __construct(
        private OwnGroup $ownGroup,
        private BetaReaderGroupRepository $groups,
        private BetaReaderGroupMemberRepository $members,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(DeleteBetaReaderGroup $command): void
    {
        $group = $this->ownGroup->of($command->groupId, $command->authorId);

        $this->session->execute(function () use ($group): void {
            $this->members->removeAllOf($group->id());
            $this->groups->delete($group);
        });
    }
}
