<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Command\ResetPassword;
use LectoresBeta\User\Account\Application\Port\PasswordHasher;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Event\AccountActivated;
use LectoresBeta\User\Account\Domain\Event\PasswordChanged;
use LectoresBeta\User\Account\Domain\Exception\InvalidPasswordResetToken;
use LectoresBeta\User\Account\Domain\Exception\PasswordResetTokenExpired;
use LectoresBeta\User\Account\Domain\Repository\PasswordResetTokenRepository;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\Service\PasswordPolicy;
use LectoresBeta\User\Authentication\Domain\Repository\RefreshTokenRepository;

/**
 * Fijar la contraseña con el enlace del correo (`FEAT-USR-007`).
 *
 * Tres cosas ocurren juntas, y ninguna es prescindible:
 *
 * 1. **se fija la contraseña** y se consume el token, que es de un solo uso;
 * 2. **se cierran todas las sesiones** (`RN-10`). Quien recupera su
 *    contraseña muchas veces lo hace porque sospecha que alguien más entró;
 *    dejar viva esa sesión sería dejar dentro justo a quien se quiere echar;
 * 3. **se activa la cuenta si no lo estaba** (`RN-14`). Activar sirve para
 *    demostrar que el buzón es tuyo, y usar un enlace mandado a ese buzón
 *    demuestra lo mismo. Pedir después el otro enlace sería exigir dos veces
 *    la misma prueba, y dejaría a alguien dentro sin poder escribir sin
 *    entender por qué.
 *
 * Todo en una transacción: una contraseña cambiada con el token todavía
 * usable dejaría el enlace sirviendo una segunda vez.
 *
 * **Esto no inicia sesión.** Sería cómodo y convertiría un enlace de correo
 * en un inicio de sesión completo: quien lo interceptase entraría sin
 * escribir nada. Después hay que entrar por el login, que es donde están sus
 * límites y sus avisos.
 */
final readonly class ResetPasswordHandler
{
    public function __construct(
        private UserRepository $users,
        private PasswordResetTokenRepository $tokens,
        private RefreshTokenRepository $refreshTokens,
        private SecureTokenFactory $secureTokens,
        private PasswordHasher $hasher,
        private PasswordPolicy $policy,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ResetPassword $command): void
    {
        $now = $this->clock->now();
        $token = $this->tokens->ofTokenHash($this->secureTokens->hashOf($command->token));

        if (null === $token) {
            throw InvalidPasswordResetToken::create();
        }

        if (!$token->isUsable($now)) {
            // «Usable» son tres cosas a la vez. Solo la caducidad se puede
            // revelar: es la única que quien está delante puede resolver.
            throw $now >= $token->expiresAt() ? PasswordResetTokenExpired::create() : InvalidPasswordResetToken::create();
        }

        $user = $this->users->ofId($token->userId());

        if (null === $user || AccountStatus::DELETED === $user->status()) {
            throw InvalidPasswordResetToken::create();
        }

        // Antes de tocar nada: una contraseña débil no debe consumir el
        // enlace. Quien se equivoca escribiendo la nueva tiene que poder
        // volver a intentarlo con el mismo correo delante.
        $this->policy->ensureAcceptable($command->password);

        $hashed = $this->hasher->hash($command->password);
        $activating = AccountStatus::PENDING_ACTIVATION === $user->status();

        $this->session->execute(function () use ($user, $token, $hashed, $activating, $now): void {
            $user->changePassword($hashed, $now);

            if ($activating) {
                $user->activate($now);
            }

            $token->consume($now);

            $this->users->save($user);
            $this->tokens->save($token);
            $this->refreshTokens->revokeAllOf($user->id(), $now);
        });

        $this->events->publish(new PasswordChanged(
            EventId::generate(),
            $user->id(),
            viaReset: true,
            changedAt: $now,
        ));

        // El mismo hecho que publica seguir el enlace de activación: para
        // `Credits`, que abona la bienvenida, esto **es** una activación y no
        // tiene por qué saber por dónde llegó.
        if ($activating) {
            $this->events->publish(new AccountActivated(
                EventId::generate(),
                $user->id(),
                $now,
            ));
        }
    }
}
