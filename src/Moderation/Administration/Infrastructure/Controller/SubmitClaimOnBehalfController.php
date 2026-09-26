<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Administration\Infrastructure\Controller;

use LectoresBeta\Moderation\AuditLog\Application\Service\RecordAuditEntry;
use LectoresBeta\Moderation\Claim\Application\Command\SubmitClaim;
use LectoresBeta\Moderation\Claim\Application\Handler\SubmitClaimHandler;
use LectoresBeta\Moderation\Claim\Domain\ValueObject\PartyId;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/admin/claims/on-behalf` (`FEAT-MOD-005`).
 *
 * Cuando alguien tiene el botón de reclamar bloqueado escribe por correo, y
 * un moderador registra esa reclamación **en su nombre**.
 *
 * Pasa por el mismo caso de uso que una reclamación ordinaria, no por uno
 * paralelo: así hereda sus cuatro comprobaciones sin que nadie tenga que
 * acordarse de repetirlas. Lo único que cambia es que **el bloqueo no cuenta
 * y el cupo sí** —si el bloqueo se aplicara aquí, la puerta de atrás no
 * abriría nada—, y que la reclamación queda marcada con quién la registró.
 *
 * Esa marca sostiene `RN-14`: quien la redactó a partir de un correo **no
 * puede resolverla**. Registrarla no es decidir, pero quien la ha escrito ya
 * se ha formado una opinión.
 */
#[AsController]
final readonly class SubmitClaimOnBehalfController
{
    public function __construct(
        private SubmitClaimHandler $submit,
        private RecordAuditEntry $audit,
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

        $claim = ($this->submit)(new SubmitClaim(
            (string) $body->string('onBehalfOf'),
            (string) $body->string('targetType'),
            (string) $body->string('targetId'),
            (string) $body->string('reason'),
            $body->string('description'),
            $moderator->getUserIdentifier(),
        ));

        $this->audit->of(
            PartyId::fromString($moderator->getUserIdentifier()),
            'CLAIM_FILED_ON_BEHALF',
            'CLAIM',
            $claim->claimId,
            null,
            ['onBehalfOf' => $body->string('onBehalfOf')],
        );

        return new JsonResponse([
            'claimId' => $claim->claimId,
            'status' => $claim->status,
            'alreadyExisted' => $claim->alreadyExisted,
        ], $claim->alreadyExisted ? Response::HTTP_OK : Response::HTTP_CREATED);
    }
}
