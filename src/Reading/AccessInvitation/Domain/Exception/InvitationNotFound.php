<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * It is not there, or it must not be revealed to be there (`FEAT-RDG-004`).
 *
 * Somebody else's work and somebody else's invitation answer the same as ones
 * that never existed. Confirming that either exists is already saying
 * something about who was offered what.
 */
final class InvitationNotFound extends \DomainException implements BusinessFailure
{
    private function __construct(private readonly string $failureCode, string $message)
    {
        parent::__construct($message);
    }

    public static function work(): self
    {
        return new self('WORK_NOT_FOUND', 'That work does not exist.');
    }

    public static function invitation(): self
    {
        return new self('INVITATION_NOT_FOUND', 'That invitation does not exist.');
    }

    public function errorCode(): string
    {
        return $this->failureCode;
    }

    public function kind(): FailureKind
    {
        return FailureKind::NOT_FOUND;
    }
}
