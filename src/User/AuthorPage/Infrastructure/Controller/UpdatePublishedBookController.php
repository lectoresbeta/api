<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\AuthorPage\Application\Command\UpdatePublishedBook;
use LectoresBeta\User\AuthorPage\Application\Handler\UpdatePublishedBookHandler;
use LectoresBeta\User\AuthorPage\Infrastructure\Http\PublishedBookBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PATCH /api/v1/me/published-books/{publishedBookId}` (`FEAT-USR-029`).
 *
 * Lo que no se envía se queda como estaba; enviar `publisher: null` lo borra.
 * La diferencia la hace la presencia de la clave, no su valor.
 */
#[AsController]
final readonly class UpdatePublishedBookController
{
    public function __construct(
        private UpdatePublishedBookHandler $update,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $publishedBookId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        return new JsonResponse(PublishedBookBody::of(($this->update)(new UpdatePublishedBook(
            $user->getUserIdentifier(),
            $publishedBookId,
            $body->has('title'),
            $body->string('title'),
            $body->has('publisher'),
            $body->string('publisher'),
            $body->has('publicationYear'),
            $body->int('publicationYear'),
            $body->has('purchaseUrl'),
            $body->string('purchaseUrl'),
            $body->has('position'),
            $body->int('position'),
        ))));
    }
}
