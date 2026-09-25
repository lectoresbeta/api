<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Application\Handler;

use LectoresBeta\Moderation\Administration\Application\Command\OrderCreditAdjustment;
use LectoresBeta\Moderation\Administration\Domain\Event\CreditAdjustmentOrdered;
use LectoresBeta\Moderation\Administration\Domain\Exception\AdministrationRefused;
use LectoresBeta\Moderation\AuditLog\Application\Service\RecordAuditEntry;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\AdministrableAccounts;

/**
 * Ordenar un ajuste manual de créditos (`FEAT-MOD-005` `RN-3`).
 *
 * **Ordena, no ajusta**, y la diferencia es toda la decisión. `Credits` no
 * recibe llamadas de nadie
 * ([`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)):
 * lo que recibe es el hecho de que un administrador decidió mover esa
 * cantidad, y él decide qué significa —un movimiento nuevo, nunca una
 * edición, y contabilizado como grifo y no como transferencia—.
 *
 * Por eso la respuesta es `202` y no `200`: cuando se contesta, el ajuste
 * está **ordenado** y no aplicado. Decir otra cosa sería mentir sobre algo
 * que el moderador va a comprobar mirando el saldo.
 *
 * El tope por ajuste no está en la ficha y lo pone la implementación: un
 * ajuste manual es la única vía que crea créditos de la nada, y un cero de
 * más tecleado a las tres de la mañana no debería poder desequilibrar la
 * economía entera. Quien necesite más hace dos, y los dos quedan auditados.
 */
final readonly class OrderCreditAdjustmentHandler
{
    public const MAX_PER_ADJUSTMENT = 1000;

    public function __construct(
        private AdministrableAccounts $accounts,
        private RecordAuditEntry $audit,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(OrderCreditAdjustment $command): void
    {
        $account = $this->accounts->ofId($command->userId);

        if (null === $account) {
            throw AdministrationRefused::userNotFound();
        }

        $reason = trim($command->reason ?? '');

        if ('' === $reason) {
            throw AdministrationRefused::withoutAReason();
        }

        $amount = $command->amount ?? 0;

        // Cero no es un ajuste: es una anotación sin efecto que ensuciaría el
        // histórico de créditos con un movimiento que no movió nada.
        if (0 === $amount) {
            throw AdministrationRefused::withoutAnAmount();
        }

        if (abs($amount) > self::MAX_PER_ADJUSTMENT) {
            throw AdministrationRefused::adjustmentTooLarge(self::MAX_PER_ADJUSTMENT);
        }

        $administrator = PartyId::fromString($command->administratorId);
        $now = $this->clock->now();

        $this->session->execute(function () use ($administrator, $account, $amount, $reason, $command): void {
            $this->audit->of(
                $administrator,
                'CREDIT_ADJUSTMENT_ORDERED',
                'USER',
                $account->userId,
                $reason,
                ['amount' => $amount, 'claimId' => $command->claimId],
            );
        });

        $this->events->publish(new CreditAdjustmentOrdered(
            EventId::generate(),
            PartyId::fromString($account->userId),
            $administrator,
            $amount,
            $reason,
            $command->claimId,
            $now,
        ));
    }
}
