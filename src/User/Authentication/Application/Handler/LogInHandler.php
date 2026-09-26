<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Handler;

use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Port\PasswordHasher;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\HashedPassword;
use LectoresBeta\User\Authentication\Application\Command\LogIn;
use LectoresBeta\User\Authentication\Application\DTO\Session;
use LectoresBeta\User\Authentication\Application\Service\OpenSession;
use LectoresBeta\User\Authentication\Domain\Exception\AccountBlocked;
use LectoresBeta\User\Authentication\Domain\Exception\InvalidCredentials;

/**
 * Logging in (`FEAT-USR-004`).
 *
 * Four situations give the identical answer — wrong password, no account, an
 * account created with Google and therefore without one, and a deleted
 * account — because any difference between them turns this endpoint into a
 * way of finding out who is on the platform (`RN-2`).
 *
 * That is why the missing account is not a shortcut: the handler verifies a
 * **decoy hash** before refusing (`RN-3`). Without it the two cases take
 * visibly different amounts of time, and the careful wording of the error
 * protects nothing at all.
 */
final class LogInHandler
{
    /**
     * A real hash of a value nobody knows, so that verifying it costs what
     * verifying a genuine one costs.
     *
     * Built on first use and not in the constructor: hashing is deliberately
     * slow, and this service is instantiated by requests that never log
     * anybody in. Generated rather than hard-coded, so that no fixed string
     * in the source can ever be mistaken for a usable credential.
     *
     * The class is therefore not `readonly`. It is the only mutable state,
     * and it is a cache.
     */
    private ?HashedPassword $decoy = null;

    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $passwords,
        private readonly OpenSession $sessions,
        private readonly TransactionalSession $transaction,
    ) {
    }

    public function __invoke(LogIn $command): Session
    {
        $user = $this->findBy($command->email);

        if (!$this->passwordMatches($user, $command->plainPassword)) {
            throw InvalidCredentials::create();
        }

        // `$user` is non-null here: a null one cannot match.
        if (AccountStatus::BLOCKED === $user?->status()) {
            throw AccountBlocked::create();
        }

        if (null === $user) {
            throw InvalidCredentials::create();
        }

        return $this->transaction->execute(
            fn (): Session => $this->sessions->forUser($user->id(), $command->userAgent),
        );
    }

    private function findBy(string $email): ?User
    {
        try {
            $user = $this->users->ofEmail(Email::fromString($email));
        } catch (InvalidValue) {
            // A malformed address is not a validation error here: it is one
            // more way of not matching any account, and saying otherwise
            // would tell an attacker which of their guesses were even worth
            // making.
            return null;
        }

        return null !== $user && AccountStatus::DELETED === $user->status() ? null : $user;
    }

    private function passwordMatches(?User $user, string $plain): bool
    {
        $stored = $user?->passwordHash();

        if (null === $stored) {
            // No account, or one with no password at all. Spend the same time
            // anyway (`RN-3`).
            $this->passwords->matches($plain, $this->decoy());

            return false;
        }

        return $this->passwords->matches($plain, $stored);
    }

    private function decoy(): HashedPassword
    {
        return $this->decoy ??= $this->passwords->hash(bin2hex(random_bytes(16)));
    }
}
