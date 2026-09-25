<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Handler;

use LectoresBeta\Credits\Account\Application\Event\ClaimUpheld;
use LectoresBeta\Credits\Account\Application\Service\AnnounceMovement;
use LectoresBeta\Credits\Account\Domain\Entity\CreditAccount;
use LectoresBeta\Credits\Account\Domain\Entity\CreditTransaction;
use LectoresBeta\Credits\Account\Domain\Enum\CreditTransactionReason;
use LectoresBeta\Credits\Account\Domain\Repository\CreditAccountRepository;
use LectoresBeta\Credits\Account\Domain\Repository\CreditTransactionRepository;
use LectoresBeta\Credits\Account\Domain\ValueObject\CreditTransactionId;
use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;
use LectoresBeta\Credits\Pricing\Application\Service\RefreshCorrectability;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Deshacer el pago de una corrección fraudulenta (`FEAT-MOD-002`).
 *
 * **No se edita ni se borra el movimiento original**: los movimientos son
 * inmutables (`decision:0006` `RN-2`), y una reversión son dos apuntes
 * nuevos, del mismo importe y en sentido contrario. La masa de créditos de la
 * economía no cambia, que es lo que `RN-11` comprueba.
 *
 * Tres detalles que parecen pequeños y no lo son:
 *
 * - **el importe es el que se cobró**, no el precio vigente del capítulo, que
 *   puede haber subido o bajado desde entonces. Por eso se lee del apunte y
 *   no se vuelve a calcular;
 * - **la propina no se revierte**. El autor la dio voluntariamente después de
 *   leer, y reclamar una corrección que se propinó es contradictorio. Aquí
 *   eso es simplemente no mirar los movimientos de propina;
 * - **el corrector puede quedar en negativo**, porque quizá ya gastó esos
 *   créditos. No hace falta ninguna regla nueva: es exactamente lo que
 *   `FEAT-CRD-018` describe, y la salida es la de siempre, corregir para
 *   saldarlo.
 *
 * La decisión de si esto procede la toma este contexto, no quien publicó el
 * hecho: `ClaimUpheld` dice que se estimó una reclamación sobre una
 * corrección y nada más.
 */
final readonly class ReverseCorrectionOnClaimUpheld
{
    private const CONSUMER = 'claim-reversal';

    private const CORRECTION = 'CORRECTION';

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

    public function __invoke(ClaimUpheld $event): void
    {
        if ($this->processedEvents->wasProcessed($event->eventId(), self::CONSUMER)) {
            return;
        }

        $now = $this->clock->now();

        if (self::CORRECTION !== $event->targetType) {
            // Obras, capítulos y usuarios no mueven saldo. Se anota como
            // visto para no volver a mirarlo en cada reintento.
            $this->session->execute(fn () => $this->markProcessed($event, $now));

            return;
        }

        $movements = $this->transactions->ofCorrection($event->targetId);
        $charge = self::firstWithReason($movements, CreditTransactionReason::CORRECTION_CHARGED);
        $earning = self::firstWithReason($movements, CreditTransactionReason::CORRECTION_EARNED);

        // Ya revertida, o nunca cobrada. Las dos acaban igual: no hay nada
        // que devolver, y anotarlo evita que un reintento lo reconsidere.
        if (null === $charge || null === $earning
            || null !== self::firstWithReason($movements, CreditTransactionReason::CLAIM_REVERSAL_REFUND)) {
            $this->session->execute(fn () => $this->markProcessed($event, $now));

            return;
        }

        $amount = abs($charge->amount());
        $authorId = $charge->userId();
        $readerId = $earning->userId();

        $author = $this->accounts->ofUser($authorId) ?? new CreditAccount($authorId, $now);
        $reader = $this->accounts->ofUser($readerId) ?? new CreditAccount($readerId, $now);

        $authorBefore = $author->balance();
        $readerBefore = $reader->balance();

        $metadata = [
            'correctionId' => $event->targetId,
            'claimId' => $event->claimId,
            'reversalOf' => $charge->id()->value(),
        ];

        $refund = $author->apply(
            CreditTransactionId::generate(),
            $amount,
            CreditTransactionReason::CLAIM_REVERSAL_REFUND,
            $now,
            $event->eventId(),
            $metadata,
        );

        $withdrawal = $reader->apply(
            CreditTransactionId::generate(),
            -$amount,
            CreditTransactionReason::CLAIM_REVERSAL_CHARGE,
            $now,
            $event->eventId(),
            [...$metadata, 'reversalOf' => $earning->id()->value()],
        );

        $this->session->execute(function () use ($author, $reader, $refund, $withdrawal, $event, $now): void {
            $this->accounts->save($author);
            $this->accounts->save($reader);
            $this->transactions->add($refund);
            $this->transactions->add($withdrawal);

            $this->markProcessed($event, $now);
        });

        $this->announce->of($refund, $authorBefore, $author->balance());
        $this->announce->of($withdrawal, $readerBefore, $reader->balance());

        // Los dos saldos se han movido y los dos pueden ser autores: uno
        // vuelve a poder pagar lo que ofrece, el otro quizá ya no.
        $this->correctability->forAuthor($authorId);
        $this->correctability->forAuthor($readerId);
    }

    /**
     * @param list<CreditTransaction> $movements
     */
    private static function firstWithReason(array $movements, CreditTransactionReason $reason): ?CreditTransaction
    {
        foreach ($movements as $movement) {
            if ($reason === $movement->reason()) {
                return $movement;
            }
        }

        return null;
    }

    private function markProcessed(ClaimUpheld $event, \DateTimeImmutable $now): void
    {
        $this->processedEvents->markProcessed(
            new ProcessedEvent($event->eventId(), self::CONSUMER, $event->eventName(), $now),
        );
    }
}
