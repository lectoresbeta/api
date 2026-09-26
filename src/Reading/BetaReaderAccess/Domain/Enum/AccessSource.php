<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Domain\Enum;

/**
 * How somebody came to be a beta reader.
 *
 * The three routes produce the same object: the work's access mode decides
 * the path, not the result. Keeping the source is worth it anyway — it is
 * what lets anyone answer, months later, why this person has access.
 */
enum AccessSource: string
{
    case PUBLIC_JOIN = 'PUBLIC_JOIN';
    case REQUEST_APPROVED = 'REQUEST_APPROVED';
    case AUTHOR_INVITATION = 'AUTHOR_INVITATION';
}
