<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Infrastructure\Security;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Authentication\Application\Port\AccessTokenIssuer;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * The port, over LexikJWTAuthenticationBundle.
 *
 * It is handed an `AuthenticatedUser`, which holds nothing but the
 * identifier, so there is no personal data available to leak into the
 * payload even by accident (`decision:0007` `RN-4`). The roles Lexik adds by
 * default are stripped by `StripClaimsFromAccessToken`.
 */
final readonly class LexikAccessTokenIssuer implements AccessTokenIssuer
{
    public function __construct(
        private JWTTokenManagerInterface $tokens,
        private int $lifetimeInSeconds,
    ) {
    }

    public function issueFor(UserId $userId): string
    {
        return $this->tokens->create(new AuthenticatedUser($userId->value()));
    }

    public function lifetimeInSeconds(): int
    {
        return $this->lifetimeInSeconds;
    }
}
