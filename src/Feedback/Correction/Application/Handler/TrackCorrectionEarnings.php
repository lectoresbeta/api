<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Event\CreditsAdded;
use LectoresBeta\Feedback\Correction\Application\Event\CreditsSpent;
use LectoresBeta\Feedback\Correction\Domain\Entity\CorrectionEarning;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionEarningRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Qué ganó cada quien por lo que corrigió (`FEAT-FBK-010`).
 *
 * Es una **proyección**, no un contrato: `Credits` no publica ninguno, ni
 * siquiera de consulta, así que la única forma de saber el importe es
 * escuchar lo que ese contexto cuenta
 * ([`decision:0002`](../../../../../docs/decisions/0002-credits-as-isolated-bounded-context.md)).
 * Es el mismo camino que ya sigue `User` para pintar el saldo en el menú.
 *
 * Dos hechos, un trabajo, y el segundo es el importante: una **reversión**
 * por reclamación estimada retira lo que se cobró
 * ([`FEAT-MOD-002`](../../../../../docs/features/moderation/FEAT-MOD-002-review-claim.md)),
 * y quien mire su lista después no puede seguir viendo un ingreso que ya no
 * tiene.
 *
 * Solo interesan los movimientos que **citan una corrección**. El resto del
 * dinero de esa persona —bienvenida, invitaciones, propinas— no es asunto de
 * este contexto y no se guarda aquí.
 */
final readonly class TrackCorrectionEarnings
{
    private const EARNED = 'CORRECTION_EARNED';
    private const REVERSED = 'CLAIM_REVERSAL_CHARGE';

    public function __construct(
        private CorrectionEarningRepository $earnings,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function earned(CreditsAdded $event): void
    {
        if (self::EARNED !== $event->reason) {
            return;
        }

        $this->settle($event->correctionId, $event->amount);
    }

    public function reversed(CreditsSpent $event): void
    {
        if (self::REVERSED !== $event->reason) {
            return;
        }

        // Se revierte lo que se cobró, entero: la cifra queda en cero.
        $this->settle($event->correctionId, 0);
    }

    /**
     * Fija la cifra en vez de sumarla. El transporte entrega **al menos** una
     * vez, así que acumular rompería la proyección el día que se repita un
     * mensaje — y lo haría en silencio, que es lo peor de todo.
     */
    private function settle(?string $correctionId, int $credits): void
    {
        if (null === $correctionId) {
            return;
        }

        try {
            $id = CorrectionId::fromString($correctionId);
        } catch (InvalidValue) {
            return;
        }

        $now = $this->clock->now();
        $earning = $this->earnings->ofCorrection($id);

        $this->session->execute(function () use ($earning, $id, $credits, $now): void {
            if (null === $earning) {
                $this->earnings->save(new CorrectionEarning($id, $credits, $now));

                return;
            }

            $earning->settleAt($credits, $now);
            $this->earnings->save($earning);
        });
    }
}
