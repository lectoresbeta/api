<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * `User`'s published contract for the one question another context needs to
 * ask about somebody it did not find itself
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * **A boolean, and nothing else.** Not a name, not a profile, not whether the
 * account is activated: what the caller is deciding is whether an identifier
 * refers to a real person, and any extra field would be somebody's data
 * crossing a border for no reason.
 *
 * It exists for `FEAT-RDG-004`: inviting an invented identifier would create
 * an invitation nobody can accept and a notice that cannot be delivered.
 *
 * A deleted account answers `false`, which is the right answer — the
 * invitation would go nowhere.
 */
interface RegisteredUsers
{
    public function exists(string $userId): bool;
}
