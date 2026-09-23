<?php

declare(strict_types=1);

namespace LectoresBeta\Work\Questionnaire\Infrastructure\Controller;

use LectoresBeta\Work\Questionnaire\Application\DTO\QuestionView;
use LectoresBeta\Work\Questionnaire\Application\Handler\GetQuestionnaireHandler;
use LectoresBeta\Work\Questionnaire\Application\Query\GetQuestionnaire;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

/**
 * `GET /api/v1/works/{workId}/questionnaire` (`FEAT-WRK-014`).
 *
 * Una obra sin cuestionario todavía devuelve `version: 0` y una lista vacía,
 * no un `404`: es el estado normal de una obra recién creada, y un error ahí
 * obligaría al cliente a distinguir dos casos que para él son el mismo.
 */
#[AsController]
final readonly class GetQuestionnaireController
{
    public function __construct(
        private GetQuestionnaireHandler $questionnaire,
        private Security $security,
    ) {
    }

    public function __invoke(string $workId): Response
    {
        $user = $this->security->getUser();

        if (null === $user) {
            throw new UnauthorizedHttpException('Bearer');
        }

        $view = ($this->questionnaire)(new GetQuestionnaire($workId, $user->getUserIdentifier()));

        if (null === $view) {
            return new JsonResponse(['workId' => $workId, 'version' => 0, 'requiredWords' => 0, 'questions' => []]);
        }

        return new JsonResponse([
            'workId' => $view->workId,
            'version' => $view->version,
            'requiredWords' => $view->requiredWords,
            'questions' => array_map(
                static fn (QuestionView $q): array => [
                    'questionId' => $q->questionId,
                    'position' => $q->position,
                    'statement' => $q->statement,
                    'example' => $q->example,
                    'required' => $q->required,
                    'minWords' => $q->minWords,
                    'maxWords' => $q->maxWords,
                    'scope' => $q->scope,
                ],
                $view->questions,
            ),
        ]);
    }
}
