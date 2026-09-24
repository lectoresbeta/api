<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Application\Handler;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Relationship\Application\Command\UnblockUser;
use LectoresBeta\Community\Relationship\Domain\Event\UserUnblocked;
use LectoresBeta\Community\Relationship\Domain\Repository\UserBlockRepository;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Levantar un bloqueo (`FEAT-COM-034` `RN-7`).
 *
 * **No restaura los seguimientos** que el bloqueo deshizo: hay que volver a
 * seguir. Reconstruirlos sería devolver una relación que ninguna de las dos
 * personas ha pedido tener otra vez.
 *
 * No falla nunca: desbloquear a quien no estaba bloqueado deja el mundo como
 * estaba, que es lo que se pedía.
 */
final readonly class UnblockUserHandler
{
    public function __construct(
        private UserBlockRepository $blocks,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(UnblockUser $command): void
    {
        try {
            $blockerId = MemberId::fromString($command->blockerId);
            $blockedId = MemberId::fromString($command->blockedId);
        } catch (InvalidValue) {
            return;
        }

        $block = $this->blocks->between($blockerId, $blockedId);

        if (null === $block) {
            return;
        }

        $this->session->execute(function () use ($block): void {
            $this->blocks->remove($block);
        });

        $this->events->publish(new UserUnblocked(
            EventId::generate(),
            $blockerId,
            $blockedId,
            $this->clock->now(),
        ));
    }
}
