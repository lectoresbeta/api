<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Infrastructure\Controller;

use LectoresBeta\Moderation\Review\Application\Command\ReviewClaim;
use LectoresBeta\Moderation\Review\Application\Handler\ReviewClaimHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/admin/claims/{claimId}/review` (`FEAT-MOD-002`).
 *
 * Tomar y resolver en una sola llamada. La respuesta confirma la decisión y
 * **no dice qué va a pasar**: los efectos los aplica cada contexto cuando
 * reciba el hecho (`RN-4`), y prometerlos aquí sería prometer por otros.
 */
#[AsController]
final readonly class ReviewClaimController
{
    public function __construct(
        private ReviewClaimHandler $review,
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

        $reviewed = ($this->review)(new ReviewClaim(
            $claimId,
            $moderator->getUserIdentifier(),
            (string) $body->string('decision'),
            (string) $body->string('motivation'),
        ));

        return new JsonResponse([
            'claimId' => $reviewed->claimId,
            'status' => $reviewed->status,
            'decision' => $reviewed->decision,
        ]);
    }
}
