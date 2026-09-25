<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Application\Security\SecureTokenFactory;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Command\ConfirmEmailChange;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Event\EmailChanged;
use LectoresBeta\User\Account\Domain\Exception\EmailChangeLinkNoLongerValid;
use LectoresBeta\User\Account\Domain\Exception\EmailChangeRefused;
use LectoresBeta\User\Account\Domain\Exception\InvalidEmailChangeToken;
use LectoresBeta\User\Account\Domain\Repository\EmailChangeRequestRepository;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Authentication\Domain\Repository\RefreshTokenRepository;

/**
 * Confirmar el cambio desde el enlace que llegó a la dirección nueva
 * (`FEAT-USR-040`).
 *
 * **No exige sesión**, y no puede exigirla: el enlace se abre donde está
 * abierto ese buzón, que muchas veces es otro dispositivo. Lo que autoriza es
 * el token, que es de un solo uso y va atado a esa solicitud concreta.
 *
 * Al confirmar se cierran las sesiones (`RN-8`). Es la otra mitad de lo que
 * convierte esto en un mecanismo de seguridad y no en un trámite: sin ello,
 * quien hubiera entrado con una sesión robada seguiría dentro con la cuenta
 * ya apuntando a su buzón.
 *
 * **No toca el estado de activación** (`RN-9`) ni el nombre de usuario
 * (`RN-7`). Aquel ya está demostrado y este ya está asignado; el correo solo
 * lo originó la primera vez.
 *
 * La dirección puede haberse ocupado entre pedir y confirmar, así que se
 * vuelve a comprobar aquí. Es la única comprobación que no se puede hacer una
 * sola vez.
 */
final readonly class ConfirmEmailChangeHandler
{
    public function __construct(
        private UserRepository $users,
        private EmailChangeRequestRepository $requests,
        private RefreshTokenRepository $refreshTokens,
        private SecureTokenFactory $secureTokens,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ConfirmEmailChange $command): void
    {
        $now = $this->clock->now();
        $request = $this->requests->ofTokenHash($this->secureTokens->hashOf($command->token));

        if (null === $request) {
            throw InvalidEmailChangeToken::create();
        }

        // Usado, caducado o anulado por una solicitud posterior: para quien
        // está delante significan lo mismo, hay que pedirlo otra vez.
        if (!$request->isPending($now)) {
            throw EmailChangeLinkNoLongerValid::create();
        }

        $user = $this->users->ofId($request->userId());

        if (null === $user || AccountStatus::DELETED === $user->status()) {
            throw InvalidEmailChangeToken::create();
        }

        $newEmail = $request->newEmail();
        $taken = $this->users->ofEmail($newEmail);

        if (null !== $taken && $taken->id()->value() !== $user->id()->value()) {
            throw EmailChangeRefused::create();
        }

        $this->session->execute(function () use ($user, $request, $newEmail, $now): void {
            $user->changeEmail($newEmail, $now);
            $request->consume($now);

            $this->users->save($user);
            $this->requests->save($request);
            $this->refreshTokens->revokeAllOf($user->id(), $now);
        });

        $this->events->publish(new EmailChanged(
            EventId::generate(),
            $user->id(),
            $now,
        ));
    }
}
