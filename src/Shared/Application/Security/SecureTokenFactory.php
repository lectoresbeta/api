<?php

declare(strict_types=1);

namespace LectoresBeta\Shared\Application\Security;

/**
 * Creating and re-hashing single-use secrets: activation links, password
 * resets, email confirmations.
 *
 * A port because randomness and hashing are infrastructure, and because a use
 * case that called `random_bytes` directly could not be tested.
 */
interface SecureTokenFactory
{
    public function create(): SecureToken;

    /**
     * The hash of a token that arrived from outside, so it can be looked up.
     * Must agree with `create()` or nothing will ever match.
     */
    public function hashOf(string $plain): string;
}
