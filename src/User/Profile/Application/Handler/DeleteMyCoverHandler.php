<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\Shared\Application\Storage\FileStorage;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Profile\Application\Command\DeleteMyCover;
use LectoresBeta\User\Profile\Application\Service\MyProfile;

/**
 * Quitar el fondo (`FEAT-USR-016` `RN-6`).
 *
 * Devuelve la página al tema sin imagen y **borra el fichero**: dejarlo
 * huérfano en el almacén sería guardar una imagen que su dueño cree haber
 * borrado.
 *
 * Es idempotente: quitar un fondo que no hay no es un error, porque el estado
 * que se pedía ya se cumple.
 */
final readonly class DeleteMyCoverHandler
{
    public function __construct(
        private MyProfile $profile,
        private UserRepository $users,
        private FileStorage $storage,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(DeleteMyCover $command): void
    {
        $user = $this->profile->of($command->userId);
        $cover = $user->coverUrl();

        if (null === $cover) {
            return;
        }

        $this->session->execute(function () use ($user): void {
            $user->updateCover(null, $this->clock->now());
            $this->users->save($user);
        });

        $this->storage->delete($cover);
    }
}
