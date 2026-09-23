<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * `User`'s published contract for the one age question other contexts need
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * It answers **a boolean**, not a date and not an age. `Work` has to decide
 * whether to serve an `ADULTS_ONLY` text; it does not need to know when
 * anybody was born, and the date of birth is private data that must not leave
 * this context (`FEAT-USR-022` `RN-4b`). Handing over the exact question and
 * nothing more is what keeps that true.
 *
 * It cannot travel in the access token either: the token carries no personal
 * data (`decision:0007` `RN-4`).
 *
 * **Somebody who has not declared a date of birth is not of age.** The
 * alternative — treating the undeclared as adults — would turn an optional
 * onboarding step into a way around the filter.
 */
interface ReaderMaturity
{
    public function isOfAge(string $userId): bool;
}
