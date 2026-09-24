<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Relationship\Application\Handler;

use LectoresBeta\Community\Post\Domain\ValueObject\MemberId;
use LectoresBeta\Community\Relationship\Application\Command\BlockUser;
use LectoresBeta\Community\Relationship\Domain\Entity\UserBlock;
use LectoresBeta\Community\Relationship\Domain\Event\UserBlocked;
use LectoresBeta\Community\Relationship\Domain\Exception\BlockRefused;
use LectoresBeta\Community\Relationship\Domain\Repository\UserBlockRepository;
use LectoresBeta\Community\Relationship\Domain\ValueObject\UserBlockId;
use LectoresBeta\Community\Subscription\Application\Service\UndoSubscriptions;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\RegisteredUsers;

/**
 * Bloquear a alguien (`FEAT-COM-034`).
 *
 * Hace **dos cosas y solo dos**: guarda el bloqueo y deshace los dos
 * seguimientos (`RN-3`). Todo lo demás —el acceso de lector beta, el
 * feedback, los créditos, los avisos— lo deciden otros contextos a partir del
 * hecho que se publica aquí (`RN-10`), y eso es lo que mantiene la
 * arquitectura en pie: `Community` conoce relaciones sociales, no obras ni
 * saldos.
 *
 * **No se avisa al bloqueado** (`RN-2`). Descubrirlo por el comportamiento es
 * lo habitual; avisarle convierte el bloqueo en una confrontación.
 *
 * Es idempotente: bloquear a quien ya está bloqueado no crea otra fila ni
 * publica un segundo hecho.
 */
final readonly class BlockUserHandler
{
    public function __construct(
        private UserBlockRepository $blocks,
        private UndoSubscriptions $subscriptions,
        private RegisteredUsers $users,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(BlockUser $command): void
    {
        try {
            $blockerId = MemberId::fromString($command->blockerId);
            $blockedId = MemberId::fromString($command->blockedId);
        } catch (InvalidValue) {
            throw BlockRefused::userNotFound();
        }

        if ($blockerId->value() === $blockedId->value()) {
            throw BlockRefused::yourself();
        }

        if (!$this->users->exists($blockedId->value())) {
            throw BlockRefused::userNotFound();
        }

        if (null !== $this->blocks->between($blockerId, $blockedId)) {
            return;
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($blockerId, $blockedId, $now): void {
            $this->blocks->save(new UserBlock(UserBlockId::generate(), $blockerId, $blockedId, $now));
        });

        // Los dos sentidos (`RN-3`). Publica su propio hecho por cada
        // seguimiento deshecho: quien proyecta el grafo no tiene por qué
        // saber que detrás había un bloqueo.
        $this->subscriptions->betweenThem($blockerId, $blockedId);

        $this->events->publish(new UserBlocked(
            EventId::generate(),
            $blockerId,
            $blockedId,
            $now,
        ));
    }
}
