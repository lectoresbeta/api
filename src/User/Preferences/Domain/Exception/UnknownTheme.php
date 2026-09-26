<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * Ese tema no existe (`FEAT-USR-042` `RN-3`).
 */
final class UnknownTheme extends \DomainException implements BusinessFailure
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    public static function create(?string $value): self
    {
        return new self(\sprintf(
            'The theme is LIGHT, DARK or SYSTEM; "%s" is none of them.',
            $value ?? '',
        ));
    }

    public function errorCode(): string
    {
        return 'UNKNOWN_THEME';
    }

    public function kind(): FailureKind
    {
        return FailureKind::INVALID;
    }
}
