<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Handler;

use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Authentication\Application\Command\LogOut;
use LectoresBeta\User\Authentication\Domain\Repository\RefreshTokenRepository;

/**
 * Logging out (`FEAT-USR-004` `RN-12`).
 *
 * Revoking the refresh token is all that can be done: **the access token
 * keeps working for up to fifteen minutes**, because a JWT cannot be taken
 * back (`decision:0007`). That window is the accepted cost of the decision
 * and has to be described honestly wherever the product says «se cierra la
 * sesión».
 *
 * It is idempotent and it never fails. A token that does not exist, or that
 * was already revoked, still leaves the caller logged out, which is what they
 * asked for — and refusing would tell whoever asked whether that token was
 * real.
 */
final readonly class LogOutHandler
{
    public function __construct(
        private RefreshTokenRepository $refreshTokens,
        private SecureTokenFactory $secureTokens,
        private TransactionalSession $transaction,
        private Clock $clock,
    ) {
    }

    public function __invoke(LogOut $command): void
    {
        $token = $this->refreshTokens->ofTokenHash($this->secureTokens->hashOf($command->refreshToken));

        if (null === $token) {
            return;
        }

        $now = $this->clock->now();

        $this->transaction->execute(function () use ($token, $now): void {
            $token->revoke($now);
            $this->refreshTokens->save($token);
        });
    }
}
