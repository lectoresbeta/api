<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Application\Command;

final readonly class InvitePersonToThePlatform
{
    public function __construct(
        public string $inviterId,
        public ?string $email,
    ) {
    }
}
