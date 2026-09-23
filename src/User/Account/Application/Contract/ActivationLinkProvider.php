<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Contract;

/**
 * `User`'s published contract for issuing an activation link
 * ([`decision:0014`](../../../../../docs/decisions/0014-published-contracts-between-contexts.md)).
 *
 * It exists because the activation token cannot travel on the queue — it is a
 * live credential, and `IntegrationEvent` forbids carrying private content —
 * while the email that delivers it belongs to `Notification`, which owns
 * templates, deliverability and the mail provider.
 *
 * So the fact travels asynchronously and **the secret is fetched
 * synchronously, at the moment of sending**. That has a second benefit worth
 * naming: the link starts living when the email goes out, so a send that only
 * succeeds after hours of retries still carries a usable link.
 *
 * Note the shape: it **asks**, it does not command. A contract that let
 * another context change something here would be a remote method call with
 * extra steps.
 */
interface ActivationLinkProvider
{
    /**
     * Issues a fresh link, invalidating any previous one so that **only the
     * latest email works** (`FEAT-NOT-008` `RN-3`).
     *
     * Returns `null` when there is nothing to send: no such account, or one
     * that is already active. The caller must treat that as «done», not as an
     * error — it is the normal outcome of a redelivered event.
     */
    public function issueFor(string $userId): ?ActivationLink;
}
