<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Controller;

use LectoresBeta\Feedback\Correction\Application\DTO\AnsweredQuestionView;
use LectoresBeta\Feedback\Correction\Application\Handler\GetCorrectionHandler;
use LectoresBeta\Feedback\Correction\Application\Query\GetCorrection;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/corrections/{correctionId}` (`FEAT-FBK-004`).
 *
 * La leen **dos personas**: su destinatario y quien la escribió. Cualquier
 * otra recibe `404`, igual que si no existiera.
 *
 * Retenida por descubierto: llega la cabecera con `answers` vacío y
 * `visibility: LOCKED`. El autor ve que existe y de quién es, que es
 * exactamente lo que `FEAT-CRD-018` quiere que vea.
 */
#[AsController]
final readonly class GetCorrectionController
{
    public function __construct(
        private GetCorrectionHandler $correction,
        private Security $security,
    ) {
    }

    public function __invoke(string $correctionId): Response
    {
        $reader = $this->security->getUser();

        if (null === $reader) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $correction = ($this->correction)(new GetCorrection($correctionId, $reader->getUserIdentifier()));

        return new JsonResponse([
            'correctionId' => $correction->correctionId,
            'workId' => $correction->workId,
            'chapterId' => $correction->chapterId,
            'chapterTitle' => $correction->chapterTitle,
            'readerId' => $correction->readerId,
            'authorLabel' => $correction->authorLabel,
            'origin' => $correction->origin,
            'visibility' => $correction->visibility,
            'questionnaireVersion' => $correction->questionnaireVersion,
            'submittedAt' => $correction->submittedAt,
            'read' => $correction->read,
            'helpful' => $correction->helpful,
            'reply' => $correction->reply,
            'answers' => array_map(
                static fn (AnsweredQuestionView $answer): array => [
                    'questionId' => $answer->questionId,
                    'position' => $answer->position,
                    'statement' => $answer->statement,
                    'text' => $answer->text,
                    'wordCount' => $answer->wordCount,
                ],
                $correction->answers,
            ),
        ]);
    }
}
