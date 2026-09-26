<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Application\DTO;

/**
 * One row of an invitation list (`FEAT-RDG-004`, `FEAT-RDG-005`).
 *
 * It carries more of the work than a request does, and the reason is the
 * screen it serves: **accepting an invitation is the only moment in the
 * product where somebody agrees to read something they could not look at
 * first**. The work may be private, or still a draft. So the synopsis and the
 * content rating travel with the offer, which is where they are useful —
 * after accepting, they would be an explanation of a decision already taken.
 */
final readonly class InvitationView
{
    /**
     * @param list<string> $contentWarnings
     */
    public function __construct(
        public string $invitationId,
        public string $workId,
        public ?string $workTitle,
        public ?string $workSynopsis,
        public bool $adultsOnly,
        public array $contentWarnings,
        public string $authorId,
        public string $readerId,
        public string $status,
        public ?string $message,
        public \DateTimeImmutable $invitedAt,
        public ?\DateTimeImmutable $resolvedAt,
    ) {
    }
}
