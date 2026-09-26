<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Controller;

use LectoresBeta\User\AuthorPage\Application\Command\DeletePublishedBook;
use LectoresBeta\User\AuthorPage\Application\Handler\DeletePublishedBookHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/me/published-books/{publishedBookId}` (`FEAT-USR-029`).
 */
#[AsController]
final readonly class DeletePublishedBookController
{
    public function __construct(
        private DeletePublishedBookHandler $delete,
        private Security $security,
    ) {
    }

    public function __invoke(string $publishedBookId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->delete)(new DeletePublishedBook($user->getUserIdentifier(), $publishedBookId));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
