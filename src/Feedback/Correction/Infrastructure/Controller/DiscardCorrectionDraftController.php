<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Controller;

use LectoresBeta\Feedback\Correction\Application\Command\DiscardCorrectionDraft;
use LectoresBeta\Feedback\Correction\Application\Handler\DiscardCorrectionDraftHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `DELETE /api/v1/chapters/{chapterId}/correction/draft` (`FEAT-FBK-011`).
 *
 * Descartar un borrador que no existe devuelve `204` igual: lo que se pedía
 * —que no haya borrador— ya se cumple.
 */
#[AsController]
final readonly class DiscardCorrectionDraftController
{
    public function __construct(
        private DiscardCorrectionDraftHandler $discard,
        private Security $security,
    ) {
    }

    public function __invoke(string $chapterId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        ($this->discard)(new DiscardCorrectionDraft($chapterId, $user->getUserIdentifier()));

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
