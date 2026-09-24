<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * Finding a person by what you can see of them
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * **The index of people belongs here**, to the context that owns profiles,
 * usernames and names. `Reading` needs it so an author can pick somebody to
 * invite (`FEAT-RDG-006`), and building a second index over the same people
 * inside that context is precisely what `AGENTS.md` forbids. When
 * `FEAT-USR-017` arrives it will expose **this same service** through its own
 * endpoint.
 *
 * Three rules travel with the implementation and not with the caller,
 * deliberately — a caller that had to remember them is a caller that one day
 * will not:
 *
 * - **never by email.** A search that accepts an address answers a different
 *   question — «does this person have an account here?» — and turns the form
 *   into an address checker. Registration already refuses to reveal that
 *   (`FEAT-USR-001` `RN-14`);
 * - **only active accounts.** A deleted one is anonymised and has nothing
 *   left to match;
 * - **the profile privacy ceiling applies.** Whoever sets it to `NOBODY`
 *   disappears from here (`FEAT-USR-038`).
 */
interface ReaderDirectory
{
    /**
     * @param int<1, 100> $limit
     *
     * @return list<DirectoryEntry>
     */
    public function search(string $query, int $limit): array;
}
