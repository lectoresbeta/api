<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Claim\Infrastructure\Controller;

use LectoresBeta\Moderation\Claim\Application\Handler\ListMyClaimsHandler;
use LectoresBeta\Moderation\Claim\Application\Query\ListMyClaims;
use LectoresBeta\Moderation\Claim\Domain\Entity\Claim;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/claims` (`FEAT-MOD-010`).
 *
 * **Para que denunciar no sea gritar a un buzón.** Quien se molesta en
 * explicar por qué algo está mal necesita comprobar que su reclamación sigue
 * viva y en qué estado.
 *
 * No lleva la identidad de quien la revisa (`FEAT-MOD-001` `RN-7`): el
 * moderador no da la cara ante las partes, porque eso lo expondría a quien
 * acaba de ser sancionado.
 */
#[AsController]
final readonly class ListMyClaimsController
{
    public function __construct(
        private ListMyClaimsHandler $claims,
        private Security $security,
    ) {
    }

    public function __invoke(): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        return new JsonResponse([
            'claims' => array_map(
                static fn (Claim $claim): array => [
                    'claimId' => $claim->id()->value(),
                    'targetType' => $claim->targetType()->value,
                    'targetId' => $claim->targetId(),
                    'type' => $claim->type()->value,
                    'status' => $claim->status()->value,
                    'submittedAt' => $claim->submittedAt()->format(\DATE_ATOM),
                ],
                ($this->claims)(new ListMyClaims($user->getUserIdentifier())),
            ),
        ]);
    }
}
