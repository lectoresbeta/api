<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\Shared\Application\Storage\MediaUrl;
use LectoresBeta\User\Profile\Application\Command\UpdateMyAvatar;
use LectoresBeta\User\Profile\Application\Handler\UpdateMyAvatarHandler;
use LectoresBeta\User\Profile\Domain\Exception\AvatarRefused;
use LectoresBeta\User\Profile\Domain\Service\AvatarPolicy;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/me/profile/avatar` (`FEAT-USR-037`).
 *
 * `multipart/form-data` con `image` —la recortada— y, la primera vez,
 * `original`. Al reencuadrar basta con la primera: la original ya está
 * guardada y volver a subirla sería mandar por la red algo que no ha
 * cambiado.
 *
 * **Subir y reencuadrar son el mismo endpoint**: para el servidor son la
 * misma operación —llega una imagen cuadrada nueva y sustituye a la
 * anterior—, y la distinción entre «Cambiar» y «Editar» es de interfaz.
 *
 * `crop` viaja como JSON y **solo se guarda**: el recorte lo hizo el
 * navegador, y esto sirve para que «Editar» reabra el editor donde el usuario
 * lo dejó.
 *
 * Aquí termina lo que Symfony sabe de una subida: a Application pasan bytes.
 */
#[AsController]
final readonly class UpdateAvatarController
{
    public function __construct(
        private UpdateMyAvatarHandler $update,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $key = ($this->update)(new UpdateMyAvatar(
            $user->getUserIdentifier(),
            self::bytesOf($request, 'image'),
            self::bytesOf($request, 'original'),
            self::cropOf($request),
        ));

        return new JsonResponse(['avatarUrl' => MediaUrl::of($key)]);
    }

    /**
     * El fichero, leído entero.
     *
     * Una subida **que el servidor ha rechazado por tamaño antes de llegar
     * aquí** —`upload_max_filesize`— viene marcada como no válida, y responde
     * lo mismo que una demasiado grande: por fuera son el mismo caso.
     */
    private static function bytesOf(Request $request, string $field): ?string
    {
        $file = $request->files->get($field);

        if (!$file instanceof UploadedFile) {
            return null;
        }

        if (!$file->isValid()) {
            throw \UPLOAD_ERR_INI_SIZE === $file->getError() || \UPLOAD_ERR_FORM_SIZE === $file->getError() ? AvatarRefused::tooLarge() : AvatarRefused::unreadable();
        }

        if ($file->getSize() > AvatarPolicy::MAX_BYTES) {
            throw AvatarRefused::tooLarge();
        }

        $contents = file_get_contents($file->getPathname());

        return false === $contents ? null : $contents;
    }

    /**
     * Escala, rotación y desplazamiento, si el editor los manda. Cualquier
     * cosa que no sean números se descarta entera: es un dato para reabrir
     * una pantalla, no algo que merezca un error.
     *
     * @return array<string, float|int>|null
     */
    private static function cropOf(Request $request): ?array
    {
        $raw = $request->request->get('crop');

        if (!\is_string($raw) || '' === $raw) {
            return null;
        }

        try {
            $decoded = json_decode($raw, true, 8, \JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!\is_array($decoded)) {
            return null;
        }

        $crop = [];

        foreach (['scale', 'rotation', 'offsetX', 'offsetY'] as $field) {
            $value = $decoded[$field] ?? null;

            if (\is_int($value) || \is_float($value)) {
                $crop[$field] = $value;
            }
        }

        return [] === $crop ? null : $crop;
    }
}
