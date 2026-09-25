<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\User\Account\Application\Command\RegisterUser;
use LectoresBeta\User\Account\Application\Port\PasswordHasher;
use LectoresBeta\User\Account\Application\Service\OpenAccount;
use LectoresBeta\User\Account\Application\Service\UsernameAllocator;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Exception\TermsNotAccepted;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\Service\PasswordPolicy;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Legal\Application\Service\CheckLegalConsent;
use LectoresBeta\User\Legal\Domain\Enum\LegalDocumentType;

/**
 * Signing up (`FEAT-USR-001`).
 *
 * It does **not** mint the activation token. That happens when the email is
 * about to go out ([`IssueActivationLink`](../Service/IssueActivationLink.php)),
 * so the link does not start expiring while the message waits in a retry
 * queue.
 *
 * The shape of this handler is dictated by one rule, `RN-14`: **the response
 * may not reveal whether an email already has an account.** So the order of
 * the checks is not an accident.
 *
 * 1. Everything the caller can be told about — the password policy, the
 *    acceptance — is validated **first**, because a `422` there says nothing
 *    about who is registered: it describes what the caller just typed.
 * 2. Only then is the address looked at. If it is taken the handler **stops
 *    silently**: nothing is created, no email goes out, and the controller
 *    returns the same `202` as a successful sign-up.
 *
 * Reversing those two steps would leak the answer, because a taken address
 * with a weak password would fail differently from a free one.
 */
final readonly class RegisterUserHandler
{
    public function __construct(
        private UserRepository $users,
        private CheckLegalConsent $consent,
        private OpenAccount $accounts,
        private UsernameAllocator $usernames,
        private PasswordPolicy $passwordPolicy,
        private PasswordHasher $passwordHasher,
        private Clock $clock,
    ) {
    }

    public function __invoke(RegisterUser $command): void
    {
        $email = Email::fromString($command->email);

        $this->passwordPolicy->ensureAcceptable($command->plainPassword);

        // No basta con que venga una versión: tiene que ser **la vigente**
        // (`FEAT-USR-024` `RN-2`). Una aceptación de un texto que ya no rige
        // parece una prueba y no lo es.
        $accepted = $this->consent->accepting([
            LegalDocumentType::TERMS_OF_USE->value => self::requiredVersion($command->acceptedTermsVersion),
            LegalDocumentType::PRIVACY_POLICY->value => self::requiredVersion($command->acceptedPrivacyVersion),
        ]);

        if ($this->users->emailIsTaken($email)) {
            return;
        }

        $now = $this->clock->now();
        $username = $this->usernames->allocateFrom($email);

        $this->accounts->open(
            User::register(
                UserId::generate(),
                $email,
                $username,
                $this->passwordHasher->hash($command->plainPassword),
                $now,
            ),
            $accepted,
            $command->ipAddress,
            $now,
        );
    }

    private static function requiredVersion(?string $version): string
    {
        $version = null === $version ? '' : trim($version);

        if ('' === $version) {
            throw TermsNotAccepted::create();
        }

        return $version;
    }
}
