<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Controller;

use LectoresBeta\Feedback\Correction\Application\Command\SubmitCorrection;
use LectoresBeta\Feedback\Correction\Application\Handler\SubmitCorrectionHandler;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `POST /api/v1/chapters/{chapterId}/corrections` (`FEAT-FBK-003`).
 *
 * El envío. La respuesta **no lleva ninguna cifra de créditos**: el abono es
 * asíncrono y lo decide otro contexto, y devolver un importe aquí obligaría a
 * `Feedback` a conocer las reglas de `Credits`.
 */
#[AsController]
final readonly class SubmitCorrectionController
{
    public function __construct(
        private SubmitCorrectionHandler $submit,
        private Security $security,
    ) {
    }

    public function __invoke(Request $request, string $chapterId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $correctionId = ($this->submit)(new SubmitCorrection(
            $chapterId,
            $user->getUserIdentifier(),
            self::answersOf($request),
        ));

        return new JsonResponse(['correctionId' => $correctionId, 'status' => 'SUBMITTED'], Response::HTTP_CREATED);
    }

    /**
     * @return array<string, string>
     */
    private static function answersOf(Request $request): array
    {
        $raw = json_decode((string) $request->getContent(), true);

        if (!\is_array($raw) || !\is_array($raw['answers'] ?? null)) {
            throw new BadRequestHttpException('The body must carry a list of answers.');
        }

        $answers = [];

        foreach ($raw['answers'] as $item) {
            if (!\is_array($item) || !\is_string($item['questionId'] ?? null) || !\is_string($item['text'] ?? null)) {
                throw new BadRequestHttpException('Every answer must carry a questionId and its text.');
            }

            $answers[$item['questionId']] = $item['text'];
        }

        return $answers;
    }
}
