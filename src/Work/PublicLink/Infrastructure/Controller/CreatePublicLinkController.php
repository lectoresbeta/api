<?php

declare(strict_types=1);

namespace LectoresBeta\Work\PublicLink\Infrastructure\Controller;

use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use LectoresBeta\Work\PublicLink\Application\Command\CreatePublicLink;
use LectoresBeta\Work\PublicLink\Application\Handler\CreatePublicLinkHandler;
use LectoresBeta\Work\PublicLink\Infrastructure\Http\PublicLinkPayload;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/works/{workId}/public-links` (`FEAT-WRK-010`).
 *
 * **La única respuesta de toda la API que lleva el token**, y por eso lleva
 * `Cache-Control: no-store`: una credencial que un intermediario guarda es
 * una credencial que ya no controla su dueño.
 */
#[AsController]
final readonly class CreatePublicLinkController
{
    public function __construct(
        private CreatePublicLinkHandler $create,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        $created = ($this->create)(new CreatePublicLink(
            $workId,
            $user->getUserIdentifier(),
            $body->int('maxCorrections'),
            $body->string('expiresAt'),
            $body->string('label'),
        ));

        $response = new JsonResponse(
            PublicLinkPayload::of($created->link) + ['token' => $created->token],
            Response::HTTP_CREATED,
        );
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
