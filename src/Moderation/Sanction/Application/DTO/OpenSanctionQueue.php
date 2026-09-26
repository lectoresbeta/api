<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Application\DTO;

final readonly class OpenSanctionQueue
{
    /**
     * @param list<OpenSanction> $sanctions
     */
    public function __construct(
        public array $sanctions,
        public int $total,
        public int $page,
        public int $perPage,
    ) {
    }

    public function totalPages(): int
    {
        return max(1, (int) ceil($this->total / $this->perPage));
    }
}
