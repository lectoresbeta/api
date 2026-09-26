<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\AuthorPage\Application\Command\AddPublishedBook;
use LectoresBeta\User\AuthorPage\Application\Handler\AddPublishedBookHandler;
use LectoresBeta\User\AuthorPage\Infrastructure\Http\PublishedBookBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/me/published-books` (`FEAT-USR-029`).
 *
 * Solo el título es obligatorio (`RN-6`). La portada no viaja aquí: se sube
 * después, a su propio endpoint, porque `multipart/form-data` y una edición
 * campo a campo no caben en la misma petición.
 */
#[AsController]
final readonly class AddPublishedBookController
{
    public function __construct(
        private AddPublishedBookHandler $add,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        return new JsonResponse(
            PublishedBookBody::of(($this->add)(new AddPublishedBook(
                $user->getUserIdentifier(),
                $body->string('title') ?? '',
                $body->string('publisher'),
                $body->int('publicationYear'),
                $body->string('purchaseUrl'),
            ))),
            Response::HTTP_CREATED,
        );
    }
}
