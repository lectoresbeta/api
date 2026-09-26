<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\DTO;

/**
 * Una fila de «mis grupos» (`FEAT-RDG-007`).
 */
final readonly class BetaReaderGroupSummary
{
    public function __construct(
        public string $groupId,
        public string $name,
        public int $memberCount,
        public \DateTimeImmutable $createdAt,
    ) {
    }
}
