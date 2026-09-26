<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Account\Application\Query;

final readonly class ListCreditMovements
{
    public function __construct(
        public string $userId,
        public ?string $reason = null,
        public ?string $from = null,
        public ?string $to = null,
        public int $limit = 50,
        public int $offset = 0,
    ) {
    }
}
