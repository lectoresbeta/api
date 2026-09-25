<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Overdraft\Application\Handler;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\Overdraft\Application\Event\ReactivationOfferChoiceChanged;
use LectoresBeta\Credits\Overdraft\Domain\Entity\OverdraftOptOut;
use LectoresBeta\Credits\Overdraft\Domain\Repository\OverdraftOptOutRepository;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Quién ha dicho que no quiere el gancho (`FEAT-CRD-019` `RN-2d`, `RN-8`).
 *
 * **Es un estado que afirmar y no un efecto que acumular**, así que no
 * necesita deduplicación: recibir dos veces «ha renunciado» deja exactamente
 * lo mismo que recibirlo una. Es la distinción que separa esto de un
 * contador, donde una reentrega sí haría daño.
 */
final readonly class TrackReactivationChoice
{
    public function __construct(
        private OverdraftOptOutRepository $optOuts,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(ReactivationOfferChoiceChanged $event): void
    {
        try {
            $userId = UserId::fromString($event->userId);
        } catch (InvalidValue) {
            return;
        }

        $existing = $this->optOuts->ofUser($userId);

        if ($event->accepted === (null === $existing)) {
            // Ya dice lo que tiene que decir.
            return;
        }

        $this->session->execute(function () use ($event, $existing, $userId): void {
            if ($event->accepted) {
                // `$existing` no es nulo: lo dice la condición de arriba.
                $this->optOuts->remove($existing ?? throw new \LogicException('Nothing to remove.'));

                return;
            }

            $this->optOuts->save(new OverdraftOptOut($userId, $this->clock->now()));
        });
    }
}
