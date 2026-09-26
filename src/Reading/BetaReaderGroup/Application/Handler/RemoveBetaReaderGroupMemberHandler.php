<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderGroup\Application\Command\RemoveBetaReaderGroupMember;
use LectoresBeta\Reading\BetaReaderGroup\Application\Service\OwnGroup;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Repository\BetaReaderGroupMemberRepository;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Sacar a alguien de la agenda (`FEAT-RDG-007` `RN-9`).
 *
 * **No le quita ningún acceso.** Si esa persona ya es lectora beta de una
 * obra, lo sigue siendo: quitarle el acceso es otra operación, con su propia
 * ficha (`FEAT-RDG-010`). Confundirlas convertiría editar una lista privada en
 * echar a alguien de una obra sin decírselo.
 *
 * Quitar a quien no está también responde `204`: el desenlace es el mismo.
 */
final readonly class RemoveBetaReaderGroupMemberHandler
{
    public function __construct(
        private OwnGroup $ownGroup,
        private BetaReaderGroupMemberRepository $members,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(RemoveBetaReaderGroupMember $command): void
    {
        $group = $this->ownGroup->of($command->groupId, $command->authorId);

        try {
            $readerId = ReaderId::fromString($command->readerId);
        } catch (InvalidValue) {
            return;
        }

        $this->session->execute(function () use ($group, $readerId): void {
            $this->members->remove($group->id(), $readerId);
        });
    }
}
