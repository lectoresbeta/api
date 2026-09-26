<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Infrastructure\Security;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

/**
 * Keeps the access token down to what `decision:0007` `RN-4` allows.
 *
 * Lexik writes `roles` into the payload by default, and that is precisely the
 * mistake the ADR calls out: **a role inside a token survives its own
 * revocation for up to fifteen minutes.** Permissions are looked up, never
 * carried.
 *
 * It also adds `jti`, which the ADR requires and Lexik does not produce: a
 * token needs its own identity to be traceable in a log without the log
 * holding the token.
 */
#[AsEventListener(event: 'lexik_jwt_authentication.on_jwt_created')]
final readonly class StripClaimsFromAccessToken
{
    /**
     * Everything not in this list is removed. An allow-list and not a
     * deny-list on purpose: a future bundle version that starts adding a
     * claim would otherwise ship it to production unnoticed.
     */
    private const ALLOWED = ['sub', 'iat', 'exp', 'jti'];

    public function __invoke(JWTCreatedEvent $event): void
    {
        $payload = $event->getData();

        // Lexik identifies the subject with `username`; the standard claim is
        // `sub`, and it is what the provider reads back.
        $payload['sub'] ??= $payload['username'] ?? null;
        $payload['jti'] = bin2hex(random_bytes(16));

        $event->setData(array_filter(
            array_intersect_key($payload, array_flip(self::ALLOWED)),
            static fn (mixed $value): bool => null !== $value,
        ));
    }
}
