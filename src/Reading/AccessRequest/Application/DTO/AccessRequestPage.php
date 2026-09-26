<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Application\DTO;

/**
 * A page of requests and where the next one starts.
 *
 * By cursor and not by page number, which is the convention
 * ([`paginación`](../../../../../docs/api/conventions/pagination.md)): this
 * is a chronological tray with things arriving at the top, not a catalogue
 * somebody jumps around in. There is no total either — nobody needs to know
 * they have 413 pending requests, they need the next twenty.
 */
final readonly class AccessRequestPage
{
    /**
     * @param list<AccessRequestView> $requests
     */
    public function __construct(
        public array $requests,
        public ?string $nextCursor,
    ) {
    }
}
