<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Handler;

use LectoresBeta\Feedback\Correction\Application\Event\CorrectionTipped;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;

/**
 * Apuntar en la corrección la propina que recibió (`FEAT-CRD-017`).
 *
 * `Credits` la movió y lo cuenta; aquí solo se anota, porque es donde autor y
 * corrector la van a ver. **Este contexto no decide nada sobre créditos** y
 * no sabe de dónde salió la cifra.
 *
 * Se **fija**, no se acumula: el hecho dice cuánto fue la propina, y una
 * reentrega escribe la misma cifra. Sumar convertiría un reintento de la cola
 * en una propina más grande de la que nadie dio.
 */
final readonly class RecordTipOnCorrection
{
    public function __construct(
        private CorrectionRepository $corrections,
        private TransactionalSession $session,
    ) {
    }

    public function __invoke(CorrectionTipped $event): void
    {
        try {
            $correction = $this->corrections->ofId(CorrectionId::fromString($event->correctionId));
        } catch (InvalidValue) {
            // Un hecho con un identificador ilegible no se reintenta: se
            // descarta. Reintentarlo lo dejaría dando vueltas para siempre.
            return;
        }

        if (null === $correction) {
            return;
        }

        $this->session->execute(function () use ($correction, $event): void {
            $correction->recordTip($event->amount, $event->occurredAt());
            $this->corrections->save($correction);
        });
    }
}
