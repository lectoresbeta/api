<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Entity;

use LectoresBeta\Feedback\Correction\Domain\Enum\AssessmentOutcome;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionAssessmentId;
use LectoresBeta\Feedback\Correction\Domain\ValueObject\CorrectionId;

/**
 * What the fraud check made of a correction (`FEAT-FBK-012`).
 *
 * A **signal**, not a decision: whoever moves credits is still `Credits`, and
 * whoever sanctions is still `Moderation`.
 *
 * Append-only — a re-assessment is a new row, never an overwrite. The
 * mechanism and its version are stored so that a verdict reached by one
 * heuristic can be told apart from one reached by another, which is the first
 * thing anyone asks when the rules change.
 *
 * `FEAT-FBK-012` is the one feature still not `APPROVED` (`AF-1`, `AF-2`).
 * This table records its result; how the result is reached is not decided.
 */
class CorrectionAssessment
{
    private string $id;

    private string $correctionId;

    private string $mechanism;

    private string $mechanismVersion;

    private AssessmentOutcome $outcome;

    private ?string $reason = null;

    private \DateTimeImmutable $assessedAt;

    public function __construct(
        CorrectionAssessmentId $id,
        CorrectionId $correctionId,
        string $mechanism,
        string $mechanismVersion,
        AssessmentOutcome $outcome,
        \DateTimeImmutable $now,
        ?string $reason = null,
    ) {
        $this->id = $id->value();
        $this->correctionId = $correctionId->value();
        $this->mechanism = $mechanism;
        $this->mechanismVersion = $mechanismVersion;
        $this->outcome = $outcome;
        $this->assessedAt = $now;
        $this->reason = $reason;
    }

    public function id(): CorrectionAssessmentId
    {
        return CorrectionAssessmentId::fromString($this->id);
    }

    public function correctionId(): CorrectionId
    {
        return CorrectionId::fromString($this->correctionId);
    }

    public function outcome(): AssessmentOutcome
    {
        return $this->outcome;
    }

    public function assessedAt(): \DateTimeImmutable
    {
        return $this->assessedAt;
    }
}
