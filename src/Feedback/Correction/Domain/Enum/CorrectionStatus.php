<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Enum;

/**
 * A correction is a draft until it is delivered, and immutable afterwards
 * (`FEAT-FBK-011`).
 *
 * One table and one state field, not a draft table plus a real one. Sending
 * is a transition, not a copy — two tables that must stay in step always end
 * up out of step.
 *
 * Immutability after delivery is not tidiness: the author has already paid
 * for it.
 */
enum CorrectionStatus: string
{
    case DRAFT = 'DRAFT';
    case SUBMITTED = 'SUBMITTED';

    public function isEditable(): bool
    {
        return self::DRAFT === $this;
    }
}
