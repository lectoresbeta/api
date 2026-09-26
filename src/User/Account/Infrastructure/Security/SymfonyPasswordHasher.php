<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Infrastructure\Security;

use LectoresBeta\User\Account\Application\Port\PasswordHasher;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\ValueObject\HashedPassword;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;

/**
 * The port, over Symfony's hasher factory.
 *
 * Going through the factory rather than hashing here keeps the algorithm and
 * its cost in `security.yaml`, which is where an operator expects to find
 * them and where the test environment can make them cheap. The factory is
 * keyed by the domain class: naming it in configuration costs nothing, and
 * the alternative — `password_hash()` inline — would put a second, silently
 * diverging policy next to the configured one.
 */
final readonly class SymfonyPasswordHasher implements PasswordHasher
{
    public function __construct(private PasswordHasherFactoryInterface $hashers)
    {
    }

    public function hash(string $plain): HashedPassword
    {
        return HashedPassword::fromHash($this->hashers->getPasswordHasher(User::class)->hash($plain));
    }

    public function matches(string $plain, HashedPassword $hashed): bool
    {
        return $this->hashers->getPasswordHasher(User::class)->verify($hashed->value(), $plain);
    }
}
