<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Port;

use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Minting the access token, as a port.
 *
 * JWT is a decision of `decision:0007` and could be replaced by an opaque
 * token without the domain noticing — which is exactly what that ADR asks
 * for: «conviene que la verificación viva tras un servicio propio desde el
 * principio, para que sustituirla sea cambiar una implementación».
 *
 * The signature takes a `UserId` and nothing else, and that is the contract
 * that keeps `RN-4` true: there is no way to put a role or a personal detail
 * in the token, because the issuer is never given one.
 */
interface AccessTokenIssuer
{
    public function issueFor(UserId $userId): string;

    /**
     * Lifetime in seconds, so the client knows when to renew without parsing
     * the token.
     */
    public function lifetimeInSeconds(): int;
}
