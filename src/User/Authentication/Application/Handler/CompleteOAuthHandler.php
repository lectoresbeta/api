<?php

declare(strict_types=1);

namespace LectoresBeta\User\Authentication\Application\Handler;

use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Service\OpenAccount;
use LectoresBeta\User\Account\Application\Service\UsernameAllocator;
use LectoresBeta\User\Account\Domain\Entity\User;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Enum\AuthProvider;
use LectoresBeta\User\Account\Domain\Exception\TermsNotAccepted;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Authentication\Application\Command\CompleteOAuth;
use LectoresBeta\User\Authentication\Application\DTO\ExternalIdentity;
use LectoresBeta\User\Authentication\Application\DTO\OAuthOutcome;
use LectoresBeta\User\Authentication\Application\DTO\Session;
use LectoresBeta\User\Authentication\Application\Service\OAuthProviders;
use LectoresBeta\User\Authentication\Application\Service\OpenSession;
use LectoresBeta\User\Authentication\Domain\Exception\AccountBlocked;
use LectoresBeta\User\Authentication\Domain\Exception\ExternalSignInFailed;
use LectoresBeta\User\Legal\Application\Service\CheckLegalConsent;
use LectoresBeta\User\Legal\Domain\Enum\LegalDocumentType;

/**
 * Volver de Google (`FEAT-USR-002`).
 *
 * **Google acredita quién es alguien, no qué ha aceptado** (`RN-3`). Es la
 * razón de fondo de toda la ficha y lo que es fácil dar por hecho: «entrar
 * con Google» no sustituye al registro. Google dice que esa persona controla
 * ese correo; no dice que haya leído nada. Por eso crear una cuenta exige la
 * aceptación legal igual que el alta con correo, y por eso **iniciar sesión
 * no la vuelve a pedir** (`RN-4`): se acepta una vez, al crear la cuenta.
 *
 * Tres caminos, y el orden en que se prueban importa:
 *
 * 1. **ya hay cuenta con esa identidad de Google** → sesión, y nada más. Es
 *    lo que hace que registrarse dos veces con la misma cuenta de Google no
 *    cree dos cuentas;
 * 2. **el correo ya tiene cuenta aquí** → se enlaza, **solo si Google afirma
 *    que ese correo está verificado** (`RN-6`). Sin esa afirmación, «tengo
 *    una cuenta con tu dirección» no demuestra nada, y enlazar sería
 *    entregarle una cuenta ajena a quien supiera el correo de su dueño;
 * 3. **nadie** → se crea, con aceptación legal o no se crea.
 *
 * La cuenta nueva nace `ACTIVE` (`RN-8`, `OB-11`): Google ya ha comprobado
 * que esa dirección es de quien la usa, y mandar un correo de activación
 * sería pedirle a alguien que demuestre algo ya demostrado, perdiendo gente
 * en un paso que no añade seguridad.
 */
final readonly class CompleteOAuthHandler
{
    public function __construct(
        private OAuthProviders $providers,
        private UserRepository $users,
        private CheckLegalConsent $consent,
        private OpenAccount $accounts,
        private UsernameAllocator $usernames,
        private OpenSession $sessions,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(CompleteOAuth $command): OAuthOutcome
    {
        $provider = $this->providers->named($command->provider);
        $identity = $provider->identify($command->code, $command->redirectUri);
        $kind = $provider->handles();

        if (null === $identity->email || '' === $identity->email) {
            // El correo es la identidad de la cuenta aquí, y todo lo que se
            // le manda a alguien va ahí. Sin él no hay cuenta que crear.
            throw ExternalSignInFailed::withoutEmail();
        }

        $email = Email::fromString($identity->email);
        $known = $this->users->ofExternalIdentity($kind, $identity->externalId);

        if (null !== $known) {
            return new OAuthOutcome($this->logIn($known, $command->userAgent), false, false);
        }

        $existing = $this->users->ofEmail($email);

        if (null !== $existing) {
            return new OAuthOutcome(
                $this->link($existing, $kind, $identity, $command->userAgent),
                false,
                true,
            );
        }

        return new OAuthOutcome($this->create($email, $identity, $kind, $command), true, false);
    }

    private function logIn(User $user, ?string $userAgent): Session
    {
        if (!$user->status()->canAuthenticate()) {
            // Expulsada, suspendida o eliminada. Entrar por otra puerta no
            // es una forma de saltarse la sanción.
            throw AccountBlocked::create();
        }

        return $this->session->execute(
            fn (): Session => $this->sessions->forUser($user->id(), $userAgent),
        );
    }

    private function link(
        User $user,
        AuthProvider $kind,
        ExternalIdentity $identity,
        ?string $userAgent,
    ): Session {
        if (!$identity->emailIsVerified) {
            throw ExternalSignInFailed::emailBelongsToAnotherAccount();
        }

        if (AccountStatus::DELETED === $user->status()) {
            // Una cuenta eliminada está anonimizada: no queda a quién
            // enlazar, y su correo ya no es suyo.
            throw ExternalSignInFailed::emailBelongsToAnotherAccount();
        }

        // Antes de escribir nada: una cuenta expulsada no estrena forma de
        // entrar. Enlazar primero y refusar después dejaría hecho el enlace
        // de alguien a quien se le acaba de negar la puerta.
        if (!$user->status()->canAuthenticate()) {
            throw AccountBlocked::create();
        }

        $user->linkExternalIdentity($kind, $identity->externalId, $this->clock->now());

        return $this->session->execute(function () use ($user, $userAgent): Session {
            $this->users->save($user);

            return $this->sessions->forUser($user->id(), $userAgent);
        });
    }

    private function create(
        Email $email,
        ExternalIdentity $identity,
        AuthProvider $kind,
        CompleteOAuth $command,
    ): Session {
        // Antes de crear nada: sin aceptación no hay cuenta (`RN-1`), venga
        // quien venga a crearla.
        $accepted = $this->consent->accepting([
            LegalDocumentType::TERMS_OF_USE->value => self::requiredVersion($command->acceptedTermsVersion),
            LegalDocumentType::PRIVACY_POLICY->value => self::requiredVersion($command->acceptedPrivacyVersion),
        ]);

        $now = $this->clock->now();

        $user = User::registerWithGoogle(
            UserId::generate(),
            $email,
            $this->usernames->allocateFrom($email),
            $identity->externalId,
            $now,
        );

        $this->accounts->open($user, $accepted, $command->ipAddress, $now);

        return $this->session->execute(
            fn (): Session => $this->sessions->forUser($user->id(), $command->userAgent),
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
