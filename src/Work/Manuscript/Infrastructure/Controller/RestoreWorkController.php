<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Manuscript\Infrastructure\Controller;

use LectoresBeta\Work\Manuscript\Application\Command\RestoreWork;
use LectoresBeta\Work\Manuscript\Application\Handler\RestoreWorkHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/works/{workId}/restore` (`FEAT-WRK-006` `RN-7`).
 *
 * Vuelve a **borrador**, nunca publicada: reabrir la puerta a los lectores
 * beta es una decisión aparte.
 */
#[AsController]
final readonly class RestoreWorkController
{
    public function __construct(
        private RestoreWorkHandler $restore,
        private Security $security,
    ) {
    }

    public function __invoke(string $workId): Response
    {
        $author = $this->security->getUser();

        if (null === $author) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->restore)(new RestoreWork($workId, $author->getUserIdentifier()));

        return new Response(status: Response::HTTP_NO_CONTENT);
    }
}
