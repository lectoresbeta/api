<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Domain\Service;

use LectoresBeta\Feedback\Correction\Domain\Enum\WorkAccessMode;

/**
 * Who may correct a chapter (`FEAT-FBK-003`).
 *
 * Three conditions, and they fail for different reasons, so they are asked
 * separately rather than folded into one boolean: the caller has to answer
 * `403` to one of them and `409` to another.
 *
 * It takes plain values and not the answer of another context's contract:
 * Domain does not know that there is a contract, or an HTTP boundary, or a
 * `Work` at the other end of anything.
 */
final class CorrectionPolicy
{
    /**
     * An author correcting their own work would pay themselves, which is
     * both nonsense and a way to farm credits out of the difference.
     */
    public function isTheAuthor(string $readerId, string $authorId): bool
    {
        return $readerId === $authorId;
    }

    /**
     * Whether this reader may correct at all, given how open the work is and
     * whether they already hold access.
     */
    public function admits(WorkAccessMode $mode, bool $hasGrantedAccess): bool
    {
        return $hasGrantedAccess || $mode->grantsAccessOnStart();
    }
}
