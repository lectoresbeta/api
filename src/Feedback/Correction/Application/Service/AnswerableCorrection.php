<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Service;

use LectoresBeta\Feedback\Correction\Domain\Entity\Correction;
use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionOrigin;
use LectoresBeta\Feedback\Correction\Domain\Enum\CorrectionStatus;
use LectoresBeta\Feedback\Correction\Domain\Exception\CorrectionHasNoWriter;
use LectoresBeta\Feedback\Correction\Domain\Exception\CorrectionLocked;
use LectoresBeta\Feedback\Correction\Domain\Exception\CorrectionNotFound;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

/**
 * Las tres puertas que comparten contestar y valorar (`FEAT-FBK-005`,
 * `FEAT-FBK-006`).
 *
 * Están juntas porque son la misma pregunta hecha una vez —«¿puede esta
 * persona responder a esto?»— y separarlas en dos casos de uso sería
 * escribirlas dos veces y arreglarlas una.
 *
 * 1. **solo el autor de la obra**, y una corrección ajena responde como una
 *    inexistente;
 * 2. **no si está retenida por descubierto**: no se ha leído, así que no hay
 *    nada que contestar ni que valorar;
 * 3. **no si llegó por enlace público**: no hay cuenta detrás a la que
 *    dirigirse.
 */
final readonly class AnswerableCorrection
{
    public function __construct(private CorrectionRepository $corrections)
    {
    }

    public function ownedBy(string $correctionId, string $authorId, bool $needsAWriter = true): Correction
    {
        try {
            $correction = $this->corrections->ofId(CorrectionId::fromString($correctionId));
        } catch (InvalidValue) {
            throw CorrectionNotFound::withId($correctionId);
        }

        if (null === $correction
            || CorrectionStatus::SUBMITTED !== $correction->status()
            || $correction->ownerId()->value() !== $authorId) {
            throw CorrectionNotFound::withId($correctionId);
        }

        if (!$correction->isReadable()) {
            throw CorrectionLocked::create();
        }

        if ($needsAWriter && CorrectionOrigin::PUBLIC_LINK === $correction->origin()) {
            throw CorrectionHasNoWriter::create();
        }

        return $correction;
    }
}
