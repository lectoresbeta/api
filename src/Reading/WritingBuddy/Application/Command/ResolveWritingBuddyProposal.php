<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Application\Command;

final readonly class ResolveWritingBuddyProposal
{
    public function __construct(
        public string $readerId,
        public string $linkId,
        public ?string $decision,
    ) {
    }
}
