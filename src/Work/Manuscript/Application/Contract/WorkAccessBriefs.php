<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Application\Contract;

/**
 * `Work`'s published contract for the context that decides who gets in
 * ([`decision:0015`](../../../../../docs/decisions/0015-work-and-reading-ask-each-other.md)).
 *
 * **It asks, it never commands.** Nothing here grants access, opens a work or
 * changes a mode.
 *
 * It is the sibling of `CorrectionBriefs`, which answers the same shape of
 * question about one chapter. This one answers about the work, because
 * requesting access and being invited are decisions about a whole work.
 *
 * Why synchronous and not a projection fed by `WorkAccessModeChanged`: the
 * reason is authorisation, not freshness. Refusing a request on a stranger's
 * draft is the same rule that makes `GET /works/{id}` answer `404`, and
 * rebuilding it inside another context would leave the most dangerous rule in
 * the backend written twice.
 *
 * **This contract completes the first cycle between contexts** — `Work`
 * already asks `Reading` whether somebody is a beta reader. What keeps that a
 * cycle of references rather than of calls is that neither implementation
 * calls the other while answering, and this one does not.
 */
interface WorkAccessBriefs
{
    public function ofWork(string $workId): ?WorkAccessBrief;

    /**
     * The same answer for several works at once, keyed by work identifier.
     *
     * It exists for the lists: a page of twenty requests would otherwise be
     * twenty calls, and a contract that invites an N+1 is a contract that
     * will get one.
     *
     * Works that do not exist are simply absent from the result.
     *
     * @param list<string> $workIds
     *
     * @return array<string, WorkAccessBrief>
     */
    public function ofWorks(array $workIds): array;
}
