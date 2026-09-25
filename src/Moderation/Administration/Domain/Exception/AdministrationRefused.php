<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No se puede hacer eso desde el backoffice (`FEAT-MOD-005`).
 */
final class AdministrationRefused extends \DomainException implements BusinessFailure, FailureDetails
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

    public static function userNotFound(): self
    {
        return new self('USER_NOT_FOUND', FailureKind::NOT_FOUND, [], 'There is no account with that identifier.');
    }

    /**
     * `RN-2`: ni una sanción ni un ajuste se aceptan sin motivo. Lo que un
     * usuario no entiende no corrige nada, y lo que nadie puede revisar
     * después no se puede corregir tampoco.
     */
    public static function withoutAReason(): self
    {
        return new self('REASON_REQUIRED', FailureKind::INVALID, [], 'Every backoffice action needs a reason.');
    }

    public static function withoutAnAmount(): self
    {
        return new self('AMOUNT_REQUIRED', FailureKind::INVALID, [], 'A credit adjustment needs a non-zero amount.');
    }

    public static function adjustmentTooLarge(int $maximum): self
    {
        return new self(
            'ADJUSTMENT_TOO_LARGE',
            FailureKind::INVALID,
            ['maximum' => $maximum],
            \sprintf('A single adjustment moves at most %d credits.', $maximum),
        );
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
