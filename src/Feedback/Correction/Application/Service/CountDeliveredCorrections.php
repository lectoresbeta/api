<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Application\Service;

use LectoresBeta\Feedback\Correction\Application\Contract\DeliveredCorrectionCount;
use LectoresBeta\Feedback\Correction\Domain\Repository\CorrectionRepository;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\ReaderId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;

final readonly class CountDeliveredCorrections implements DeliveredCorrectionCount
{
    public function __construct(private CorrectionRepository $corrections)
    {
    }

    public function ofReader(string $readerId): int
    {
        try {
            return $this->corrections->deliveredCountBy(ReaderId::fromString($readerId));
        } catch (InvalidValue) {
            return 0;
        }
    }
}
