<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Infrastructure\Controller;

use LectoresBeta\Moderation\Claim\Application\Command\SubmitClaim;
use LectoresBeta\Moderation\Claim\Application\Handler\SubmitClaimHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/claims` (`FEAT-MOD-001`).
 *
 * **La respuesta no dice qué va a pasar**, porque no va a pasar nada
 * todavía: confirma el registro y da un identificador con el que seguirla.
 *
 * Un reintento devuelve la reclamación que ya existía y `201` igualmente, con
 * `alreadyExisted` a `true`: reclamar dos veces suele ser un doble clic, y
 * responder con un error a eso sería castigar la torpeza.
 */
#[AsController]
final readonly class SubmitClaimController
{
    public function __construct(
        private SubmitClaimHandler $submit,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $body = JsonBody::of($request);

        $claim = ($this->submit)(new SubmitClaim(
            $user->getUserIdentifier(),
            (string) $body->string('targetType'),
            (string) $body->string('targetId'),
            (string) $body->string('reason'),
            $body->string('description'),
        ));

        return new JsonResponse([
            'claimId' => $claim->claimId,
            'status' => $claim->status,
            'alreadyExisted' => $claim->alreadyExisted,
        ], Response::HTTP_CREATED);
    }
}
