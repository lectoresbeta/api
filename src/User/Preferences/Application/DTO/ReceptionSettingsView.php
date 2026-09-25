<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\DTO;

/**
 * Lo que enseña la pantalla de recepción de propuestas (`FEAT-USR-011`).
 */
final readonly class ReceptionSettingsView
{
    public function __construct(
        public bool $betaReaderInvitations,
        public bool $writingBuddyProposals,
        public \DateTimeImmutable $updatedAt,
    ) {
    }
}
