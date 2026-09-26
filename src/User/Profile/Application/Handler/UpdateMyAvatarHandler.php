<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Application\Handler;

use LectoresBeta\Shared\Application\Event\EventPublisher;
use LectoresBeta\Shared\Application\Image\ImageProcessor;
use LectoresBeta\Shared\Application\Image\ProcessedImage;
use LectoresBeta\Shared\Application\Storage\FileStorage;
use LectoresBeta\Shared\Application\Storage\MediaUrl;
use LectoresBeta\Shared\Application\Storage\StoredFile;
use LectoresBeta\Shared\Domain\Clock\Clock;
use LectoresBeta\Shared\Domain\Event\EventId;
use LectoresBeta\Shared\Domain\Persistence\TransactionalSession;
use LectoresBeta\User\Account\Domain\Event\UserProfileUpdated;
use LectoresBeta\User\Account\Domain\Repository\UserRepository;
use LectoresBeta\User\Profile\Application\Command\UpdateMyAvatar;
use LectoresBeta\User\Profile\Application\Service\MyProfile;
use LectoresBeta\User\Profile\Domain\Exception\AvatarRefused;
use LectoresBeta\User\Profile\Domain\Service\AvatarPolicy;

/**
 * Subir o reencuadrar la foto de perfil (`FEAT-USR-037`).
 *
 * **La imagen se reescribe siempre** (`RN-3`), venga como venga y la haya
 * recortado quien la haya recortado. Es la regla que no se puede saltar por
 * comodidad: confiar en una imagen que el navegador ya recortó y guardarla
 * tal cual publicaría **las coordenadas de dónde se tomó la foto**. Recortar
 * y sanear son cosas distintas.
 *
 * Se guardan **dos ficheros** (`RN-4c`): la recortada, que es la que se ve, y
 * la original, que es material de trabajo del editor. Sin la segunda,
 * «Editar» solo podría recortar hacia dentro de 360 píxeles y cada pasada
 * degradaría un poco más la foto.
 *
 * Y se borra lo que sustituye (`RN-8`): una foto reemplazada deja de ser
 * accesible, en vez de quedarse huérfana en el almacén para siempre.
 */
final readonly class UpdateMyAvatarHandler
{
    public function __construct(
        private MyProfile $profile,
        private UserRepository $users,
        private ImageProcessor $images,
        private FileStorage $storage,
        private TransactionalSession $session,
        private EventPublisher $events,
        private Clock $clock,
    ) {
    }

    public function __invoke(UpdateMyAvatar $command): string
    {
        $user = $this->profile->of($command->userId);

        if (null === $command->image || '' === $command->image) {
            throw AvatarRefused::missing();
        }

        $avatar = $this->process(
            $command->image,
            fn (string $bytes): ?ProcessedImage => $this->images->normaliseSquare($bytes, AvatarPolicy::SIDE),
        );

        $original = null === $command->original || '' === $command->original
            ? null
            : $this->process(
                $command->original,
                fn (string $bytes): ?ProcessedImage => $this->images->normalise($bytes, AvatarPolicy::ORIGINAL_MAX_SIDE),
            );

        $avatarKey = self::keyFor($avatar, 'avatars');
        $this->storage->put($avatarKey, new StoredFile($avatar->contents, $avatar->contentType));

        // Lo que deja de usarse, para borrarlo cuando el cambio esté firme.
        $replaced = [$user->avatarUrl()];
        $originalKey = $user->avatarOriginalUrl();

        if (null !== $original) {
            $replaced[] = $originalKey;
            $originalKey = self::keyFor($original, 'originals');
            $this->storage->put($originalKey, new StoredFile($original->contents, $original->contentType));
        }

        $now = $this->clock->now();

        $this->session->execute(function () use ($user, $avatarKey, $originalKey, $command, $now): void {
            $user->updateAvatar($avatarKey, $originalKey, $command->crop, $now);
            $this->users->save($user);
        });

        // Solo después de que el cambio esté guardado: borrar antes dejaría a
        // alguien sin foto si la transacción no llega a cerrarse.
        foreach ($replaced as $old) {
            if (null !== $old && $old !== $avatarKey && $old !== $originalKey) {
                $this->storage->delete($old);
            }
        }

        $this->events->publish(new UserProfileUpdated(
            EventId::generate(),
            $user->id(),
            $user->name()?->value(),
            $user->description(),
            MediaUrl::of($avatarKey),
            $now,
        ));

        return $avatarKey;
    }

    /**
     * @param \Closure(string): ?ProcessedImage $normalise
     */
    private function process(string $bytes, \Closure $normalise): ProcessedImage
    {
        // El tamaño se mide sobre lo que llegó, no sobre lo que se guarda:
        // el límite existe para no tragarse un fichero enorme, y a lo que se
        // guarda ya lo acota el redimensionado.
        if (\strlen($bytes) > AvatarPolicy::MAX_BYTES) {
            throw AvatarRefused::tooLarge();
        }

        return $normalise($bytes) ?? throw self::refuse($bytes);
    }

    /**
     * Distingue «no es una imagen» de «es una imagen que no se puede leer»,
     * que llevan a cosas distintas: elegir otro fichero, o volver a
     * exportarlo. Un HEIC de iPhone cae en el primero (`F-3`).
     */
    private static function refuse(string $bytes): AvatarRefused
    {
        return false === @getimagesizefromstring($bytes)
            ? AvatarRefused::unsupportedType()
            : AvatarRefused::unreadable();
    }

    /**
     * Imposible de adivinar, y sin rastro del nombre original (`RN-7`): un
     * nombre de fichero acaba apareciendo en una URL, y el de alguien puede
     * decir mucho más de lo que su dueño cree.
     *
     * **Las dos imágenes viven en carpetas distintas a propósito.** Lo que se
     * sirve en abierto solo puede estar bajo `avatars/`, así que la original
     * no es alcanzable desde ahí ni sabiendo su clave: para leerla hay que
     * pasar por el endpoint que comprueba de quién es.
     */
    private static function keyFor(ProcessedImage $image, string $folder): string
    {
        return \sprintf('%s/%s.%s', $folder, bin2hex(random_bytes(16)), $image->extension);
    }
}
