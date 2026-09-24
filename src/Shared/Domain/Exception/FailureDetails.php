<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Exception;

/**
 * A failure that carries something the caller needs beyond the code.
 *
 * RFC 9457 allows extension members, and this is what fills them. It exists
 * for the cases where a code alone leaves the client guessing: «too soon» is
 * useless without **when**, and parsing a date out of a sentence written for
 * people is the kind of thing that works until the sentence is translated.
 *
 * Keep it to what the caller must act on. It is a response body, so the same
 * rule as everywhere applies: nothing internal, no identifiers of things they
 * cannot see, no addresses that would reveal who has an account.
 */
interface FailureDetails
{
    /**
     * @return array<string, scalar>
     */
    public function failureDetails(): array;
}
