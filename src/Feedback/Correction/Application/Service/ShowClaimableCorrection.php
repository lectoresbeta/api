<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Service;

use LectoresBeta\Feedback\Correction\Application\Contract\ClaimableCorrection;
use LectoresBeta\Feedback\Correction\Application\Contract\ClaimableCorrections;
use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionStatus;
use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionVisibility;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * El lado de `Feedback` del contrato.
 *
 * «Se puede leer» son dos cosas a la vez: que esté **entregada** —un borrador
 * no es nada todavía— y que esté **visible**. Una retenida por descubierto
 * aún no se ha podido leer, y reclamarla a ciegas sería una forma de no
 * pagarla.
 */
final readonly class ShowClaimableCorrection implements ClaimableCorrections
{
    public function __construct(private CorrectionRepository $corrections)
    {
    }

    public function ofId(string $correctionId): ?ClaimableCorrection
    {
        try {
            $correction = $this->corrections->ofId(CorrectionId::fromString($correctionId));
        } catch (InvalidValue) {
            return null;
        }

        if (null === $correction) {
            return null;
        }

        return new ClaimableCorrection(
            $correction->id()->value(),
            $correction->ownerId()->value(),
            $correction->readerId()?->value() ?? '',
            CorrectionStatus::SUBMITTED === $correction->status()
                && CorrectionVisibility::VISIBLE === $correction->visibility(),
        );
    }
}
