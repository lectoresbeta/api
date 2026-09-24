<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\Command;

final readonly class ResolveInvitation
{
    public function __construct(
        public string $invitationId,
        public string $inviteeId,
        public ?string $decision,
    ) {
    }
}
