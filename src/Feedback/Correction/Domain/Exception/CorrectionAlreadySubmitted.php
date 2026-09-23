<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Exception;

final class CorrectionAlreadySubmitted extends \DomainException
{
    public static function withId(string $correctionId): self
    {
        return new self(\sprintf('The correction %s has been delivered and cannot change.', $correctionId));
    }
}
