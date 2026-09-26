<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderGroup\Application\Command\AddBetaReaderGroupMember;
use LectoresBeta\Reading\BetaReaderGroup\Application\Service\OwnGroup;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Entity\BetaReaderGroupMember;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Exception\GroupRefused;
use LectoresBeta\Reading\BetaReaderGroup\Domain\Repository\BetaReaderGroupMemberRepository;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\RegisteredUsers;

/**
 * Apuntar a alguien en la agenda (`FEAT-RDG-007`).
 *
 * **No le concede nada** (`RN-11`): no le abre ninguna obra, no le avisa y no
 * se entera. Lo único que cambia es que el autor no tendrá que volver a
 * buscarlo.
 *
 * Es idempotente (`RN-8`) y por eso la ruta es `PUT`: lo que el autor quiere
 * decir es «esta persona está en el grupo», no «añade una fila».
 */
final readonly class AddBetaReaderGroupMemberHandler
{
    /** `RN-5`. Es una agenda, no una lista de difusión. */
    public const MAX_MEMBERS = 200;

    public function __construct(
        private OwnGroup $ownGroup,
        private RegisteredUsers $users,
        private BetaReaderGroupMemberRepository $members,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(AddBetaReaderGroupMember $command): void
    {
        $group = $this->ownGroup->of($command->groupId, $command->authorId);

        if ($command->readerId === $command->authorId) {
            throw GroupRefused::authorCannotBeAMember();
        }

        // Se comprueba que existe por el mismo motivo que al invitar
        // (`FEAT-RDG-004`): una agenda llena de identificadores inventados es
        // una agenda que falla el día que se usa, en bloque y sin explicación.
        if (!$this->users->exists($command->readerId)) {
            throw GroupRefused::userNotFound();
        }

        try {
            $readerId = ReaderId::fromString($command->readerId);
        } catch (InvalidValue) {
            throw GroupRefused::userNotFound();
        }

        if ($this->members->has($group->id(), $readerId)) {
            return;
        }

        if ($this->members->countIn($group->id()) >= self::MAX_MEMBERS) {
            throw GroupRefused::tooManyMembers();
        }

        $member = new BetaReaderGroupMember($group->id(), $readerId, $this->clock->now());

        $this->session->execute(function () use ($member): void {
            $this->members->save($member);
        });
    }
}
