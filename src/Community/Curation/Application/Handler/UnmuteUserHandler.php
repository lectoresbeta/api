<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Application\Handler;

use LectoresBeta\Community\Curation\Application\Command\UnmuteUser;
use LectoresBeta\Community\Curation\Domain\Repository\MutedMemberRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Dejar de silenciar (`FEAT-COM-033` `RN-4`).
 *
 * No comprueba que esa persona siga existiendo: quien se dio de baja sigue
 * mereciendo salir de la lista, o la fila se quedaría ahí sin forma de
 * quitarla.
 */
final readonly class UnmuteUserHandler
{
    public function __construct(
        private MutedMemberRepository $muted,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(UnmuteUser $command): void
    {
        try {
            $member = MemberId::fromString($command->memberId);
            $mutedId = MemberId::fromString($command->mutedId);
        } catch (InvalidValue) {
            return;
        }

        $this->session->execute(function () use ($member, $mutedId): void {
            $this->muted->remove($member, $mutedId);
        });
    }
}
