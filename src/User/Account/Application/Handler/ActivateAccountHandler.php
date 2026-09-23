<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Command\ActivateAccount;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Event\AccountActivated;
use LectoresBeta\User\Account\Domain\Exception\ActivationTokenExpired;
use LectoresBeta\User\Account\Domain\Exception\InvalidActivationToken;
use LectoresBeta\User\Account\Domain\Repository\AccountActivationTokenRepository;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;

/**
 * Activating the account (`FEAT-USR-020`).
 *
 * Two behaviours are worth reading twice, because both look like bugs and
 * neither is:
 *
 * - **Activating an already active account succeeds** (`RN-3`). People click
 *   the link twice, and mail clients prefetch it. The second call changes
 *   nothing and publishes nothing — publishing again would be harmless thanks
 *   to deduplication, but only because the event id is reused, and relying on
 *   that would be relying on somebody else's safety net.
 * - **An unknown token and a used one are the same answer** (`RN-5`). Only
 *   expiry is told apart, because the user needs to be offered a new email.
 */
final readonly class ActivateAccountHandler
{
    public function __construct(
        private UserRepository $users,
        private AccountActivationTokenRepository $tokens,
        private SecureTokenFactory $secureTokens,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ActivateAccount $command): void
    {
        $now = $this->clock->now();
        $token = $this->tokens->ofTokenHash($this->secureTokens->hashOf($command->token));

        if (null === $token) {
            throw InvalidActivationToken::create();
        }

        $user = $this->users->ofId($token->userId());

        if (null === $user || AccountStatus::DELETED === $user->status()) {
            throw InvalidActivationToken::create();
        }

        if (AccountStatus::ACTIVE === $user->status()) {
            return;
        }

        if (!$token->isUsable($now)) {
            // Usable is three things at once. Only expiry may be revealed.
            throw $now >= $token->expiresAt() ? ActivationTokenExpired::create() : InvalidActivationToken::create();
        }

        $user->activate($now);
        $token->consume($now);

        $this->session->execute(function () use ($user, $token): void {
            $this->users->save($user);
            $this->tokens->save($token);
        });

        $this->events->publish(new AccountActivated(
            EventId::generate(),
            $user->id(),
            $now,
        ));
    }
}
