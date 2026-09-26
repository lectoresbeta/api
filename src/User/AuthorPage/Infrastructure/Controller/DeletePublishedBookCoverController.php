<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Controller;

use LectoresBeta\User\AuthorPage\Application\Command\DeletePublishedBookCover;
use LectoresBeta\User\AuthorPage\Application\Handler\DeletePublishedBookCoverHandler;
use LectoresBeta\User\AuthorPage\Infrastructure\Http\PublishedBookBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/me/published-books/{publishedBookId}/cover`
 * (`FEAT-USR-029` `RN-10`).
 *
 * Quita la portada y deja la obra. Devuelve la ficha entera y no un `204`
 * porque lo que el cliente pinta a continuación es esa ficha sin portada.
 */
#[AsController]
final readonly class DeletePublishedBookCoverController
{
    public function __construct(
        private DeletePublishedBookCoverHandler $delete,
        private Security $security,
    ) {
    }

    public function __invoke(string $publishedBookId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse(PublishedBookBody::of(($this->delete)(new DeletePublishedBookCover(
            $user->getUserIdentifier(),
            $publishedBookId,
        ))));
    }
}
