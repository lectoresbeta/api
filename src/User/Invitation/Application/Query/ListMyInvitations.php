<?php

declare(strict_types=1);

namespace LectoresBeta\User\Invitation\Application\Query;

final readonly class ListMyInvitations
{
    public function __construct(
        public string $inviterId,
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}
