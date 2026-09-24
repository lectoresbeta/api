<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Application\Storage\FileStorage;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\Event\UserProfileUpdated;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Profile\Application\Command\DeleteMyAvatar;
use LectoresBeta\User\Profile\Application\Service\MyProfile;

/**
 * Quitar la foto de perfil (`FEAT-USR-037` `RN-12`).
 *
 * **Borra los dos ficheros**, y esa es la mitad importante: dejar la original
 * huérfana en el almacén sería guardar una imagen personal que su dueño cree
 * haber borrado. Quien retira su foto la retira entera.
 *
 * Es **idempotente** (`RN-13`): quitar una foto que no hay no es un error,
 * porque el estado que se pedía ya se cumple. Tampoco publica nada entonces
 * — anunciar un cambio que no ocurrió haría trabajar a cada read model para
 * nada.
 *
 * El avatar por defecto no es un fichero de nadie: es el marcador de la
 * interfaz (`RN-14`), así que aquí no se pone nada en su lugar.
 */
final readonly class DeleteMyAvatarHandler
{
    public function __construct(
        private MyProfile $profile,
        private UserRepository $users,
        private FileStorage $storage,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(DeleteMyAvatar $command): void
    {
        $user = $this->profile->of($command->userId);
        $files = array_filter([$user->avatarUrl(), $user->avatarOriginalUrl()]);

        if (null === $user->avatarUrl() && null === $user->avatarOriginalUrl()) {
            return;
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($user, $now): void {
            $user->updateAvatar(null, null, null, $now);
            $this->users->save($user);
        });

        // Después de guardar: si se borrasen antes y la transacción fallara,
        // el perfil apuntaría a ficheros que ya no están.
        foreach ($files as $file) {
            $this->storage->delete($file);
        }

        $this->events->publish(new UserProfileUpdated(
            EventId::generate(),
            $user->id(),
            $user->name()?->value(),
            $user->description(),
            null,
            $now,
        ));
    }
}
