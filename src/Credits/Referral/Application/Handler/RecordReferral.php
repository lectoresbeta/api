<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Referral\Application\Handler;

use LectoresBeta\Credits\Account\Domain\ValueObject\UserId;
use LectoresBeta\Credits\EventProcessing\Domain\Entity\ProcessedEvent;
use LectoresBeta\Credits\EventProcessing\Domain\Repository\ProcessedEventRepository;
use LectoresBeta\Credits\Referral\Application\Event\PlatformInvitationConsumed;
use LectoresBeta\Credits\Referral\Domain\Entity\Referral;
use LectoresBeta\Credits\Referral\Domain\Repository\ReferralRepository;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Apuntar el par, sin pagar nada (`FEAT-CRD-005` `RN-1`).
 *
 * **Aquí no se mueve un crédito**, y esa es la mitad del diseño. Pagar al
 * registrarse haría que una invitación valiese lo que cuesta un correo
 * desechable; pagar cuando el invitado entrega su primera corrección hace que
 * valga una corrección de verdad (`decision:0006`, regla 6). Lo que ocurre en
 * este momento es solo dejar escrito quién trajo a quién, para poder
 * reconocerlo meses después.
 *
 * **El primero gana** (`RN-2`). Si dos personas invitaron a la misma y el
 * hecho llega dos veces, el par ya apuntado no se reescribe: cambiar de
 * invitador después de haber pagado pagaría dos veces por un alta, y sin
 * haber pagado no arregla nada que merezca el riesgo.
 */
final readonly class RecordReferral
{
    /**
     * Nombra **la regla**, no la clase. Renombrar esta no puede reabrir
     * hechos ya aplicados (`FEAT-CRD-011` `RN-4`).
     */
    private const CONSUMER = 'referral-record';

    public function __construct(
        private ReferralRepository $referrals,
        private ProcessedEventRepository $processedEvents,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(PlatformInvitationConsumed $event): void
    {
        if ($this->processedEvents->wasProcessed($event->eventId(), self::CONSUMER)) {
            return;
        }

        $inviterId = UserId::fromString($event->inviterId);
        $inviteeId = UserId::fromString($event->inviteeId);
        $now = $this->clock->now();

        // Nadie se trae a sí mismo. `User` ya lo impide al consumir, pero un
        // hecho es un hecho: llegado uno así, apuntarlo dejaría a alguien
        // cobrando por su propia primera corrección.
        $alreadyLinked = $inviterId->equals($inviteeId)
            || null !== $this->referrals->ofInvitee($inviteeId);

        $this->session->execute(function () use ($alreadyLinked, $inviterId, $inviteeId, $event, $now): void {
            if (!$alreadyLinked) {
                $this->referrals->save(new Referral($inviteeId, $inviterId, $now));
            }

            $this->processedEvents->markProcessed(
                new ProcessedEvent($event->eventId(), self::CONSUMER, $event->eventName(), $now),
            );
        });
    }
}
