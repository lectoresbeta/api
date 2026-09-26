<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Application\DTO;

/**
 * Un grupo abierto, con quién hay dentro (`FEAT-RDG-007`).
 */
final readonly class BetaReaderGroupDetail
{
    /**
     * @param list<GroupMemberCard> $members
     */
    public function __construct(
        public string $groupId,
        public string $name,
        public \DateTimeImmutable $createdAt,
        public array $members,
    ) {
    }
}
