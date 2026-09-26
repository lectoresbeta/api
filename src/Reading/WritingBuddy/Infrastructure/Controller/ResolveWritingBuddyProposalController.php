<?php

declare(strict_types=1);

namespace LectoresBeta\Reading\WritingBuddy\Infrastructure\Controller;

use LectoresBeta\Reading\WritingBuddy\Application\Command\ResolveWritingBuddyProposal;
use LectoresBeta\Reading\WritingBuddy\Application\Handler\ResolveWritingBuddyProposalHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/writing-buddy-proposals/{linkId}/resolution` (`FEAT-RDG-009`).
 *
 * Misma forma que resolver una invitación de lector beta, y por lo mismo: las
 * dos decisiones son la misma operación con distinto desenlace, y dos rutas
 * harían creer que son dos cosas.
 *
 * **Solo la resuelve quien la recibió.** Para quien la propuso responde
 * `404`, igual que para alguien ajeno.
 */
#[AsController]
final readonly class ResolveWritingBuddyProposalController
{
    public function __construct(
        private ResolveWritingBuddyProposalHandler $resolve,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $linkId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse([
            'status' => ($this->resolve)(new ResolveWritingBuddyProposal(
                $user->getUserIdentifier(),
                $linkId,
                JsonBody::of($request)->string('decision'),
            )),
        ]);
    }
}
