<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Profile\Application\Event\CreditBalanceChanged;
use LectoresBeta\User\Profile\Domain\Entity\KnownCreditBalance;
use LectoresBeta\User\Profile\Domain\Repository\KnownCreditBalanceRepository;

/**
 * La copia del saldo que `User` necesita para pintar el menú (`FEAT-USR-027`).
 *
 * Es la misma forma que el grafo de seguidores y los bloqueos, y por la misma
 * razón de fondo: **`Credits` no publica contratos**, ni siquiera para leer.
 * Esa regla es dura, y una excepción «solo para consultar» abriría la puerta
 * que mañana alguien usa para algo más. Así que se escucha el hecho y se
 * guarda lo único que hace falta: un número.
 */
final readonly class TrackCreditBalance
{
    public function __construct(
        private KnownCreditBalanceRepository $balances,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(CreditBalanceChanged $event): void
    {
        try {
            $userId = UserId::fromString($event->userId);
        } catch (InvalidValue) {
            return;
        }

        $known = $this->balances->ofUser($userId);

        if (null === $known) {
            $known = new KnownCreditBalance($userId, $event->balance, $event->occurredAt());
        } else {
            $known->record($event->balance, $event->occurredAt());
        }

        $this->session->execute(function () use ($known): void {
            $this->balances->save($known);
        });
    }
}
