<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Service;

use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Contract\ActivationLink;
use LectoresBeta\User\Account\Application\Contract\ActivationLinkProvider;
use LectoresBeta\User\Account\Domain\Entity\AccountActivationToken;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Repository\AccountActivationTokenRepository;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\AccountActivationTokenId;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * `User`'s side of the contract.
 *
 * Minting happens here and not at registration, and that is the point: a
 * token that were created with the account would start expiring while the
 * email sat in a retry queue.
 *
 * Every live token is invalidated first, so only the newest link works
 * (`FEAT-USR-021`, `FEAT-NOT-008` `RN-3`). Two live links would double the
 * window in which a leaked one still opens the account.
 */
final readonly class IssueActivationLink implements ActivationLinkProvider
{
    private const LIFETIME = 'P2D';

    public function __construct(
        private UserRepository $users,
        private AccountActivationTokenRepository $tokens,
        private SecureTokenFactory $secureTokens,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function issueFor(string $userId): ?ActivationLink
    {
        $user = $this->users->ofId(UserId::fromString($userId));

        if (null === $user || AccountStatus::PENDING_ACTIVATION !== $user->status()) {
            return null;
        }

        $now = $this->clock->now();
        $expiresAt = $now->add(new \DateInterval(self::LIFETIME));
        $secret = $this->secureTokens->create();

        $this->session->execute(function () use ($user, $secret, $now, $expiresAt): void {
            foreach ($this->tokens->liveTokensOf($user->id()) as $previous) {
                $previous->invalidate($now);
                $this->tokens->save($previous);
            }

            $this->tokens->save(new AccountActivationToken(
                AccountActivationTokenId::generate(),
                $user->id(),
                $secret->hash,
                $now,
                $expiresAt,
            ));
        });

        return new ActivationLink(
            $user->email()->value(),
            $user->username()->value(),
            $secret->plain,
            $expiresAt,
        );
    }
}
