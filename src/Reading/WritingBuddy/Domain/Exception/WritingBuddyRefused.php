<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Domain\Exception;

use LectoresBeta\Shared\Domain\Exception\BusinessFailure;
use LectoresBeta\Shared\Domain\Exception\FailureKind;

/**
 * La propuesta de writing buddy no sale (`FEAT-RDG-008`).
 *
 * `notAccepted()` **no dice por qué**, igual que en los mensajes directos: si
 * distinguiera «lo tiene cerrado» de «te ha bloqueado», cualquiera podría
 * averiguar los ajustes de otro probando a proponerle algo.
 */
final class WritingBuddyRefused extends \DomainException implements BusinessFailure
{
    private function __construct(
        private readonly string $failureCode,
        private readonly FailureKind $failureKind,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function toYourself(): self
    {
        return new self(
            'CANNOT_BE_YOUR_OWN_BUDDY',
            FailureKind::INVALID,
            'There is no writing buddy link with one person in it.',
        );
    }

    public static function notAccepted(): self
    {
        return new self(
            'PROPOSALS_NOT_ACCEPTED',
            FailureKind::FORBIDDEN,
            'That person does not accept writing buddy proposals.',
        );
    }

    /**
     * @param list<string> $allowed
     */
    public static function unknownDecision(string $given, array $allowed): self
    {
        return new self(
            'UNKNOWN_DECISION',
            FailureKind::INVALID,
            \sprintf('«%s» is not a decision. Use one of: %s.', $given, implode(', ', $allowed)),
        );
    }

    public static function alreadyLinked(): self
    {
        return new self(
            'WRITING_BUDDY_ALREADY_LIVE',
            FailureKind::CONFLICT,
            'There is already a live writing buddy link with that person.',
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
