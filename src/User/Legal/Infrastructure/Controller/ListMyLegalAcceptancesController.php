<?php

declare(strict_types=1);

namespace LectoresBeta\User\Legal\Infrastructure\Controller;

use LectoresBeta\User\Legal\Application\Handler\ListMyLegalAcceptancesHandler;
use LectoresBeta\User\Legal\Application\Query\ListMyLegalAcceptances;
use LectoresBeta\User\Legal\Domain\Entity\LegalAcceptance;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/me/legal-acceptances` (`FEAT-USR-024` `RN-6`).
 *
 * **El derecho a ver la prueba.** Cada fila lleva qué documento, qué versión
 * y cuándo: un «aceptaste las condiciones» sin versión sería exactamente el
 * booleano que `RN-2` evita.
 *
 * No lleva la dirección IP desde la que se aceptó, aunque se guarde. Es un
 * dato de la prueba y no del usuario, y una respuesta que se puede pedir en
 * cualquier momento no necesita repetirlo.
 */
#[AsController]
final readonly class ListMyLegalAcceptancesController
{
    public function __construct(
        private ListMyLegalAcceptancesHandler $acceptances,
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
            'acceptances' => array_map(
                static fn (LegalAcceptance $acceptance): array => [
                    'type' => $acceptance->documentType()->value,
                    'version' => $acceptance->version(),
                    'acceptedAt' => $acceptance->acceptedAt()->format(\DATE_ATOM),
                ],
                ($this->acceptances)(new ListMyLegalAcceptances($user->getUserIdentifier())),
            ),
        ]);
    }
}
