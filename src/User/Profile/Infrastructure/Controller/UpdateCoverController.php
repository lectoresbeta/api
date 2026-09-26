<?php

declare(strict_types=1);

namespace LectoresBeta\User\Profile\Infrastructure\Controller;

use LectoresBeta\Shared\Application\Storage\MediaUrl;
use LectoresBeta\User\Profile\Application\Command\UpdateMyCover;
use LectoresBeta\User\Profile\Application\Handler\UpdateMyCoverHandler;
use LectoresBeta\User\Profile\Domain\Exception\CoverRefused;
use LectoresBeta\User\Profile\Domain\Service\CoverPolicy;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/me/profile/cover` (`FEAT-USR-016`).
 *
 * `multipart/form-data` con `image`, y **sin `crop`**, que es la diferencia
 * con el avatar: un fondo es un banner y su proporción la decide quien diseña
 * la pantalla, no un editor de recorte.
 *
 * Aquí termina lo que Symfony sabe de una subida: a Application pasan bytes.
 */
#[AsController]
final readonly class UpdateCoverController
{
    public function __construct(
        private UpdateMyCoverHandler $update,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $key = ($this->update)(new UpdateMyCover($user->getUserIdentifier(), self::bytesOf($request)));

        return new JsonResponse(['coverUrl' => MediaUrl::of($key)]);
    }

    /**
     * Una subida **que el servidor ha rechazado por tamaño antes de llegar
     * aquí** —`upload_max_filesize`— viene marcada como no válida, y responde
     * lo mismo que una demasiado grande: por fuera son el mismo caso.
     */
    private static function bytesOf(Request $request): ?string
    {
        $file = $request->files->get('image');

        if (!$file instanceof UploadedFile) {
            return null;
        }

        if (!$file->isValid()) {
            throw \UPLOAD_ERR_INI_SIZE === $file->getError() || \UPLOAD_ERR_FORM_SIZE === $file->getError() ? CoverRefused::tooLarge() : CoverRefused::unreadable();
        }

        if ($file->getSize() > CoverPolicy::MAX_BYTES) {
            throw CoverRefused::tooLarge();
        }

        $contents = file_get_contents($file->getPathname());

        return false === $contents ? null : $contents;
    }
}
