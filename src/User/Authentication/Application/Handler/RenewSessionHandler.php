<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Handler;

use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Authentication\Application\Command\RenewSession;
use LectoresBeta\User\Authentication\Application\DTO\Session;
use LectoresBeta\User\Authentication\Application\Service\OpenSession;
use LectoresBeta\User\Authentication\Domain\Exception\AccountBlocked;
use LectoresBeta\User\Authentication\Domain\Exception\InvalidRefreshToken;
use LectoresBeta\User\Authentication\Domain\Repository\RefreshTokenRepository;
use Psr\Log\LoggerInterface;

/**
 * Renewing the session (`FEAT-USR-004` `RN-10`, `RN-11`).
 *
 * The refresh token **rotates**: the one presented is revoked and a new one
 * issued, so each serves exactly once.
 *
 * Which makes one particular case unambiguous. **A token that was already
 * revoked, presented again, means two parties hold the same token** — there
 * is no innocent reading. One of them is a thief and there is no way to tell
 * which, so every session of that user is revoked. It is annoying for the
 * legitimate person, who has to sign in again, and it is the only action that
 * removes the thief without knowing who they are.
 *
 * The caller is told nothing about it: whoever is asking may be the thief.
 */
final readonly class RenewSessionHandler
{
    public function __construct(
        private RefreshTokenRepository $refreshTokens,
        private UserRepository $users,
        private OpenSession $sessions,
        private SecureTokenFactory $secureTokens,
        private TransactionalSession $transaction,
        private Clock $clock,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RenewSession $command): Session
    {
        $now = $this->clock->now();
        $presented = $this->refreshTokens->ofTokenHash($this->secureTokens->hashOf($command->refreshToken));

        if (null === $presented) {
            throw InvalidRefreshToken::create();
        }

        if (!$presented->isUsable($now)) {
            $this->onReuse($presented->userId(), $now);

            throw InvalidRefreshToken::create();
        }

        $user = $this->users->ofId($presented->userId());

        if (null === $user || AccountStatus::DELETED === $user->status()) {
            throw InvalidRefreshToken::create();
        }

        if (AccountStatus::BLOCKED === $user->status()) {
            // Renewing is where a block bites within the fifteen-minute
            // window of `decision:0007`: the access token still works, but
            // the session stops being able to outlive it.
            $this->refreshTokens->revokeAllOf($user->id(), $now);

            throw AccountBlocked::create();
        }

        return $this->transaction->execute(function () use ($presented, $user, $command, $now): Session {
            $presented->revoke($now);
            $presented->markUsed($now);
            $this->refreshTokens->save($presented);

            return $this->sessions->forUser($user->id(), $command->userAgent);
        });
    }

    private function onReuse(UserId $userId, \DateTimeImmutable $now): void
    {
        $revoked = $this->refreshTokens->revokeAllOf($userId, $now);

        if ($revoked > 0) {
            // Worth an alert, not just a line: it is the only signal the
            // system has that a refresh token may have been stolen.
            $this->logger->warning('A revoked refresh token was presented again; every session of the user was revoked.', [
                'userId' => $userId->value(),
                'revokedSessions' => $revoked,
            ]);
        }
    }
}
