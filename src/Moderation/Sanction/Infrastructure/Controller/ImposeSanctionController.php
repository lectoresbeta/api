<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Sanction\Infrastructure\Controller;

use LectoresBeta\Moderation\Sanction\Application\Command\ImposeSanction;
use LectoresBeta\Moderation\Sanction\Application\Handler\ImposeSanctionHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/admin/sanctions` (`FEAT-MOD-006`).
 *
 * **Solo moderación**, y lo comprueba el firewall contra la base de datos en
 * cada petición: el token no lleva roles.
 */
#[AsController]
final readonly class ImposeSanctionController
{
    public function __construct(
        private ImposeSanctionHandler $impose,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $moderator = $this->security->getUser();

        if (null === $moderator) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        $sanctionId = ($this->impose)(new ImposeSanction(
            $moderator->getUserIdentifier(),
            (string) $body->string('userId'),
            $body->string('type'),
            $body->string('reason'),
            $body->string('duration'),
            $body->string('claimId'),
        ));

        return new JsonResponse(['sanctionId' => $sanctionId], Response::HTTP_CREATED);
    }
}
