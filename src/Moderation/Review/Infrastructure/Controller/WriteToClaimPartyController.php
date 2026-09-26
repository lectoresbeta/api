<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Infrastructure\Controller;

use LectoresBeta\Moderation\Review\Application\Command\WriteToClaimParty;
use LectoresBeta\Moderation\Review\Application\Handler\WriteToClaimPartyHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/admin/claims/{claimId}/messages` (`FEAT-MOD-009`).
 *
 * **La ruta del moderador indica a qué parte escribe**; la de la parte no
 * necesita indicarlo, porque solo tiene un hilo.
 */
#[AsController]
final readonly class WriteToClaimPartyController
{
    public function __construct(
        private WriteToClaimPartyHandler $write,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $claimId): Response
    {
        $moderator = $this->security->getUser();

        if (null === $moderator) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        $messageId = ($this->write)(new WriteToClaimParty(
            $claimId,
            $moderator->getUserIdentifier(),
            $body->string('party'),
            $body->string('body'),
        ));

        return new JsonResponse(['messageId' => $messageId], Response::HTTP_CREATED);
    }
}
