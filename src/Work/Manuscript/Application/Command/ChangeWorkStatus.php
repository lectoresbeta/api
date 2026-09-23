<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Command;

/**
 * Moving a work through its life cycle (`FEAT-WRK-016`).
 *
 * One command for every transition, with the destination in it. **The server
 * decides which paths exist**: a client that knew the state machine would
 * have to be updated every time it changed, and the one that was not updated
 * would be the one making the illegal call.
 */
final readonly class ChangeWorkStatus
{
    public function __construct(
        public string $workId,
        public string $authorId,
        public string $status,
    ) {
    }
}
