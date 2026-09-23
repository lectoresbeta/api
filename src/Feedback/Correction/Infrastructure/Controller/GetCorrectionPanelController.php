<?php

declare(strict_types=1);

namespace LectoresBeta\Feedback\Correction\Infrastructure\Controller;

use LectoresBeta\Feedback\Correction\Application\DTO\PanelQuestionView;
use LectoresBeta\Feedback\Correction\Application\Handler\GetCorrectionPanelHandler;
use LectoresBeta\Feedback\Correction\Application\Query\GetCorrectionPanel;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/chapters/{chapterId}/questionnaire` (`FEAT-FBK-003`).
 *
 * El cuestionario tal y como lo responde el lector: solo las preguntas que
 * aplican a **este** capítulo, y lo que ya hubiera escrito.
 *
 * Es la otra mitad de `getWorkQuestionnaire`, que es la del autor. Las dos
 * describen el mismo objeto y no son la misma representación: el autor ve
 * cómo está configurado el formulario; el lector, lo que necesita para
 * responderlo.
 */
#[AsController]
final readonly class GetCorrectionPanelController
{
    public function __construct(
        private GetCorrectionPanelHandler $panel,
        private Security $security,
    ) {
    }

    public function __invoke(string $chapterId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $view = ($this->panel)(new GetCorrectionPanel($chapterId, $user->getUserIdentifier()));

        return new JsonResponse([
            'chapterId' => $view->chapterId,
            'workId' => $view->workId,
            'questionnaireVersion' => $view->questionnaireVersion,
            'correctionId' => $view->correctionId,
            'status' => $view->status,
            'questions' => array_map(
                static fn (PanelQuestionView $question): array => [
                    'questionId' => $question->questionId,
                    'position' => $question->position,
                    'statement' => $question->statement,
                    'example' => $question->example,
                    'required' => $question->required,
                    'minWords' => $question->minWords,
                    'maxWords' => $question->maxWords,
                    'answer' => $question->answer,
                ],
                $view->questions,
            ),
        ]);
    }
}
