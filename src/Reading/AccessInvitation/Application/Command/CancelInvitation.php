<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\Command;

final readonly class CancelInvitation
{
    public function __construct(
        public string $invitationId,
        public string $authorId,
    ) {
    }
}
