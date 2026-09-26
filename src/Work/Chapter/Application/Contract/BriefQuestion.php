<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Chapter\Application\Contract;

/**
 * One question the author asks about this chapter.
 *
 * Plain data, no entity: whoever receives an aggregate ends up navigating it
 * and the internal model is shared again
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 */
final readonly class BriefQuestion
{
    public function __construct(
        public string $questionId,
        public int $position,
        public string $statement,
        public ?string $example,
        public bool $required,
        /** Words. Zero means the author declared no minimum. */
        public int $minWords,
        public ?int $maxWords,
    ) {
    }
}
