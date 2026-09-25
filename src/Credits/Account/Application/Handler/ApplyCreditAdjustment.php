<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Handler;

use LectoresBeta\Credits\Account\Application\Event\CreditAdjustmentOrdered;
use LectoresBeta\Credits\Account\Application\Service\AnnounceMovement;
use LectoresBeta\Credits\Account\Domain\Entity\CreditAccount;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\Repository\CreditTransactionRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\CreditTransactionId;
use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;
use LectoresBeta\Credits\Pricing\Application\Service\RefreshCorrectability;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * El ajuste manual que un administrador ordenó (`FEAT-MOD-005` `RN-3`).
 *
 * **Se aplica como `MANUAL_ADJUSTMENT`, que es un grifo** (`RN-4`). Un ajuste
 * crea o destruye créditos de la nada, y contarlo como transferencia haría
 * fallar la invariante contable sin que nadie supiera por qué
 * ([`FEAT-CRD-012`](../../../../../docs/features/credits/FEAT-CRD-012-economy-health.md)).
 * Como el motivo de que ese tipo esté en la lista de grifos es exactamente
 * este caso, no hay nada especial que hacer: basta con no equivocarse de
 * tipo.
 *
 * **Nunca edita un movimiento existente.** Corregir un error de crédito es
 * añadir otro apunte, no reescribir el primero: el saldo tiene que poder
 * recalcularse desde cero y coincidir con la historia.
 *
 * Idempotente por el registro de lo ya aplicado: acumula, así que una
 * reentrega ajustaría dos veces lo que alguien ordenó una.
 *
 * **No comprueba si el saldo queda negativo.** Un ajuste puede dejarlo así a
 * propósito —revertir un abono indebido es justamente eso—, y este contexto
 * ya sabe convivir con el descubierto (`FEAT-CRD-018`).
 */
final readonly class ApplyCreditAdjustment
{
    private const CONSUMER = 'manual-adjustment';

    public function __construct(
        private CreditAccountRepository $accounts,
        private CreditTransactionRepository $transactions,
        private ProcessedEventRepository $processedEvents,
        private AnnounceMovement $announce,
        private RefreshCorrectability $correctability,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(CreditAdjustmentOrdered $event): void
    {
        if ($this->processedEvents->wasProcessed($event->eventId(), self::CONSUMER)) {
            return;
        }

        try {
            $userId = UserId::fromString($event->userId);
        } catch (InvalidValue) {
            // Un identificador ilegible no se reintenta: se descarta.
            return;
        }

        $now = $this->clock->now();
        $account = $this->accounts->ofUser($userId) ?? new CreditAccount($userId, $now);
        $before = $account->balance();

        $movement = $account->apply(
            CreditTransactionId::generate(),
            $event->amount,
            CreditTransactionReason::MANUAL_ADJUSTMENT,
            $now,
            $event->eventId(),
            // El motivo viaja con el movimiento: sin él, el apunte es
            // inexplicable seis meses después, que es cuando alguien pregunta.
            ['reason' => $event->reason, 'claimId' => $event->claimId],
        );

        $this->session->execute(function () use ($account, $movement, $event, $now): void {
            $this->accounts->save($account);
            $this->transactions->add($movement);
            $this->processedEvents->markProcessed(
                new ProcessedEvent($event->eventId(), self::CONSUMER, $event->eventName(), $now),
            );
        });

        $this->announce->of($movement, $before, $account->balance());

        // El saldo ha cambiado, así que puede haber capítulos suyos que pasen
        // a ser corregibles o dejen de serlo (`FEAT-CRD-009`).
        $this->correctability->forAuthor($userId);
    }
}
