<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\Shared\Application\Storage\FileStorage;
use LectoresBeta\Shared\Application\Storage\StoredFile;
use LectoresBeta\User\Profile\Application\Query\GetMyAvatarOriginal;
use LectoresBeta\User\Profile\Application\Service\MyProfile;
use LectoresBeta\User\Profile\Domain\Exception\ProfileNotFound;

/**
 * La foto **sin recortar**, para reabrir el editor (`FEAT-USR-037`).
 *
 * Tiene endpoint propio en vez de viajar como una URL en el perfil, y es una
 * decisión de privacidad, no de comodidad: la original **no se expone**
 * —puede enseñar mucho más que el encuadre que su dueño eligió mostrar— y una
 * URL en el perfil propio acaba copiada en un sitio donde la ve alguien más.
 * Aquí solo la sirve quien la subió, con su sesión.
 */
final readonly class GetMyAvatarOriginalHandler
{
    public function __construct(
        private MyProfile $profile,
        private FileStorage $storage,
    ) {
    }

    public function __invoke(GetMyAvatarOriginal $query): StoredFile
    {
        $user = $this->profile->of($query->userId);
        $key = $user->avatarOriginalUrl();

        // Quien no tiene foto no tiene original, y quien subió una antes de
        // que se guardaran originales tampoco (`F-13`). Los dos casos son lo
        // mismo desde fuera: no hay nada que editar.
        return (null === $key ? null : $this->storage->read($key)) ?? throw ProfileNotFound::create();
    }
}
