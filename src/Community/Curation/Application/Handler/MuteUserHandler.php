<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Application\Handler;

use LectoresBeta\Community\Curation\Application\Command\MuteUser;
use LectoresBeta\Community\Curation\Domain\Entity\MutedMember;
use LectoresBeta\Community\Curation\Domain\Exception\MuteRefused;
use LectoresBeta\Community\Curation\Domain\Repository\MutedMemberRepository;
use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\RegisteredUsers;

/**
 * Silenciar a alguien (`FEAT-COM-033`).
 *
 * Hace **una sola cosa**: guardar la fila. No deshace el seguimiento, no
 * corta los mensajes, no toca el acceso de lector beta y no publica ningún
 * hecho — todo eso es bloquear (`FEAT-COM-034`), que es otra cosa y atraviesa
 * cuatro contextos.
 *
 * Que no publique evento es la decisión, no un olvido: un consumidor que
 * reaccionara a un silencio estaría convirtiendo una preferencia de pantalla
 * en una regla del sistema.
 *
 * **No se avisa al silenciado**, igual que con el bloqueo. Y es idempotente.
 */
final readonly class MuteUserHandler
{
    public function __construct(
        private MutedMemberRepository $muted,
        private RegisteredUsers $users,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(MuteUser $command): void
    {
        try {
            $member = MemberId::fromString($command->memberId);
            $mutedId = MemberId::fromString($command->mutedId);
        } catch (InvalidValue) {
            throw MuteRefused::userNotFound();
        }

        if ($member->value() === $mutedId->value()) {
            throw MuteRefused::yourself();
        }

        if (!$this->users->exists($mutedId->value())) {
            throw MuteRefused::userNotFound();
        }

        if ($this->muted->has($member, $mutedId)) {
            return;
        }

        $entry = new MutedMember($member, $mutedId, $this->clock->now());

        $this->session->execute(function () use ($entry): void {
            $this->muted->save($entry);
        });
    }
}
