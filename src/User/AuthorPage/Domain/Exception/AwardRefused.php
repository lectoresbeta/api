<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;
use LectoresBeta\User\AuthorPage\Domain\Service\AwardPolicy;

/**
 * El premio no se puede guardar, y esto es por qué (`FEAT-USR-030`).
 *
 * `notYours()` responde `403` y **no `404`**, igual que en la bibliografía:
 * esconder la existencia de algo solo tiene sentido cuando lo que se protege
 * es saber que existe, y un premio se enseña en un perfil abierto.
 */
final class AwardRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function notFound(): self
    {
        return new self(
            'AWARD_NOT_FOUND',
            FailureKind::NOT_FOUND,
            'That award does not exist.',
        );
    }

    public static function notYours(): self
    {
        return new self(
            'NOT_YOUR_AWARD',
            FailureKind::FORBIDDEN,
            'Only its holder manages an award.',
        );
    }

    public static function missingTitle(): self
    {
        return new self(
            'VALIDATION_FAILED',
            FailureKind::INVALID,
            'An award needs a title.',
        );
    }

    public static function titleTooLong(): self
    {
        return new self(
            'AWARD_TITLE_TOO_LONG',
            FailureKind::INVALID,
            \sprintf('An award title is at most %d characters.', AwardPolicy::TITLE_MAX_LENGTH),
        );
    }

    public static function awardedByTooLong(): self
    {
        return new self(
            'AWARD_GRANTOR_TOO_LONG',
            FailureKind::INVALID,
            \sprintf('Who granted the award is at most %d characters.', AwardPolicy::GRANTOR_MAX_LENGTH),
        );
    }

    public static function noteTooLong(): self
    {
        return new self(
            'AWARD_NOTE_TOO_LONG',
            FailureKind::INVALID,
            \sprintf('An award note is at most %d characters.', AwardPolicy::NOTE_MAX_LENGTH),
        );
    }

    /**
     * El rango atrapa el error de teclado —un `19` o un `202`—, no le discute
     * a nadie su trayectoria.
     */
    public static function implausibleYear(): self
    {
        return new self(
            'IMPLAUSIBLE_AWARD_YEAR',
            FailureKind::INVALID,
            'That year does not look like a year an award was granted.',
        );
    }

    /**
     * Solo `http` y `https`. Cualquier otro esquema convertiría lo que parece
     * un enlace en algo que ejecuta lo que escribió un extraño.
     */
    public static function invalidUrl(): self
    {
        return new self(
            'INVALID_AWARD_URL',
            FailureKind::INVALID,
            'That link is not a usable web address.',
        );
    }

    public static function tooMany(): self
    {
        return new self(
            'TOO_MANY_AWARDS',
            FailureKind::CONFLICT,
            \sprintf('A profile holds at most %d awards.', AwardPolicy::MAX_PER_AUTHOR),
        );
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
