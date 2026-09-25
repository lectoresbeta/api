<?php

declare(strict_types=1);

namespace LectoresBeta\User\Preferences\Application\Command;

final readonly class UpdateReceptionSettings
{
    public function __construct(
        public string $userId,
        public ?bool $betaReaderInvitations,
        public ?bool $writingBuddyProposals,
    ) {
    }
}
