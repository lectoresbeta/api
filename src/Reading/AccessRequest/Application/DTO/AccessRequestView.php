<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Application\DTO;

/**
 * One row of a request list (`FEAT-RDG-002`, `FEAT-RDG-003`).
 *
 * The same shape serves both sides, and that is not laziness: a request is
 * one object seen from two ends. What differs is which field the reader of
 * the list cares about — the author looks at `readerId` and `message`, the
 * requester at `workTitle` and `status`.
 *
 * `workTitle` may be `null` when the work is gone and `WorkDeleted` has not
 * been delivered yet. A row without a title is better than a list that
 * fails.
 */
final readonly class AccessRequestView
{
    public function __construct(
        public string $requestId,
        public string $workId,
        public ?string $workTitle,
        public string $readerId,
        public string $status,
        public ?string $message,
        public \DateTimeImmutable $requestedAt,
        public ?\DateTimeImmutable $resolvedAt,
    ) {
    }
}
