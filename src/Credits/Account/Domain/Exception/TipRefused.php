<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No se puede propinar eso (`FEAT-CRD-017`).
 */
final class TipRefused extends \DomainException implements BusinessFailure, FailureDetails
{
    /**
     * @param array<string, scalar> $details
     */
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        private readonly array $details,
        string $message,
    ) {
        parent::__construct($message);
    }

    /**
     * Ni existe, ni es suya, ni se ha pagado todavía: **una sola respuesta**.
     *
     * Este contexto sabe quién pagó cada corrección porque lo tiene apuntado,
     * así que «no es tuya» y «no existe» se contestan igual. Distinguirlas
     * diría algo sobre las correcciones de otra persona.
     */
    public static function notYours(): self
    {
        return new self('CORRECTION_NOT_FOUND', FailureKind::NOT_FOUND, [], 'That correction is not one of yours.');
    }

    public static function outOfRange(int $min, int $max): self
    {
        return new self(
            'TIP_OUT_OF_RANGE',
            FailureKind::INVALID,
            ['minimumTip' => $min, 'maximumTip' => $max],
            \sprintf('A tip is between %d and %d credits.', $min, $max),
        );
    }

    /**
     * `RN-1`: **la propina no genera descubierto**.
     *
     * El descubierto existe para que un lector nunca trabaje sin cobrar, y
     * una propina es voluntaria. Endeudarse por ser generoso sería una
     * trampa.
     */
    public static function notAffordable(int $balance): self
    {
        return new self(
            'INSUFFICIENT_CREDITS',
            FailureKind::CONFLICT,
            ['balance' => $balance],
            'A tip comes out of what you have; it does not go into debt.',
        );
    }

    /**
     * `RN-4`: una por corrección. Y es irrevocable, así que volver a pulsar
     * no puede significar «otra vez».
     */
    public static function alreadyTipped(): self
    {
        return new self('ALREADY_TIPPED', FailureKind::CONFLICT, [], 'That correction has already been tipped.');
    }

    /**
     * `RN-6`: una corrección por enlace público **no tiene cuenta a la que
     * abonar**, porque quien la escribió no tiene cuenta.
     */
    public static function nobodyToPay(): self
    {
        return new self('NOBODY_TO_TIP', FailureKind::CONFLICT, [], 'That correction has no account to credit.');
    }

    public function failureDetails(): array
    {
        return $this->details;
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return $this->failureKind;
    }
}
