<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Command\RegisterUser;
use LectoresBeta\User\Account\Application\Port\PasswordHasher;
use LectoresBeta\User\Account\Application\Service\UsernameAllocator;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Event\UserRegistered;
use LectoresBeta\User\Account\Domain\Exception\TermsNotAccepted;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\Service\PasswordPolicy;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Legal\Domain\Entity\LegalAcceptance;
use LectoresBeta\User\Legal\Domain\Enum\LegalDocumentType;
use LectoresBeta\User\Legal\Domain\Repository\LegalAcceptanceRepository;
use LectoresBeta\User\Legal\Domain\ValueObject\LegalAcceptanceId;
use LectoresBeta\User\Privacy\Application\Service\StartPrivacySettings;

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
        private LegalAcceptanceRepository $acceptances,
        private StartPrivacySettings $privacySettings,
        private UsernameAllocator $usernames,
        private PasswordPolicy $passwordPolicy,
        private PasswordHasher $passwordHasher,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RegisterUser $command): void
    {
        $email = Email::fromString($command->email);

        $this->passwordPolicy->ensureAcceptable($command->plainPassword);

        $terms = self::requiredVersion($command->acceptedTermsVersion);
        $privacy = self::requiredVersion($command->acceptedPrivacyVersion);

        if ($this->users->emailIsTaken($email)) {
            return;
        }

        $now = $this->clock->now();
        $userId = UserId::generate();
        $username = $this->usernames->allocateFrom($email);

        $user = User::register(
            $userId,
            $email,
            $username,
            $this->passwordHasher->hash($command->plainPassword),
            $now,
        );

        $this->session->execute(function () use ($user, $userId, $now, $terms, $privacy, $command): void {
            $this->users->save($user);

            // En la misma transacción y no en respuesta a un evento: una
            // cuenta sin ajustes de privacidad, aunque fuese un segundo, es
            // una cuenta cuya privacidad alguien tiene que suponer
            // (`FEAT-USR-038` `RN-4`).
            $this->privacySettings->forAccount($userId, $now);

            foreach ([[LegalDocumentType::TERMS_OF_USE, $terms], [LegalDocumentType::PRIVACY_POLICY, $privacy]] as [$type, $version]) {
                $this->acceptances->save(new LegalAcceptance(
                    LegalAcceptanceId::generate(),
                    $userId,
                    $type,
                    $version,
                    $now,
                    $command->ipAddress,
                ));
            }
        });

        // Published after the transaction commits, never inside it: a fact
        // announced by a transaction that then rolls back is a fact that
        // never happened, and consumers cannot take it back.
        $this->events->publish(new UserRegistered(
            EventId::generate(),
            $userId,
            $email,
            $username,
            $now,
        ));
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
