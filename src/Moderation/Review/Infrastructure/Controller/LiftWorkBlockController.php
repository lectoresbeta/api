<?php

declare(strict_types=1);

namespace LectoresBeta\Moderation\Review\Infrastructure\Controller;

use LectoresBeta\Moderation\Review\Application\Command\LiftWorkBlock;
use LectoresBeta\Moderation\Review\Application\Handler\LiftWorkBlockHandler;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/admin/moderation-blocks/lift` (`FEAT-MOD-003` `RN-7`).
 *
 * Hasta ahora un bloqueo era definitivo **de hecho**: la regla decía que un
 * moderador podía revocarlo y no había por dónde pedirlo, así que un error no
 * tenía arreglo.
 *
 * Bajo `/admin`, que exige rol de moderador. La motivación es obligatoria y
 * va al registro de auditoría: deshacer una decisión necesita explicarse
 * todavía más que tomarla.
 */
#[AsController]
final readonly class LiftWorkBlockController
{
    public function __construct(
        private LiftWorkBlockHandler $lift,
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

        ($this->lift)(new LiftWorkBlock(
            (string) $body->string('targetType'),
            (string) $body->string('targetId'),
            $moderator->getUserIdentifier(),
            (string) $body->string('motivation'),
        ));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
