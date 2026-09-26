<?php

declare(strict_types=1);

namespace LectoresBeta\User\Onboarding\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Ese tour no existe, o ese paso no está en él (`FEAT-USR-026`).
 *
 * Se rechaza en vez de ignorarse porque lo que se guarda es **la métrica que
 * dice si el tour funciona** (`RN-4`). Un identificador con una errata
 * crearía un tour fantasma que nadie ha visto nunca; un paso fuera de rango
 * estropearía el recuento en silencio, que es la forma en que un dato malo
 * sobrevive más tiempo.
 */
final class UnknownTour extends \DomainException implements BusinessFailure
{
    private function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    public static function named(): self
    {
        return new self('UNKNOWN_TOUR', 'There is no such tour.');
    }

    public static function step(int $steps): self
    {
        return new self('UNKNOWN_TOUR_STEP', \sprintf('This tour has %d steps.', $steps));
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
