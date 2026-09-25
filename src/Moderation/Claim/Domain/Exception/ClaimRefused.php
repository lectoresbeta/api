<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureDetails;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No se puede presentar esa reclamación (`FEAT-MOD-001`).
 *
 * **Los motivos se distinguen a propósito**, al revés que en casi todo lo
 * demás de la plataforma: aquí quien llama no está averiguando nada sobre
 * nadie, está intentando usar una herramienta y necesita saber por qué no
 * puede. Decirle «no puedes» sin más es cómo se pierde a quien tenía algo
 * legítimo que denunciar.
 *
 * El caso con fecha lleva **cuándo** podrá volver, porque el bloqueo por
 * reclamaciones desestimadas es acumulativo y sin ese dato la pantalla no
 * puede decir nada útil.
 */
final class ClaimRefused extends \DomainException implements BusinessFailure, FailureDetails
{
    /**
     * @param array<string, scalar> $details
     */
    private function __construct(
        private readonly string $errorCode,
        private readonly FailureKind $kind,
        string $message,
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public static function becauseTheTargetIsNotClaimable(): self
    {
        return new self(
            'CLAIM_TARGET_NOT_CLAIMABLE',
            FailureKind::NOT_FOUND,
            'That cannot be claimed about: it does not exist, or it is not yours to claim about.',
        );
    }

    /**
     * `RN-5`: una corrección retenida por descubierto **todavía no se ha
     * podido leer**, y reclamarla a ciegas sería una forma de no pagarla.
     */
    /**
     * Nadie se denuncia a sí mismo (`FEAT-COM-035` `RN-3`).
     *
     * No es una formalidad: gasta el cupo mensual de quien la presenta y el
     * tiempo de quien la lee, y no hay desenlace posible que signifique algo.
     * Si alguien quiere irse, eso es dar de baja la cuenta.
     */
    public static function againstYourself(): self
    {
        return new self(
            'CANNOT_CLAIM_AGAINST_YOURSELF',
            FailureKind::INVALID,
            'You cannot report yourself.',
        );
    }

    public static function becauseTheCorrectionHasNotBeenRead(): self
    {
        return new self(
            'CLAIM_CORRECTION_NOT_READ',
            FailureKind::CONFLICT,
            'That correction cannot be claimed about until it can be read.',
        );
    }

    public static function becauseTheMonthlyLimitIsSpent(int $limit): self
    {
        return new self(
            'CLAIM_LIMIT_REACHED',
            FailureKind::RATE_LIMITED,
            'You have used all your claims for this month.',
            ['monthlyLimit' => $limit],
        );
    }

    /**
     * El bloqueo acumulativo de `RN-6b`. Lleva la fecha porque sin ella la
     * pantalla solo puede decir «no puedes», que no ayuda a nadie.
     */
    public static function untilTheBlockExpires(\DateTimeImmutable $until): self
    {
        return new self(
            'CLAIM_BLOCKED',
            FailureKind::FORBIDDEN,
            'Claiming is blocked for now because previous claims were dismissed.',
            ['blockedUntil' => $until->format(\DATE_ATOM)],
        );
    }

    public static function becauseThatReasonDoesNotExist(string $reason): self
    {
        return new self(
            'CLAIM_REASON_UNKNOWN',
            FailureKind::INVALID,
            \sprintf('There is no claim reason called %s.', $reason),
        );
    }

    public function failureDetails(): array
    {
        return $this->details;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function kind(): FailureKind
    {
        return $this->kind;
    }
}
