<?php

declare(strict_types=1);

namespace LectoresBeta\User\Account\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Exception\InvalidValue;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Application\Command\RequestEmailChange;
use LectoresBeta\User\Account\Application\Port\PasswordHasher;
use LectoresBeta\User\Account\Domain\Entity\EmailChangeRequest;
use LectoresBeta\User\Account\Domain\Event\EmailChangeRequested;
use LectoresBeta\User\Account\Domain\Exception\CurrentPasswordRequired;
use LectoresBeta\User\Account\Domain\Exception\EmailChangeRefused;
use LectoresBeta\User\Account\Domain\Exception\IncorrectPassword;
use LectoresBeta\User\Account\Domain\Exception\UserNotFound;
use LectoresBeta\User\Account\Domain\Repository\EmailChangeRequestRepository;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Account\Domain\ValueObject\Email;
use LectoresBeta\User\Account\Domain\ValueObject\EmailChangeRequestId;
use LectoresBeta\User\Account\Domain\ValueObject\UserId;

/**
 * Pedir el cambio de correo (`FEAT-USR-040`).
 *
 * **Cambiar el correo no es editar un campo.** Es la identidad de la cuenta:
 * con él se entra, por él se recupera la contraseña y de él salió el nombre
 * de usuario. Por eso son dos pasos con verificación y no un `PATCH`.
 *
 * Aquí solo se registra la intención. **La dirección no cambia** (`RN-2`):
 * hasta confirmar, la válida sigue siendo la anterior, y entre un paso y otro
 * la cuenta funciona con normalidad.
 *
 * Pide la contraseña actual (`RN-1`) por el mismo motivo que el cambio de
 * contraseña: sin ella, quien se siente ante una sesión ajena apunta la
 * cuenta a su propio buzón y se queda con ella vía «he olvidado mi
 * contraseña».
 *
 * Una segunda solicitud **anula la primera** (`RN-6`), y de eso se encarga
 * además un índice único parcial: dos enlaces vivos apuntando a direcciones
 * distintas son una forma de acabar con la cuenta en el buzón equivocado.
 */
final readonly class RequestEmailChangeHandler
{
    public function __construct(
        private UserRepository $users,
        private EmailChangeRequestRepository $requests,
        private PasswordHasher $hasher,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(RequestEmailChange $command): void
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

        // Una cuenta sin contraseña —la de quien entró con Google— no puede
        // aportarla. `S-8` sigue abierta sobre qué la sustituye, así que de
        // momento este camino le está cerrado: abrirlo sin nada que demostrar
        // convertiría una sesión robada en un cambio de dueño.
        if (null === $current) {
            throw CurrentPasswordRequired::create();
        }

        if (null === $command->currentPassword || !$this->hasher->matches($command->currentPassword, $current)) {
            throw IncorrectPassword::create();
        }

        $newEmail = Email::fromString($command->newEmail);

        // La misma respuesta para «ya la tiene otra cuenta» y «es la que ya
        // tienes» (`RN-4`): decir cuál convertiría esto en un comprobador de
        // quién tiene cuenta.
        if ($newEmail->equals($user->email()) || null !== $this->users->ofEmail($newEmail)) {
            throw EmailChangeRefused::create();
        }

        $now = $this->clock->now();
        $request = new EmailChangeRequest(
            EmailChangeRequestId::generate(),
            $user->id(),
            $newEmail,
            $now,
            // Un plazo provisional: el de verdad lo fija el contrato al
            // acuñar el token, que es cuando el correo sale.
            $now->add(new \DateInterval('P2D')),
        );

        $pending = $this->requests->pendingOf($user->id());

        // Anular la anterior y crear la nueva van en **dos transacciones**, y
        // en este orden. El índice único parcial solo admite una solicitud
        // viva por cuenta, así que en una sola transacción Doctrine podría
        // insertar antes de actualizar y chocar consigo mismo. El hueco entre
        // las dos es inocuo: lo peor que puede pasar es quedarse sin ninguna
        // solicitud viva, y pedirlo otra vez lo arregla.
        if (null !== $pending) {
            $this->session->execute(function () use ($pending, $now): void {
                $pending->consume($now);
                $this->requests->save($pending);
            });
        }

        $this->session->execute(function () use ($request): void {
            $this->requests->save($request);
        });

        $this->events->publish(new EmailChangeRequested(
            EventId::generate(),
            $user->id(),
            $request->id(),
            $now,
        ));
    }
}
