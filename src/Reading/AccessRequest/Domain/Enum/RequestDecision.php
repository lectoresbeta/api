<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessRequest\Domain\Enum;

/**
 * What the author answers to a request (`FEAT-RDG-003`).
 *
 * Two values and not two endpoints: it is **one** state transition, like a
 * work's status. Two routes for the same change mean the same checks written
 * twice, and two places to forget one of them.
 *
 * Cancelling is not here. That is the reader withdrawing their own question,
 * not an answer to it.
 */
enum RequestDecision: string
{
    case ACCEPTED = 'ACCEPTED';
    case REJECTED = 'REJECTED';
}
