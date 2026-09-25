<?php

declare(strict_types=1);

namespace LectoresBeta\User\AuthorPage\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\User\AuthorPage\Application\Command\UpdateAuthorLinks;
use LectoresBeta\User\AuthorPage\Application\DTO\AuthorLinkView;
use LectoresBeta\User\AuthorPage\Application\Handler\UpdateAuthorLinksHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/me/author-links` (`FEAT-USR-015`).
 *
 * **`PUT` y la lista entera**, no un `POST` por referencia: lo que el
 * formulario manda es «estas son mis referencias», con su orden, y ese orden
 * es del usuario — quien pone su web primero y su cuenta de fotos después lo
 * ha decidido.
 *
 * No hay un `GET` hermano, y esa ausencia es la ficha: **la página de autor
 * es el perfil** (`P-5`, resuelta), así que las referencias se leen donde se
 * lee todo lo demás de esa persona, en `GET /users/{userId}`. Un endpoint
 * aparte habría sido el primer paso hacia dos perfiles que mantener.
 */
#[AsController]
final readonly class UpdateAuthorLinksController
{
    public function __construct(
        private UpdateAuthorLinksHandler $update,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $given = [];

        foreach (JsonBody::of($request)->objectList('links') as $link) {
            $given[] = ['label' => $link->string('label'), 'url' => $link->string('url')];
        }

        $links = ($this->update)(new UpdateAuthorLinks($user->getUserIdentifier(), $given));

        return new JsonResponse([
            'links' => array_map(
                static fn (AuthorLinkView $link): array => ['label' => $link->label, 'url' => $link->url],
                $links,
            ),
        ]);
    }
}
