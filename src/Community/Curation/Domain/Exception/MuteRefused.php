<?php

declare(strict_types=1);

namespace LectoresBeta\Community\Curation\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * No se puede silenciar a esa persona (`FEAT-COM-033`).
 */
final class MuteRefused extends \DomainException implements BusinessFailure
{
    private function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    public static function yourself(): self
    {
        return new self('CANNOT_MUTE_YOURSELF', 'You cannot mute yourself.');
    }

    public static function userNotFound(): self
    {
        return new self('USER_NOT_FOUND', 'There is no such user to mute.');
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
