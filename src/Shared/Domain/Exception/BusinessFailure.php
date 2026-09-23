<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Exception;

/**
 * An expected failure of a business rule, as opposed to a bug.
 *
 * The point of the interface is that the HTTP boundary can answer *every*
 * business failure without knowing any of them. Without it, translating
 * exceptions to responses means a central `match` over class names that every
 * context has to remember to edit, and the day somebody forgets, a business
 * rule surfaces as a `500` with a stack trace.
 *
 * What an implementation promises:
 *
 * - `errorCode()` is **stable and machine-readable**. Clients branch on it,
 *   never on the message (`docs/api/conventions/errors.md`).
 * - The message is for people and may change.
 * - Neither may carry anything the caller should not see: no email addresses
 *   that would reveal who has an account, no tokens, no internal identifiers.
 */
interface BusinessFailure extends \Throwable
{
    /**
     * `SCREAMING_SNAKE_CASE`, unique across the platform.
     */
    public function errorCode(): string;

    public function kind(): FailureKind;
}
