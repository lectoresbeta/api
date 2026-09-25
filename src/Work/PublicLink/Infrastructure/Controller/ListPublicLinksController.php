<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Infrastructure\Controller;

use LectoresBeta\Work\PublicLink\Application\DTO\PublicLinkView;
use LectoresBeta\Work\PublicLink\Application\Handler\ListPublicLinksHandler;
use LectoresBeta\Work\PublicLink\Application\Query\ListPublicLinks;
use LectoresBeta\Work\PublicLink\Infrastructure\Http\PublicLinkPayload;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/works/{workId}/public-links` (`FEAT-WRK-010`).
 *
 * Incluye los revocados y los caducados: el autor necesita saber **qué
 * repartió**, no solo qué sigue abierto.
 */
#[AsController]
final readonly class ListPublicLinksController
{
    public function __construct(
        private ListPublicLinksHandler $links,
        private Security $security,
    ) {
    }

    public function __invoke(string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $links = ($this->links)(new ListPublicLinks($workId, $user->getUserIdentifier()));

        return new JsonResponse([
            'publicLinks' => array_map(
                static fn (PublicLinkView $link): array => PublicLinkPayload::of($link),
                $links,
            ),
        ]);
    }
}
