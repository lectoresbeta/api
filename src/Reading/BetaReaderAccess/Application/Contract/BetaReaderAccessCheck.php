<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderAccess\Application\Contract;

/**
 * `Reading`'s published contract for the one question the rest of the system
 * asks about access
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * **A boolean, and nothing else.** Not who granted it, not when, not through
 * which invitation: whoever needs to decide whether somebody may correct a
 * chapter does not need any of that, and handing it over would make the
 * shape of an access record somebody else's business.
 *
 * It answers about **live** access only. A revoked one is indistinguishable
 * here from never having had it, which is what makes revoking effective
 * everywhere at once.
 */
interface BetaReaderAccessCheck
{
    public function hasAccessTo(string $workId, string $readerId): bool;
}
