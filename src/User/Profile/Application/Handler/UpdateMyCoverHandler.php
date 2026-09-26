<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\Shared\Application\Image\ImageProcessor;
use LectoresBeta\Shared\Application\Storage\FileStorage;
use LectoresBeta\Shared\Application\Storage\StoredFile;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Profile\Application\Command\UpdateMyCover;
use LectoresBeta\User\Profile\Application\Service\MyProfile;
use LectoresBeta\User\Profile\Domain\Exception\CoverRefused;
use LectoresBeta\User\Profile\Domain\Service\CoverPolicy;

/**
 * Subir el fondo de la página de autor (`FEAT-USR-016` `RN-4`).
 *
 * `User` tenía `coverUrl` y `updateCover` desde `FEAT-USR-014` y **nunca tuvo
 * endpoint**. Esto no inventa una imagen nueva: le pone la puerta que le
 * faltaba.
 *
 * **La imagen se reescribe siempre**, como el avatar y por lo mismo: guardar
 * lo que llega tal cual publicaría los metadatos EXIF, y una foto de fondo
 * puede llevar las coordenadas de dónde se tomó.
 *
 * **No se recorta** (`RN-5`): un fondo es un banner, no un retrato, y su
 * proporción la decide quien diseña la pantalla. Solo se acota el lado mayor.
 *
 * Y se borra lo que sustituye, después de guardar: borrar antes dejaría el
 * perfil apuntando a un fichero que ya no está si la transacción no cierra.
 */
final readonly class UpdateMyCoverHandler
{
    public function __construct(
        private MyProfile $profile,
        private UserRepository $users,
        private ImageProcessor $images,
        private FileStorage $storage,
        private TransactionalSession $session,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateMyCover $command): string
    {
        $user = $this->profile->of($command->userId);

        if (null === $command->image || '' === $command->image) {
            throw CoverRefused::missing();
        }

        // El tamaño se mide sobre lo que llegó y no sobre lo que se guarda:
        // el límite existe para no tragarse un fichero enorme, y a lo que se
        // guarda ya lo acota el redimensionado.
        if (\strlen($command->image) > CoverPolicy::MAX_BYTES) {
            throw CoverRefused::tooLarge();
        }

        $cover = $this->images->normalise($command->image, CoverPolicy::MAX_SIDE)
            ?? throw self::refuse($command->image);

        // Imposible de adivinar y sin rastro del nombre original: un nombre
        // de fichero acaba en una URL, y el de alguien puede decir más de lo
        // que su dueño cree.
        $key = \sprintf('covers/%s.%s', bin2hex(random_bytes(16)), $cover->extension);
        $this->storage->put($key, new StoredFile($cover->contents, $cover->contentType));

        $replaced = $user->coverUrl();

        $this->session->execute(function () use ($user, $key): void {
            $user->updateCover($key, $this->clock->now());
            $this->users->save($user);
        });

        if (null !== $replaced && $replaced !== $key) {
            $this->storage->delete($replaced);
        }

        return $key;
    }

    /**
     * Distingue «no es una imagen» de «es una imagen que no se puede leer»,
     * que llevan a cosas distintas: elegir otro fichero, o volver a
     * exportarlo. Un HEIC de iPhone cae en el primero.
     */
    private static function refuse(string $bytes): CoverRefused
    {
        return false === @getimagesizefromstring($bytes)
            ? CoverRefused::unsupportedType()
            : CoverRefused::unreadable();
    }
}
