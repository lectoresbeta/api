<?php

declare(strict_types=1);

namespace LectoresBeta\User\Privacy\Application\Contract;

/**
 * `User`'s published contract for the privacy ceiling
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * **A boolean, never the setting.** What another context has to decide is
 * whether this person may comment on that author's texts; handing over
 * `FOLLOWERS` would make everyone who asks learn what «followers» means here
 * and go and work it out, and then the rule would live in four places.
 *
 * It exists because the profile setting is a **ceiling** over each work's
 * access mode (`FEAT-USR-038` `RN-2`, `S-14`): the profile sets the maximum
 * and a work may lower it, never raise it. Without this, hardening the
 * setting would close a door that stays open on every `PUBLIC` work.
 *
 * The answer is about **what happens from now on**. It does not rewrite the
 * past (`RN-3`), and whoever already started correcting finishes and gets
 * paid (`S-36`).
 */
interface AuthorAudience
{
    public function acceptsCommentsFrom(string $authorId, string $readerId): bool;
}
