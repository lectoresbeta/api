<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Controller;

use LectoresBeta\Feedback\Correction\Application\Command\SubmitPublicCorrection;
use LectoresBeta\Feedback\Correction\Application\Handler\SubmitPublicCorrectionHandler;
use LectoresBeta\Feedback\Correction\Infrastructure\Http\AnswersPayload;
use LectoresBeta\Shared\Infrastructure\Http\JsonBody;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;

/**
 * `POST /api/v1/public/{token}/chapters/{chapterId}/corrections`
 * (`FEAT-FBK-008`).
 *
 * **La única escritura de toda la API que no exige sesión.** Lo que la
 * autoriza es el token, y lo que la contiene es el tope del enlace.
 *
 * Lee la sesión aunque no la exija: a quien ya tiene cuenta se le manda al
 * flujo normal, donde su corrección se paga (`RN-1`).
 */
#[AsController]
final readonly class SubmitPublicCorrectionController
{
    public function __construct(
        private SubmitPublicCorrectionHandler $submit,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $token, string $chapterId): Response
    {
        $body = JsonBody::of($request);

        $receipt = ($this->submit)(new SubmitPublicCorrection(
            $token,
            $chapterId,
            AnswersPayload::of($request),
            true === $body->bool('acceptedTerms'),
            $body->string('name'),
            $this->security->getUser()?->getUserIdentifier(),
        ));

        return new JsonResponse([
            'correctionId' => $receipt->correctionId,
            'wouldHaveBeenWorth' => $receipt->wouldHaveBeenWorth,
        ], Response::HTTP_CREATED);
    }
}
