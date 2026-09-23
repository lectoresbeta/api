<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Service;

use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Authentication\Application\DTO\Session;
use LectoresBeta\User\Authentication\Application\Port\AccessTokenIssuer;
use LectoresBeta\User\Authentication\Domain\Entity\RefreshToken;
use LectoresBeta\User\Authentication\Domain\Repository\RefreshTokenRepository;
use LectoresBeta\User\Authentication\Domain\ValueObject\RefreshTokenId;

/**
 * Issuing a session: the access token plus a fresh refresh token.
 *
 * Shared by logging in and by renewing, so that a renewed session is built
 * exactly like a new one. Two code paths would drift, and the one that drifts
 * is always the one nobody exercises by hand.
 *
 * It does not flush: the caller owns the transaction, because renewing has to
 * revoke the old token and store the new one together or not at all.
 */
final readonly class OpenSession
{
    public const LIFETIME = 'P30D';

    public function __construct(
        private RefreshTokenRepository $refreshTokens,
        private AccessTokenIssuer $accessTokens,
        private SecureTokenFactory $secureTokens,
        private Clock $clock,
    ) {
    }

    public function forUser(UserId $userId, ?string $userAgent = null): Session
    {
        $now = $this->clock->now();
        $secret = $this->secureTokens->create();

        $this->refreshTokens->save(new RefreshToken(
            RefreshTokenId::generate(),
            $userId,
            $secret->hash,
            $now,
            $now->add(new \DateInterval(self::LIFETIME)),
            $userAgent,
        ));

        return new Session(
            $this->accessTokens->issueFor($userId),
            $secret->plain,
            $this->accessTokens->lifetimeInSeconds(),
        );
    }
}
