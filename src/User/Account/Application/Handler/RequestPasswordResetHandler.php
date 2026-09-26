<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Application\Command\RequestPasswordReset;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Event\PasswordResetRequested;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;

/**
 * Pedir el enlace de recuperación (`FEAT-USR-007`).
 *
 * **No devuelve nada, y ese es el diseño.** Que la cuenta exista, que no
 * exista o que el correo esté mal escrito terminan igual (`RN-1`, `RN-2`):
 * cualquier diferencia observable convertiría este formulario en un
 * comprobador de qué direcciones tienen cuenta en la plataforma, que es una
 * lista valiosa para quien prepara un engaño.
 *
 * Solo publica el hecho cuando procede mandarlo. Quién emite el token y quién
 * compone el correo ocurren al enviar, así que el enlace empieza a caducar
 * cuando sale y no cuando se pidió — con una hora de vida (`RN-6`), eso
 * importa.
 *
 * Una cuenta **sin activar** también lo recibe: usarlo demuestra lo mismo que
 * activar, y `RN-14` cierra el círculo activándola. Una **eliminada** no, y
 * tampoco lo dice.
 */
final readonly class RequestPasswordResetHandler
{
    public function __construct(
        private UserRepository $users,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RequestPasswordReset $command): void
    {
        try {
            $email = Email::fromString($command->email);
        } catch (InvalidValue) {
            return;
        }

        $user = $this->users->ofEmail($email);

        if (null === $user || AccountStatus::DELETED === $user->status()) {
            return;
        }

        $this->events->publish(new PasswordResetRequested(
            EventId::generate(),
            $user->id(),
            $this->clock->now(),
        ));
    }
}
