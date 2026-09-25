<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Controller;

use LectoresBeta\User\AuthorPage\Application\Handler\ListPublishedBooksHandler;
use LectoresBeta\User\AuthorPage\Application\Query\ListPublishedBooks;
use LectoresBeta\User\AuthorPage\Infrastructure\Http\PublishedBookBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `GET /api/v1/users/{userId}/published-books` (`FEAT-USR-029`).
 *
 * **Público**, como el perfil del que forma parte. La sesión se lee si la
 * hay y sirve para una sola cosa: que su titular se vea siempre a sí mismo,
 * aunque haya restringido su perfil.
 */
#[AsController]
final readonly class ListPublishedBooksController
{
    public function __construct(
        private ListPublishedBooksHandler $books,
        private Security $security,
    ) {
    }

    public function __invoke(string $userId): Response
    {
        return new JsonResponse(PublishedBookBody::listOf(($this->books)(new ListPublishedBooks(
            $userId,
            $this->security->getUser()?->getUserIdentifier(),
        ))));
    }
}
