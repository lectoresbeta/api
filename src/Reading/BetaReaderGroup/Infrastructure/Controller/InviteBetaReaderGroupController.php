<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\BetaReaderGroup\Infrastructure\Controller;

use LectoresBeta\Reading\BetaReaderGroup\Application\Command\InviteBetaReaderGroup;
use LectoresBeta\Reading\BetaReaderGroup\Application\Handler\InviteBetaReaderGroupHandler;
use LectoresBeta\Reading\BetaReaderGroup\Infrastructure\Http\BetaReaderGroupBody;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/works/{workId}/group-invitations` (`FEAT-RDG-007` `RN-12`).
 *
 * Responde **`200` y no `201`**: no se ha creado un recurso, se han creado
 * unas cuantas invitaciones y se ha omitido a unos cuantos, y eso es lo que
 * cuenta el cuerpo. El resultado es parcial a propósito.
 */
#[AsController]
final readonly class InviteBetaReaderGroupController
{
    public function __construct(
        private InviteBetaReaderGroupHandler $invite,
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

        $result = ($this->invite)(new InviteBetaReaderGroup(
            $workId,
            $user->getUserIdentifier(),
            $body->string('groupId'),
            $body->string('message'),
        ));

        return new JsonResponse(BetaReaderGroupBody::invitationResult($result));
    }
}
