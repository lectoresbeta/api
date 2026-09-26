<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Command\ChangeMyPassword;
use LectoresBeta\User\Account\Application\Port\PasswordHasher;
use LectoresBeta\User\Account\Domain\Event\PasswordChanged;
use LectoresBeta\User\Account\Domain\Exception\CurrentPasswordRequired;
use LectoresBeta\User\Account\Domain\Exception\IncorrectPassword;
use LectoresBeta\User\Account\Domain\Exception\PasswordUnchanged;
use LectoresBeta\User\Account\Domain\Exception\UserNotFound;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\Service\PasswordPolicy;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;
use LectoresBeta\User\Authentication\Domain\Repository\RefreshTokenRepository;

/**
 * Cambiar la contraseña desde dentro (`FEAT-USR-041`).
 *
 * Es la pareja de [`FEAT-USR-007`](../../../../../docs/features/user/FEAT-USR-007-recover-password.md):
 * aquella la cambia desde fuera demostrando quién eres con el buzón, y esta
 * desde dentro demostrándolo con la contraseña actual. Acaban en el mismo
 * sitio y publican el mismo hecho.
 *
 * **La actual no es una formalidad** (`RN-1`). Una sesión abierta no basta,
 * porque cambiar la contraseña echa a todas las demás sesiones: sin ese campo,
 * quien se siente ante un portátil desbloqueado se queda con la cuenta.
 *
 * ## El único caso en que puede faltar
 *
 * Quien se dio de alta con Google no tiene contraseña que aportar, así que el
 * mismo formulario **la establece** con el campo vacío (`S-8`). En cuanto la
 * tiene, vuelve a ser obligatorio: este camino no sirve para saltarse `RN-1`.
 *
 * Y conviene decir lo que eso cuesta, porque es real: en una cuenta de Google,
 * cualquiera con la sesión abierta puede ponerle contraseña sin demostrar
 * nada y entrar después sin pasar por Google. Las dos defensas que quedan son
 * el aviso por correo y el cierre de sesiones, y por eso ninguna es opcional
 * aquí — en este flujo concreto son lo único que hay.
 */
final readonly class ChangeMyPasswordHandler
{
    public function __construct(
        private UserRepository $users,
        private RefreshTokenRepository $refreshTokens,
        private PasswordHasher $hasher,
        private PasswordPolicy $policy,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ChangeMyPassword $command): void
    {
        try {
            $user = $this->users->ofId(UserId::fromString($command->userId));
        } catch (InvalidValue) {
            throw UserNotFound::withId($command->userId);
        }

        if (null === $user) {
            throw UserNotFound::withId($command->userId);
        }

        $current = $user->passwordHash();
        $given = $command->currentPassword ?? '';

        if (null !== $current) {
            if ('' === $given) {
                throw CurrentPasswordRequired::create();
            }

            if (!$this->hasher->matches($given, $current)) {
                throw IncorrectPassword::create();
            }

            if ($this->hasher->matches($command->newPassword, $current)) {
                throw PasswordUnchanged::create();
            }
        }

        $this->policy->ensureAcceptable($command->newPassword);

        $hashed = $this->hasher->hash($command->newPassword);
        $now = $this->clock->now();

        $this->session->execute(function () use ($user, $hashed, $now): void {
            $user->changePassword($hashed, $now);
            $this->users->save($user);

            // Todas, y no «las demás»: la petición no trae nada que
            // identifique la sesión desde la que llega —el token de acceso es
            // un JWT sin vínculo con su token de refresco—, así que la única
            // lectura implementable es la segura. El coste es volver a
            // entrar también en este dispositivo.
            $this->refreshTokens->revokeAllOf($user->id(), $now);
        });

        $this->events->publish(new PasswordChanged(
            EventId::generate(),
            $user->id(),
            viaReset: false,
            changedAt: $now,
        ));
    }
}
