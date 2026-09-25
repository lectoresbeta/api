<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Messaging\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * El mensaje no sale (`FEAT-COM-011`).
 *
 * `notAccepted()` es el único que merece explicación: dice que esa persona no
 * admite que le escribas, y **no dice por qué**. Si distinguiera «lo tiene en
 * solo seguidores» de «lo tiene cerrado» de «te ha bloqueado», cualquiera
 * podría averiguar los ajustes de privacidad de otro probando a escribirle, y
 * el bloqueo dejaría de ser discreto.
 */
final class MessageRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function empty(): self
    {
        return new self('VALIDATION_FAILED', FailureKind::INVALID, 'A message needs something in it.');
    }

    public static function tooLong(): self
    {
        return new self(
            'VALIDATION_FAILED',
            FailureKind::INVALID,
            'That message is longer than 4000 characters.',
        );
    }

    public static function toYourself(): self
    {
        return new self(
            'CANNOT_MESSAGE_YOURSELF',
            FailureKind::INVALID,
            'There is no conversation with one person in it.',
        );
    }

    public static function notAccepted(): self
    {
        return new self(
            'MESSAGES_NOT_ACCEPTED',
            FailureKind::FORBIDDEN,
            'That person does not accept messages from you.',
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
