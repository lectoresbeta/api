<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Domain\Exception;

/**
 * What kind of thing went wrong, in terms the domain can express.
 *
 * It is **not** an HTTP status. «The thing you asked for is not there» and
 * «you are not allowed» are different failures whatever the transport, and a
 * command-line entry point would have to tell them apart just the same. What
 * Infrastructure adds is the translation to `404` and `403`; the distinction
 * itself belongs here.
 *
 * Keeping the list short is deliberate. A category per use case would just be
 * the error code again, and the mapping would stop being a mapping.
 */
enum FailureKind
{
    /** The input cannot be accepted. */
    case INVALID;

    /**
     * We do not know who is asking, or no longer do.
     *
     * Distinct from `FORBIDDEN`: there the caller is identified and the
     * answer is no, here the answer is «say who you are». Collapsing the two
     * is what produces a client that logs somebody out when what it needed
     * was to tell them they lack permission.
     */
    case UNAUTHENTICATED;

    /** It does not exist, or must not be revealed to exist. */
    case NOT_FOUND;

    /** It exists, and the current state does not allow this. */
    case CONFLICT;

    /** Identified, and not allowed. */
    case FORBIDDEN;

    /** It existed and is gone for good. */
    case GONE;
}
