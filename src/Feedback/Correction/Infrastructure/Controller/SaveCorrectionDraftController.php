<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Controller;

use LectoresBeta\Feedback\Correction\Application\Command\SaveCorrectionDraft;
use LectoresBeta\Feedback\Correction\Application\Handler\SaveCorrectionDraftHandler;
use LectoresBeta\Feedback\Correction\Infrastructure\Http\AnswersPayload;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `PUT /api/v1/chapters/{chapterId}/correction/draft` (`FEAT-FBK-011`).
 *
 * Idempotente por naturaleza: el recurso es «el borrador de esta persona para
 * este capítulo», que es único. No necesita `Idempotency-Key`.
 */
#[AsController]
final readonly class SaveCorrectionDraftController
{
    public function __construct(
        private SaveCorrectionDraftHandler $save,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $chapterId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $draft = ($this->save)(new SaveCorrectionDraft(
            $chapterId,
            $user->getUserIdentifier(),
            AnswersPayload::of($request),
        ));

        return new JsonResponse([
            'correctionId' => $draft->correctionId,
            'questionnaireVersion' => $draft->questionnaireVersion,
            'questionnaireVersionChanged' => $draft->questionnaireVersionChanged,
        ], $draft->started ? Response::HTTP_CREATED : Response::HTTP_OK);
    }
}
