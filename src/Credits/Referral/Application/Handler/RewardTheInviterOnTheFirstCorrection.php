<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Referral\Application\Handler;

use LectoresBeta\Credits\Account\Application\Event\FeedbackSubmitted;
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
use LectoresBeta\Credits\Referral\Domain\Repository\ReferralRepository;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * La recompensa por invitación (`FEAT-CRD-005`).
 *
 * Escucha **el mismo hecho** que el cobro de la corrección, y por eso el
 * consumidor se llama distinto: con el identificador del evento a secas, la
 * primera regla en verlo lo cerraría para la otra (`FEAT-CRD-011` `RN-4`).
 *
 * Es un **grifo**: cinco créditos que entran en la economía desde ninguna
 * parte, como el regalo de bienvenida. Por eso lleva tres topes, y ninguno
 * sobra:
 *
 * - **una vez por persona invitada** (`RN-3`), que garantiza la fila y no la
 *   deduplicación de eventos: la segunda corrección del invitado llega con un
 *   evento distinto y legítimo;
 * - **diez recompensas por invitador** (`RN-5`), para que nadie construya su
 *   saldo reclutando en lugar de corrigiendo;
 * - **la corrección tiene que ser de verdad**, que es lo que el momento
 *   elegido consigue sin comprobar nada (`decision:0006`, regla 6).
 *
 * Quien no fue invitado por nadie no tiene fila, y esto es entonces una
 * consulta y nada más. Es el caso normal, y tiene que ser barato.
 */
final readonly class RewardTheInviterOnTheFirstCorrection
{
    /**
     * Nombra **la regla**, no la clase (`FEAT-CRD-011` `RN-4`).
     */
    private const CONSUMER = 'referral-reward';

    public function __construct(
        private ReferralRepository $referrals,
        private CreditAccountRepository $accounts,
        private CreditTransactionRepository $transactions,
        private ProcessedEventRepository $processedEvents,
        private AnnounceMovement $announce,
        private RefreshCorrectability $correctability,
        private TransactionalSession $session,
        private Clock $clock,
        private int $invitationReward,
        private int $rewardedInvitationLimit,
    ) {
    }

    public function __invoke(FeedbackSubmitted $event): void
    {
        if ($this->processedEvents->wasProcessed($event->eventId(), self::CONSUMER)) {
            return;
        }

        $now = $this->clock->now();
        $readerId = UserId::fromString($event->readerId);
        $referral = $this->referrals->ofInvitee($readerId);

        // Sin invitador, ya pagada, o con el tope alcanzado: se apunta como
        // vista y se acaba. Marcarla igualmente es lo que impide que cada
        // reentrega repita las mismas consultas para siempre.
        if (
            null === $referral
            || $referral->wasRewarded()
            || $event->authorId === $event->readerId
            || $this->referrals->rewardedCountOf($referral->inviterId()) >= $this->rewardedInvitationLimit
        ) {
            $this->session->execute(fn () => $this->markProcessed($event, $now));

            return;
        }

        $inviterId = $referral->inviterId();
        $inviter = $this->accounts->ofUser($inviterId) ?? new CreditAccount($inviterId, $now);
        $balanceBefore = $inviter->balance();

        $referral->reward($now);

        $movement = $inviter->apply(
            CreditTransactionId::generate(),
            $this->invitationReward,
            CreditTransactionReason::INVITATION_REWARD,
            $now,
            $event->eventId(),
            // Sin el identificador de la corrección: el movimiento del
            // invitador no es de esa corrección —no la pagó ni la cobró— y
            // citarla haría que `FEAT-FBK-010` le contase a quien corrigió
            // unos créditos que no ha ganado.
            ['inviteeId' => $event->readerId],
        );

        // La marca de pagado, el apunte y la deduplicación cierran juntos o
        // no cierra ninguno. Aparte, una caída entre medias paga dos veces o
        // da por pagada una recompensa que nadie recibió (`FEAT-CRD-011`
        // `RN-2`).
        $this->session->execute(function () use ($referral, $inviter, $movement, $event, $now): void {
            $this->referrals->save($referral);
            $this->accounts->save($inviter);
            $this->transactions->add($movement);
            $this->markProcessed($event, $now);
        });

        $this->announce->of($movement, $balanceBefore, $inviter->balance());

        // Cinco créditos pueden ser justo los que le faltaban para poder
        // pagar una corrección, así que sus capítulos pueden volverse
        // corregibles en este mismo instante (`FEAT-CRD-009`).
        $this->correctability->forAuthor($inviterId);
    }

    private function markProcessed(FeedbackSubmitted $event, \DateTimeImmutable $now): void
    {
        $this->processedEvents->markProcessed(
            new ProcessedEvent($event->eventId(), self::CONSUMER, $event->eventName(), $now),
        );
    }
}
