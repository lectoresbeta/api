<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\AccessInvitation\Domain\Enum;

/**
 * What the invitee answers (`FEAT-RDG-005`).
 *
 * `DECLINED` and not `REJECTED`, unlike a request: the words are not
 * interchangeable in the product's own vocabulary. An author **rejects** a
 * stranger's request; a reader **declines** an offer made to them.
 *
 * Cancelling is not here either. That is the author withdrawing the offer,
 * not an answer to it.
 */
enum InvitationDecision: string
{
    case ACCEPTED = 'ACCEPTED';
    case DECLINED = 'DECLINED';
}
