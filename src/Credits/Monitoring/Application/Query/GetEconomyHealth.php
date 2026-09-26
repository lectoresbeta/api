<?php

declare(strict_types=1);

namespace LectoresBeta\Credits\Monitoring\Application\Query;

final readonly class GetEconomyHealth
{
    public function __construct(
        public ?string $from = null,
        public ?string $to = null,
    ) {
    }
}
