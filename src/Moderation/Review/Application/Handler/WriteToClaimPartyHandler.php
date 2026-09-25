<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Handler;

use LectoresBeta\Moderation\AuditLog\Application\Service\RecordAuditEntry;
use LectoresBeta\Moderation\Claim\Domain\Repository\ClaimRepository;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\ClaimId;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Review\Application\Command\WriteToClaimParty;
use LectoresBeta\Moderation\Review\Domain\Entity\ClaimMessage;
use LectoresBeta\Moderation\Review\Domain\Enum\MessageAuthorType;
use LectoresBeta\Moderation\Review\Domain\Enum\ThreadParty;
use LectoresBeta\Moderation\Review\Domain\Exception\ClaimMessageRefused;
use LectoresBeta\Moderation\Review\Domain\Repository\ClaimMessageRepository;
use LectoresBeta\Moderation\Review\Domain\ValueObject\ClaimMessageId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * El moderador abre o continúa el hilo con una de las partes
 * (`FEAT-MOD-009`).
 *
 * **Solo el moderador abre conversación.** Si una parte pudiera, la cola se
 * llenaría de alegatos no solicitados y el expediente dejaría de ser un
 * procedimiento para convertirse en una bandeja de entrada.
 *
 * El mensaje es **inmutable**: no se edita ni se borra. Importa más de lo que
 * parece — si alguien puede reescribir lo que dijo, el expediente deja de ser
 * prueba de nada.
 */
final readonly class WriteToClaimPartyHandler
{
    public function __construct(
        private ClaimRepository $claims,
        private ClaimMessageRepository $messages,
        private RecordAuditEntry $audit,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(WriteToClaimParty $command): string
    {
        try {
            $claim = $this->claims->ofId(ClaimId::fromString($command->claimId));
        } catch (InvalidValue) {
            throw ClaimMessageRefused::claimNotFound();
        }

        if (null === $claim) {
            throw ClaimMessageRefused::claimNotFound();
        }

        if (!$claim->status()->isOpen()) {
            throw ClaimMessageRefused::claimResolved();
        }

        $party = ThreadParty::tryFrom(strtoupper($command->party ?? ''))
            ?? throw ClaimMessageRefused::unknownParty();

        // Una reclamación sobre una obra no señala a nadie, así que no hay
        // con quién abrir el segundo hilo.
        if (ThreadParty::SUBJECT === $party && null === $claim->subjectId()) {
            throw ClaimMessageRefused::noSubject();
        }

        $body = trim($command->body ?? '');

        if ('' === $body) {
            throw ClaimMessageRefused::emptyMessage();
        }

        $message = new ClaimMessage(
            ClaimMessageId::generate(),
            $claim->id(),
            $party,
            MessageAuthorType::MODERATOR,
            PartyId::fromString($command->moderatorId),
            $body,
            $this->clock->now(),
        );

        $this->session->execute(function () use ($message, $claim, $command, $party): void {
            $this->messages->add($message);

            // `RN-8`: todo mensaje queda en el registro. **Sin el cuerpo**:
            // lo que hay que poder revisar después es que el moderador habló
            // con una parte, no reproducir el expediente en un segundo sitio.
            $this->audit->of(
                PartyId::fromString($command->moderatorId),
                'CLAIM_MESSAGE_SENT',
                'CLAIM',
                $claim->id()->value(),
                null,
                ['thread' => $party->value],
            );
        });

        return $message->id()->value();
    }
}
