<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\User\Account\Application\Command\ResendActivationEmail;
use LectoresBeta\User\Account\Domain\Enum\AccountStatus;
use LectoresBeta\User\Account\Domain\Event\ActivationEmailRequested;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;

/**
 * Volver a mandar el enlace de activación (`FEAT-USR-021`).
 *
 * **No devuelve nada, y esa es la funcionalidad entera.** Los tres desenlaces
 * —no hay cuenta, la hay sin activar, ya está activada— terminan igual, sin
 * que quien llama pueda distinguirlos (`RN-4`, `RN-5`). Cualquier diferencia
 * observable convertiría el formulario en un comprobador de qué direcciones
 * tienen cuenta, que es exactamente lo que no debe ser un endpoint público.
 *
 * Por lo mismo, un correo mal formado tampoco es un error aquí: responder
 * `422` a «pepe@» y `202` a «pepe@ejemplo.com» ya diría algo. La validación
 * de formato la hace el formulario, que sí puede.
 *
 * Solo publica el hecho. Quién invalida el token anterior y quién compone el
 * correo son `RN-1` y `FEAT-NOT-008`, y ocurren al enviar: así el enlace
 * empieza a caducar cuando sale el correo y no cuando se pidió (`RN-6`).
 */
final readonly class ResendActivationEmailHandler
{
    public function __construct(
        private UserRepository $users,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(ResendActivationEmail $command): void
    {
        try {
            $email = Email::fromString($command->email);
        } catch (InvalidValue) {
            return;
        }

        $user = $this->users->ofEmail($email);

        if (null === $user || AccountStatus::PENDING_ACTIVATION !== $user->status()) {
            return;
        }

        $this->events->publish(new ActivationEmailRequested(
            EventId::generate(),
            $user->id(),
            $this->clock->now(),
        ));
    }
}
