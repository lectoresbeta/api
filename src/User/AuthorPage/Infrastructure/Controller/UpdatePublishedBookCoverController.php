<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Controller;

use LectoresBeta\User\AuthorPage\Application\Command\UpdatePublishedBookCover;
use LectoresBeta\User\AuthorPage\Application\Handler\UpdatePublishedBookCoverHandler;
use LectoresBeta\User\AuthorPage\Domain\Exception\PublishedBookRefused;
use LectoresBeta\User\AuthorPage\Domain\Service\PublishedBookPolicy;
use LectoresBeta\User\AuthorPage\Infrastructure\Http\PublishedBookBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/me/published-books/{publishedBookId}/cover` (`FEAT-USR-029`).
 *
 * `multipart/form-data` con `cover`. **Endpoint aparte y no un campo más del
 * `PATCH`**, por una razón que no es de gusto: PHP solo desmonta un cuerpo
 * `multipart` en las peticiones `POST`, así que una portada que viajara en el
 * `PATCH` llegaría como un cuerpo vacío. Separarlos, además, es lo que evita
 * que el año se quede sin guardar porque falló una subida.
 *
 * Aquí termina lo que Symfony sabe de una subida: a Application pasan bytes.
 */
#[AsController]
final readonly class UpdatePublishedBookCoverController
{
    public function __construct(
        private UpdatePublishedBookCoverHandler $update,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $publishedBookId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(PublishedBookBody::of(($this->update)(new UpdatePublishedBookCover(
            $user->getUserIdentifier(),
            $publishedBookId,
            self::bytesOf($request),
        ))));
    }

    /**
     * El fichero, leído entero.
     *
     * Una subida **que el servidor ha rechazado por tamaño antes de llegar
     * aquí** —`upload_max_filesize`— viene marcada como no válida, y responde
     * lo mismo que una demasiado grande: por fuera son el mismo caso.
     */
    private static function bytesOf(Request $request): ?string
    {
        $file = $request->files->get('cover');

        if (!$file instanceof UploadedFile) {
            return null;
        }

        if (!$file->isValid()) {
            throw \UPLOAD_ERR_INI_SIZE === $file->getError() || \UPLOAD_ERR_FORM_SIZE === $file->getError() ? PublishedBookRefused::coverTooLarge() : PublishedBookRefused::unreadableCover();
        }

        if ($file->getSize() > PublishedBookPolicy::COVER_MAX_BYTES) {
            throw PublishedBookRefused::coverTooLarge();
        }

        $contents = file_get_contents($file->getPathname());

        return false === $contents ? null : $contents;
    }
}
