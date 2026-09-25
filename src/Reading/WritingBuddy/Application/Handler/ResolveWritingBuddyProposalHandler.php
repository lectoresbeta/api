<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Application\Handler;

use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\WritingBuddy\Application\Command\ResolveWritingBuddyProposal;
use LectoresBeta\Reading\WritingBuddy\Domain\Enum\WritingBuddyStatus;
use LectoresBeta\Reading\WritingBuddy\Domain\Event\WritingBuddyLinked;
use LectoresBeta\Reading\WritingBuddy\Domain\Exception\WritingBuddyLinkNotFound;
use LectoresBeta\Reading\WritingBuddy\Domain\Exception\WritingBuddyRefused;
use LectoresBeta\Reading\WritingBuddy\Domain\Repository\WritingBuddyLinkRepository;
use LectoresBeta\Reading\WritingBuddy\Domain\ValueObject\WritingBuddyLinkId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Aceptar o rechazar una propuesta de writing buddy (`FEAT-RDG-009`).
 *
 * **Solo la resuelve quien la recibió.** Quien la propuso no puede aceptarse
 * a sí mismo, y la única salida que tiene es esperar: el par se guarda
 * ordenado y por eso `proposedBy` vive en su propia columna — es lo único que
 * el orden tira y lo único que distingue a las dos partes.
 *
 * **Aceptar anuncia, rechazar no.** Es la misma decisión que con
 * `AuthorUnsubscribed`: avisar a alguien de que le han dicho que no
 * convertiría una respuesta discreta en un desaire con acuse de recibo. Quien
 * propuso lo ve en su lista, que es donde fue a mirar.
 *
 * Y aceptar **no abre ninguna puerta** (`R-3`): el vínculo queda declarado y
 * nada más.
 */
final readonly class ResolveWritingBuddyProposalHandler
{
    private const ACCEPT = 'ACCEPT';

    private const DECLINE = 'DECLINE';

    public function __construct(
        private WritingBuddyLinkRepository $links,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ResolveWritingBuddyProposal $command): string
    {
        $decision = strtoupper($command->decision ?? '');

        if (self::ACCEPT !== $decision && self::DECLINE !== $decision) {
            throw WritingBuddyRefused::unknownDecision($command->decision ?? '', [self::ACCEPT, self::DECLINE]);
        }

        try {
            $link = $this->links->ofId(WritingBuddyLinkId::fromString($command->linkId));
            $reader = ReaderId::fromString($command->readerId);
        } catch (InvalidValue) {
            throw WritingBuddyLinkNotFound::create();
        }

        // No existir, no ser tuya y estar ya resuelta responden igual: quién
        // le ha propuesto qué a quién no se le debe a nadie que no sea parte.
        if (
            null === $link
            || WritingBuddyStatus::PROPOSED !== $link->status()
            || $link->proposedTo()->value() !== $reader->value()
        ) {
            throw WritingBuddyLinkNotFound::create();
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($link, $decision, $now): void {
            if (self::ACCEPT === $decision) {
                $link->accept($now);
            } else {
                $link->decline($now);
            }

            $this->links->save($link);
        });

        if (self::ACCEPT === $decision) {
            $this->events->publish(new WritingBuddyLinked(
                EventId::generate(),
                $link->id(),
                $link->proposedBy(),
                $reader,
                $now,
            ));
        }

        return $link->status()->value;
    }
}
