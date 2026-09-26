<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\WritingBuddy\Application\Command\ProposeWritingBuddy;
use LectoresBeta\Reading\WritingBuddy\Domain\Entity\WritingBuddyLink;
use LectoresBeta\Reading\WritingBuddy\Domain\Event\WritingBuddyProposed;
use LectoresBeta\Reading\WritingBuddy\Domain\Exception\WritingBuddyLinkNotFound;
use LectoresBeta\Reading\WritingBuddy\Domain\Exception\WritingBuddyRefused;
use LectoresBeta\Reading\WritingBuddy\Domain\Repository\WritingBuddyLinkRepository;
use LectoresBeta\Reading\WritingBuddy\Domain\ValueObject\WritingBuddyLinkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\RegisteredUsers;
use LectoresBeta\User\Preferences\Application\Contract\ProposalRecipients;

/**
 * Proponerle a alguien ser writing buddy (`FEAT-RDG-008`).
 *
 * **El vínculo no habilita nada** (`R-3`, resuelta). No concede acceso a las
 * obras del otro, no salta la modalidad que cada autor eligió y no salta la
 * clasificación por edad. Es un vínculo declarado: se ve, se anuncia y ahí
 * acaba. Quien quiera leer al otro lo invita (`FEAT-RDG-004`), que cuesta un
 * clic y pasa por donde tiene que pasar.
 *
 * La propuesta **no concede ni siquiera el vínculo**: es una oferta, y quien
 * decide es quien la recibe.
 *
 * **Uno vivo por par.** Lo garantiza el índice único parcial sobre los
 * estados vivos; la comprobación previa solo ahorra el error feo.
 */
final readonly class ProposeWritingBuddyHandler
{
    public function __construct(
        private RegisteredUsers $users,
        private ProposalRecipients $recipients,
        private WritingBuddyLinkRepository $links,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ProposeWritingBuddy $command): string
    {
        try {
            $proposer = ReaderId::fromString($command->proposerId);
            $partner = ReaderId::fromString($command->partnerId);
        } catch (InvalidValue) {
            throw WritingBuddyLinkNotFound::create();
        }

        if ($proposer->value() === $partner->value()) {
            throw WritingBuddyRefused::toYourself();
        }

        if (!$this->users->isActivated($command->partnerId)) {
            // Ni existe, ni está activada, ni se distingue cuál de las dos:
            // distinguirlo confirmaría quién está en la plataforma.
            throw WritingBuddyLinkNotFound::create();
        }

        // El ajuste y el bloqueo, en una pregunta y sin decir cuál de los dos
        // (`FEAT-USR-011`): distinguirlos permitiría averiguar los ajustes de
        // otro probando a proponerle cosas.
        if (!$this->recipients->acceptsWritingBuddyProposals($command->partnerId, $command->proposerId)) {
            throw WritingBuddyRefused::notAccepted();
        }

        if (null !== $this->links->liveBetween($proposer, $partner)) {
            throw WritingBuddyRefused::alreadyLinked();
        }

        $now = $this->clock->now();
        $link = new WritingBuddyLink(WritingBuddyLinkId::generate(), $proposer, $partner, $now);

        $this->session->execute(function () use ($link): void {
            $this->links->save($link);
        });

        $this->events->publish(new WritingBuddyProposed(
            EventId::generate(),
            $link->id(),
            $proposer,
            $partner,
            $now,
        ));

        return $link->id()->value();
    }
}
