<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Service;

use LectoresBeta\Reading\BetaReaderAccess\Application\Contract\BetaReaderAccessCheck;
use LectoresBeta\Reading\BetaReaderAccess\Domain\Repository\BetaReaderAccessRepository;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\ReaderId;
use LectoresBeta\Reading\BetaReaderAccess\Domain\ValueObject\WorkId;

final readonly class CheckBetaReaderAccess implements BetaReaderAccessCheck
{
    public function __construct(private BetaReaderAccessRepository $accesses)
    {
    }

    public function hasAccessTo(string $workId, string $readerId): bool
    {
        return null !== $this->accesses->liveFor(
            ReaderId::fromString($readerId),
            WorkId::fromString($workId),
        );
    }
}
