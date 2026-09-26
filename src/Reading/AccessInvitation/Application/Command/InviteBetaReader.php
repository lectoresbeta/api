<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\Command;

final readonly class InviteBetaReader
{
    public function __construct(
        public string $workId,
        public string $authorId,
        public ?string $inviteeId,
        public ?string $message,
    ) {
    }
}
