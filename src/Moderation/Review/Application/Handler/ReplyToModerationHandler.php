<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Application\Handler;

use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Moderation\Review\Application\Command\ReplyToModeration;
use LectoresBeta\Moderation\Review\Application\Service\ClaimThread;
use LectoresBeta\Moderation\Review\Domain\Entity\ClaimMessage;
use LectoresBeta\Moderation\Review\Domain\Enum\MessageAuthorType;
use LectoresBeta\Moderation\Review\Domain\Exception\ClaimMessageRefused;
use LectoresBeta\Moderation\Review\Domain\Repository\ClaimMessageRepository;
use LectoresBeta\Moderation\Review\Domain\ValueObject\ClaimMessageId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Una parte responde **en su hilo** (`FEAT-MOD-009` `RN-2`).
 *
 * No hay forma de elegir hilo: el suyo se deduce de quién es. Con un
 * identificador de hilo en la petición, leer o escribir en el de la otra
 * parte sería cambiar una palabra.
 *
 * **Solo después de que el moderador lo abra.** Una parte no inicia
 * conversación por su cuenta.
 *
 * Y el hilo se **cierra al resolverse** la reclamación (`RN-6`): se puede
 * leer, no continuar. Seguir escribiendo en un expediente cerrado sería
 * alegar ante quien ya decidió.
 */
final readonly class ReplyToModerationHandler
{
    public function __construct(
        private ClaimThread $threads,
        private ClaimMessageRepository $messages,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(ReplyToModeration $command): string
    {
        ['claim' => $claim, 'party' => $party] = $this->threads->of($command->claimId, $command->authorId);

        if (!$claim->status()->isOpen()) {
            throw ClaimMessageRefused::claimResolved();
        }

        if (!$this->messages->isOpen($claim->id(), $party)) {
            throw ClaimMessageRefused::threadNotOpen();
        }

        $body = trim($command->body ?? '');

        if ('' === $body) {
            throw ClaimMessageRefused::emptyMessage();
        }

        $message = new ClaimMessage(
            ClaimMessageId::generate(),
            $claim->id(),
            $party,
            MessageAuthorType::PARTY,
            PartyId::fromString($command->authorId),
            $body,
            $this->clock->now(),
        );

        $this->session->execute(function () use ($message): void {
            $this->messages->add($message);
        });

        return $message->id()->value();
    }
}
